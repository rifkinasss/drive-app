<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicLinkResource;
use App\Models\File;
use App\Models\Folder;
use App\Models\PublicShareLink;
use App\Services\PublicShareService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicLinkController
{
    public function fileStore(Request $request, File $file, PublicShareService $public): JsonResponse
    {
        $this->owner($request, $file->owner_id);
        [$link, $url] = $public->enable($request->user(), $file);

        return ApiResponse::success((new PublicLinkResource((object) ['link' => $link, 'item' => $file, 'type' => 'file', 'url' => $url]))->resolve($request), 'Public link enabled.', 201);
    }

    public function folderStore(Request $request, Folder $folder, PublicShareService $public): JsonResponse
    {
        $this->owner($request, $folder->owner_id);
        [$link, $url] = $public->enable($request->user(), $folder);

        return ApiResponse::success((new PublicLinkResource((object) ['link' => $link, 'item' => $folder, 'type' => 'folder', 'url' => $url]))->resolve($request), 'Public link enabled.', 201);
    }

    public function fileShow(Request $request, File $file, PublicShareService $public): JsonResponse
    {
        return $this->show($request, $file, $public, 'file');
    }

    public function folderShow(Request $request, Folder $folder, PublicShareService $public): JsonResponse
    {
        return $this->show($request, $folder, $public, 'folder');
    }

    public function fileDestroy(Request $request, File $file, PublicShareService $public): JsonResponse
    {
        return $this->destroy($request, $file, $public);
    }

    public function folderDestroy(Request $request, Folder $folder, PublicShareService $public): JsonResponse
    {
        return $this->destroy($request, $folder, $public);
    }

    public function fileRegenerate(Request $request, File $file, PublicShareService $public): JsonResponse
    {
        return $this->regenerate($request, $file, $public, 'file');
    }

    public function folderRegenerate(Request $request, Folder $folder, PublicShareService $public): JsonResponse
    {
        return $this->regenerate($request, $folder, $public, 'folder');
    }

    public function fileUpdate(Request $request, File $file, PublicShareService $public): JsonResponse
    {
        return $this->update($request, $file, $public, 'file');
    }

    public function folderUpdate(Request $request, Folder $folder, PublicShareService $public): JsonResponse
    {
        return $this->update($request, $folder, $public, 'folder');
    }

    public function analytics(Request $request, PublicShareLink $link, PublicShareService $public): JsonResponse
    {
        return ApiResponse::success($public->analytics($request->user(), $link));
    }

    private function update(Request $request, File|Folder $item, PublicShareService $public, string $type): JsonResponse
    {
        $this->owner($request, $item->owner_id);
        $request->validate([
            'expiration' => ['sometimes', Rule::in(['never', '24h', '7d', '30d', 'custom'])],
            'expiresAt' => ['nullable', 'date', 'after:now'],
            'password' => ['nullable', 'string', 'min:4', 'max:128'],
            'allowDownload' => ['sometimes', 'boolean'],
        ]);
        $expiration = $request->input('expiration', 'never');
        $expiresAt = match ($expiration) {
            '24h' => now()->addDay(),
            '7d' => now()->addDays(7),
            '30d' => now()->addDays(30),
            'custom' => $request->date('expiresAt'),
            default => null,
        };
        $options = [
            'expiresAt' => $expiresAt,
            'allowDownload' => $request->boolean('allowDownload', true),
        ];
        if ($request->has('password')) {
            $options['password'] = $request->input('password');
        }
        [$link, $url] = $public->configure($request->user(), $item, $options);

        return ApiResponse::success((new PublicLinkResource((object) ['link' => $link, 'item' => $item, 'type' => $type, 'url' => $url]))->resolve($request));
    }

    private function show(Request $request, File|Folder $item, PublicShareService $public, string $type): JsonResponse
    {
        $this->owner($request, $item->owner_id);
        $link = $public->status($item);

        return ApiResponse::success((new PublicLinkResource((object) ['link' => $link, 'item' => $item, 'type' => $type, 'url' => $link?->enabled ? $public->rawUrl($link) : null]))->resolve($request));
    }

    private function destroy(Request $request, File|Folder $item, PublicShareService $public): JsonResponse
    {
        $this->owner($request, $item->owner_id);
        $public->disable($request->user(), $item);

        return ApiResponse::success(null, 'Public link disabled.');
    }

    private function regenerate(Request $request, File|Folder $item, PublicShareService $public, string $type): JsonResponse
    {
        $this->owner($request, $item->owner_id);
        [$link, $url] = $public->regenerate($request->user(), $item);

        return ApiResponse::success((new PublicLinkResource((object) ['link' => $link, 'item' => $item, 'type' => $type, 'url' => $url]))->resolve($request), 'Public link regenerated.');
    }

    private function owner(Request $request, int $ownerId): void
    {
        abort_unless($request->user()->getKey() === $ownerId, 404);
    }
}
