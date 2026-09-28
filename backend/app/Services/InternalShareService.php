<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\SharePermission;
use App\Exceptions\ShareConflictException;
use App\Models\File;
use App\Models\Folder;
use App\Models\InternalShare;
use App\Models\NotificationPreference;
use App\Models\PublicShareLink;
use App\Models\User;
use App\Notifications\InternalSharePermissionChanged;
use App\Notifications\InternalShareReceived;
use App\Notifications\InternalShareRevoked;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InternalShareService
{
    public function __construct(private readonly ActivityRecorder $activities, private readonly SystemSettingService $settings, private readonly PushNotificationService $push) {}

    public function create(User $owner, File|Folder $item, int $recipientId, SharePermission $permission): InternalShare
    {
        if (! $this->settings->getBool('sharing.internal_enabled')) {
            throw new HttpException(409, 'Internal sharing is currently disabled.');
        }
        if ($item->trashed_at !== null) {
            throw ValidationException::withMessages(['item' => 'Trashed items cannot be shared.']);
        }
        $recipient = User::query()->whereKey($recipientId)->where('status', 'active')->first();
        if ($recipient === null) {
            throw ValidationException::withMessages(['recipientId' => 'The recipient must be an active Drive user.']);
        }
        if ($recipient->getKey() === $owner->getKey()) {
            throw ValidationException::withMessages(['recipientId' => 'You cannot share an item with yourself.']);
        }

        $type = $item instanceof File ? 'file' : 'folder';
        if (InternalShare::query()->where('recipient_id', $recipient->getKey())->where('shareable_type', $type)->where('shareable_id', $item->getKey())->exists()) {
            throw new ShareConflictException('This item is already shared with that user.');
        }

        return DB::transaction(function () use ($owner, $recipient, $item, $type, $permission): InternalShare {
            $share = InternalShare::create([
                'owner_id' => $item->owner_id,
                'recipient_id' => $recipient->getKey(),
                'shareable_type' => $type,
                'shareable_id' => $item->getKey(),
                'permission' => $permission,
            ]);
            $metadata = ['recipientName' => $recipient->name, 'permission' => $permission->value, 'shareableType' => $type];
            $this->activities->record($owner, ActivityAction::ShareCreated, $item, $metadata);
            $this->activities->recordFor($recipient, $owner, ActivityAction::ShareReceived, $item, ['ownerName' => $owner->name, 'permission' => $permission->value, 'shareableType' => $type]);
            if ((NotificationPreference::query()->where('user_id', $recipient->getKey())->value('shares') ?? true) === true) {
                $recipient->notify(new InternalShareReceived($item, $permission->value, $owner->getKey(), $owner->name));
                $this->push->send($recipient, 'share.received', 'Item shared with you', $owner->name.' shared an item with you.', '/shared');
            }

            return $share->load('recipient');
        });
    }

    public function update(User $owner, InternalShare $share, SharePermission $permission): InternalShare
    {
        $old = $share->permission;
        $item = $this->item($share);
        $recipient = $share->recipient;

        return DB::transaction(function () use ($owner, $share, $permission, $old, $item, $recipient): InternalShare {
            $share->update(['permission' => $permission]);
            $this->activities->record($owner, ActivityAction::SharePermissionUpdated, $item, [
                'recipientName' => $recipient->name,
                'from' => $old->value,
                'to' => $permission->value,
            ]);
            if ($old !== $permission) {
                if ((NotificationPreference::query()->where('user_id', $recipient->getKey())->value('shares') ?? true) === true) {
                    $recipient->notify(new InternalSharePermissionChanged($item, $old->value, $permission->value, $owner->getKey(), $owner->name));
                    $this->push->send($recipient, 'share.permission_changed', 'Sharing permission changed', $owner->name.' changed your access to a shared item.', '/shared');
                }
            }

            return $share->fresh('recipient');
        });
    }

    public function revoke(User $owner, InternalShare $share): void
    {
        $item = $this->item($share);
        $recipient = $share->recipient;
        $itemType = $share->shareable_type;

        DB::transaction(function () use ($owner, $share, $item, $recipient, $itemType): void {
            $item = $this->item($share);
            $this->activities->record($owner, ActivityAction::ShareRevoked, $item, ['recipientName' => $recipient->name, 'permission' => $share->permission->value]);
            if ((NotificationPreference::query()->where('user_id', $recipient->getKey())->value('shares') ?? true) === true) {
                $recipient->notify(new InternalShareRevoked($itemType, $item->uuid, $item instanceof File ? $item->original_name : $item->name, $owner->getKey(), $owner->name));
                $this->push->send($recipient, 'share.revoked', 'Sharing access removed', $owner->name.' removed your access to a shared item.', '/shared');
            }
            $share->delete();
        });
    }

    public function list(User $owner, File|Folder $item): Collection
    {
        $ownerEntry = (object) ['uuid' => null, 'permission' => null, 'user' => $owner, 'created_at' => null, 'updated_at' => null];
        $shares = InternalShare::query()->where('owner_id', $owner->getKey())->where('shareable_type', $item instanceof File ? 'file' : 'folder')->where('shareable_id', $item->getKey())->with('recipient')->latest()->get();
        $shares->prepend($ownerEntry);

        return $shares;
    }

    public function sharedWithMe(User $user, int $limit, ?string $search = null): CursorPaginator
    {
        $query = InternalShare::query()->where('recipient_id', $user->getKey())->latest('created_at')->latest('id');
        $page = $query->cursorPaginate($limit);
        $page->setCollection($page->getCollection()->map(fn (InternalShare $share) => $this->sharedItem($share))->filter(fn (?object $item): bool => $item !== null && ($search === null || str_contains(mb_strtolower($item->name), mb_strtolower($search))))->values());

        return $page;
    }

    public function sharedByMe(User $owner, int $limit, ?string $search = null): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $groups = InternalShare::query()->where('owner_id', $owner->getKey())->selectRaw('shareable_type, shareable_id, MAX(created_at) as shared_at')->groupBy('shareable_type', 'shareable_id')->orderByDesc('shared_at')->get();
        $items = $groups->map(fn ($group) => $this->sharedByItem($owner, $group))->filter(fn (?object $item): bool => $item !== null && ($search === null || str_contains(mb_strtolower($item->name), mb_strtolower($search))))->values();
        $page = max((int) request()->input('page', 1), 1);
        $slice = $items->forPage($page, $limit)->values();

        return new LengthAwarePaginator($slice, $items->count(), $limit, $page, ['path' => request()->url(), 'query' => request()->query()]);
    }

    private function item(InternalShare $share): File|Folder
    {
        return $share->shareable_type === 'file' ? File::query()->findOrFail($share->shareable_id) : Folder::query()->findOrFail($share->shareable_id);
    }

    private function sharedItem(InternalShare $share): ?object
    {
        $item = $this->shareable($share);
        if ($item === null || $item->trashed_at !== null || ! User::query()->whereKey($share->owner_id)->where('status', 'active')->exists()) {
            return null;
        }

        return (object) ['item' => $item, 'type' => $share->shareable_type, 'owner' => $share->owner, 'permission' => $share->permission, 'sharedAt' => $share->created_at];
    }

    private function sharedByItem(User $owner, object $group): ?object
    {
        $item = $group->shareable_type === 'file' ? File::query()->where('owner_id', $owner->getKey())->whereKey($group->shareable_id)->first() : Folder::query()->where('owner_id', $owner->getKey())->whereKey($group->shareable_id)->first();
        if ($item === null) {
            return null;
        }
        $shares = InternalShare::query()->where('owner_id', $owner->getKey())->where('shareable_type', $group->shareable_type)->where('shareable_id', $group->shareable_id)->with('recipient')->get();

        return (object) ['item' => $item, 'type' => $group->shareable_type, 'owner' => $owner, 'permission' => $shares->sortByDesc(fn (InternalShare $share) => $share->permission->rank())->first()->permission, 'sharedAt' => $group->shared_at, 'recipientCount' => $shares->count(), 'recipients' => $shares->map(fn (InternalShare $share) => ['id' => $share->recipient->getKey(), 'name' => $share->recipient->name, 'permission' => $share->permission->value])->values()->all(), 'publicLinkEnabled' => PublicShareLink::query()->where('shareable_type', $group->shareable_type)->where('shareable_id', $group->shareable_id)->where('enabled', true)->exists()];
    }

    private function shareable(InternalShare $share): File|Folder|null
    {
        return $share->shareable_type === 'file' ? File::query()->where('owner_id', $share->owner_id)->find($share->shareable_id) : Folder::query()->where('owner_id', $share->owner_id)->find($share->shareable_id);
    }
}
