<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\PublicSharePermission;
use App\Exceptions\PublicShareUnavailableException;
use App\Models\File;
use App\Models\Folder;
use App\Models\PublicShareLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PublicShareService
{
    public function __construct(private readonly ShareTokenService $tokens, private readonly ActivityRecorder $activities, private readonly SystemSettingService $settings) {}

    public function enable(User $owner, File|Folder $item): array
    {
        if (! $this->settings->getBool('sharing.public_links_enabled')) {
            throw new HttpException(409, 'Public links are currently disabled.');
        }
        abort_if($item->trashed_at !== null, 422, 'Trashed items cannot be publicly shared.');
        $type = $this->type($item);
        $existing = $this->link($item);
        if ($existing?->enabled) {
            return [$existing, $this->rawUrl($existing)];
        }

        return DB::transaction(function () use ($owner, $item, $type, $existing): array {
            [$link, $token] = $this->upsertRotated($owner, $item, $type, $existing);
            $this->activities->record($owner, ActivityAction::PublicLinkEnabled, $item, ['shareableType' => $type]);

            return [$link, $this->url($token)];
        });
    }

    public function disable(User $owner, File|Folder $item): ?PublicShareLink
    {
        $link = $this->link($item);
        if ($link === null || ! $link->enabled) {
            return $link;
        }

        return DB::transaction(function () use ($owner, $item, $link): PublicShareLink {
            $link->update(['enabled' => false]);
            $this->activities->record($owner, ActivityAction::PublicLinkDisabled, $item, ['shareableType' => $link->shareable_type]);

            return $link->fresh();
        });
    }

    public function regenerate(User $owner, File|Folder $item): array
    {
        if (! $this->settings->getBool('sharing.public_links_enabled')) {
            throw new HttpException(409, 'Public links are currently disabled.');
        }
        $type = $this->type($item);

        return DB::transaction(function () use ($owner, $item, $type): array {
            [$link, $token] = $this->upsertRotated($owner, $item, $type, $this->link($item));
            $this->activities->record($owner, ActivityAction::PublicLinkRegenerated, $item, ['shareableType' => $type]);

            return [$link, $this->url($token)];
        });
    }

    public function configure(User $owner, File|Folder $item, array $options): array
    {
        [$link, $url] = $this->enable($owner, $item);
        $attributes = [];
        if (array_key_exists('expiresAt', $options)) {
            $attributes['expires_at'] = $options['expiresAt'];
        }
        if (array_key_exists('password', $options)) {
            $attributes['password_hash'] = filled($options['password']) ? Hash::make($options['password']) : null;
        }
        if (array_key_exists('allowDownload', $options)) {
            $attributes['allow_download'] = (bool) $options['allowDownload'];
        }
        if ($attributes !== []) {
            $link->update($attributes);
            $link = $link->fresh();
        }

        return [$link, $url];
    }

    public function status(File|Folder $item): ?PublicShareLink
    {
        return $this->link($item);
    }

    public function recordView(PublicShareLink $link): void
    {
        $this->recordAccess($link, 'view_count');
    }

    public function recordDownload(PublicShareLink $link): void
    {
        $this->recordAccess($link, 'download_count');
    }

    public function analytics(User $owner, PublicShareLink $link): array
    {
        abort_unless($link->owner_id === $owner->getKey(), 403);

        return [
            'views' => (int) $link->view_count,
            'downloads' => (int) $link->download_count,
            'lastAccessedAt' => $link->last_accessed_at?->toISOString(),
            'createdAt' => $link->created_at?->toISOString(),
            'expiresAt' => $link->expires_at?->toISOString(),
            'status' => $this->statusFor($link),
        ];
    }

    public function statusFor(PublicShareLink $link): string
    {
        return ! $link->enabled ? 'revoked' : ($link->expires_at?->isPast() ? 'expired' : 'active');
    }

    public function resolve(string $rawToken, ?string $password = null): array
    {
        if (! $this->settings->getBool('sharing.public_links_enabled')) {
            throw new PublicShareUnavailableException;
        }
        $link = PublicShareLink::query()->where('token_hash', $this->tokens->hash($rawToken))->where('enabled', true)->first();
        if ($link === null || ($link->expires_at !== null && $link->expires_at->isPast())) {
            throw new PublicShareUnavailableException;
        }
        if (filled($link->password_hash) && (! filled($password) || ! Hash::check($password, $link->password_hash))) {
            abort(401, 'A password is required to access this shared item.');
        }
        $item = $link->shareable_type === 'file' ? File::query()->where('owner_id', $link->owner_id)->find($link->shareable_id) : Folder::query()->where('owner_id', $link->owner_id)->find($link->shareable_id);
        if ($item === null || $item->trashed_at !== null || ! User::query()->whereKey($link->owner_id)->where('status', 'active')->exists()) {
            throw new PublicShareUnavailableException;
        }

        return ['link' => $link, 'item' => $item, 'type' => $link->shareable_type];
    }

    public function links(User $owner, ?string $status = null, ?string $search = null): Collection
    {
        $query = PublicShareLink::query()->where('owner_id', $owner->getKey())->orderByDesc('updated_at')->with([]);
        if ($status === 'active' || $status === null) {
            $query->where('enabled', true);
        }
        if ($status === 'disabled') {
            $query->where('enabled', false);
        }

        return $query->get()->map(function (PublicShareLink $link) use ($search): ?object {
            $item = $link->shareable_type === 'file' ? File::query()->where('owner_id', $link->owner_id)->find($link->shareable_id) : Folder::query()->where('owner_id', $link->owner_id)->find($link->shareable_id);
            if ($item === null || ($search !== null && ! str_contains(mb_strtolower($this->name($item)), mb_strtolower($search)))) {
                return null;
            }

            return (object) ['link' => $link, 'item' => $item, 'type' => $link->shareable_type, 'url' => $link->enabled ? $this->rawUrl($link) : null];
        })->filter()->values();
    }

    public function rawUrl(PublicShareLink $link): string
    {
        return $this->url($this->tokens->decrypt($link->token_encrypted));
    }

    private function upsertRotated(User $owner, File|Folder $item, string $type, ?PublicShareLink $existing): array
    {
        $token = $this->tokens->generate();
        $attributes = ['owner_id' => $item->owner_id, 'shareable_type' => $type, 'shareable_id' => $item->getKey(), 'token_hash' => $this->tokens->hash($token), 'token_encrypted' => $this->tokens->encrypt($token), 'enabled' => true, 'permission' => PublicSharePermission::Viewer];
        if ($existing === null) {
            $link = PublicShareLink::create($attributes);
        } else {
            $existing->update($attributes);
            $link = $existing->fresh();
        }

        return [$link, $token];
    }

    private function link(File|Folder $item): ?PublicShareLink
    {
        return PublicShareLink::query()->where('shareable_type', $this->type($item))->where('shareable_id', $item->getKey())->first();
    }

    private function recordAccess(PublicShareLink $link, string $counter): void
    {
        PublicShareLink::query()->whereKey($link->getKey())->update([
            $counter => DB::raw($counter.' + 1'),
            'last_accessed_at' => now(),
        ]);
    }

    private function type(Model $item): string
    {
        return $item instanceof File ? 'file' : 'folder';
    }

    private function name(Model $item): string
    {
        return $item instanceof File ? $item->original_name : $item->name;
    }

    private function url(string $token): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/s/'.$token;
    }
}
