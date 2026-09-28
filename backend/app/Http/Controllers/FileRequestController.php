<?php

namespace App\Http\Controllers;

use App\Exceptions\FileNameConflictException;
use App\Http\Requests\File\UploadFileRequest;
use App\Models\FileRequest;
use App\Models\Folder;
use App\Services\ShareTokenService;
use App\Services\UploadFileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FileRequestController
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(['items' => FileRequest::query()->where('owner_id', $request->user()->getKey())->with('folder')->latest()->get()->map(fn (FileRequest $item): array => $this->resource($item))->values()->all()]);
    }

    public function store(Request $request, ShareTokenService $tokens): JsonResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:160'], 'folderId' => ['required', 'uuid'], 'expiration' => ['sometimes', Rule::in(['never', '24h', '7d', '30d'])]]);
        $folder = Folder::query()->where('uuid', $data['folderId'])->where('owner_id', $request->user()->getKey())->notTrashed()->firstOrFail();
        $token = $tokens->generate();
        $expiresAt = match ($data['expiration'] ?? 'never') {
            '24h' => now()->addDay(), '7d' => now()->addDays(7), '30d' => now()->addDays(30), default => null
        };
        $item = FileRequest::create(['owner_id' => $request->user()->getKey(), 'folder_id' => $folder->getKey(), 'title' => trim($data['title']), 'token_hash' => $tokens->hash($token), 'token_encrypted' => $tokens->encrypt($token), 'enabled' => true, 'expires_at' => $expiresAt]);

        return ApiResponse::success($this->resource($item, $token), 'File request created.', 201);
    }

    public function update(Request $request, FileRequest $fileRequest): JsonResponse
    {
        abort_unless($fileRequest->owner_id === $request->user()->getKey(), 404);
        $data = $request->validate(['title' => ['sometimes', 'string', 'max:160'], 'enabled' => ['sometimes', 'boolean'], 'expiresAt' => ['nullable', 'date', 'after:now']]);
        $fileRequest->update(array_filter([
            'title' => $data['title'] ?? null,
            'enabled' => $data['enabled'] ?? null,
            'expires_at' => array_key_exists('expiresAt', $data) ? $data['expiresAt'] : null,
        ], static fn ($value, $key): bool => $value !== null || $key === 'expires_at', ARRAY_FILTER_USE_BOTH));

        return ApiResponse::success($this->resource($fileRequest->fresh('folder')));
    }

    public function destroy(Request $request, FileRequest $fileRequest): JsonResponse
    {
        abort_unless($fileRequest->owner_id === $request->user()->getKey(), 404);
        $fileRequest->delete();

        return ApiResponse::success(null, 'File request deleted.');
    }

    public function show(string $token, ShareTokenService $tokens): JsonResponse
    {
        $item = $this->resolve($token, $tokens);

        return ApiResponse::success(['title' => $item->title, 'expiresAt' => $item->expires_at?->toISOString(), 'ownerDisplayName' => $item->owner->name]);
    }

    public function upload(UploadFileRequest $request, string $token, ShareTokenService $tokens, UploadFileService $uploads): JsonResponse
    {
        $item = $this->resolve($token, $tokens);
        try {
            $file = $uploads->upload($item->owner, $request->file('file'), $item->folder->uuid, 'keep_both');
        } catch (FileNameConflictException) {
            return ApiResponse::error('Unable to accept this upload.', [], 422);
        }

        return ApiResponse::success(['uploaded' => true, 'fileId' => $file->uuid], 'File received.', 201);
    }

    private function resolve(string $token, ShareTokenService $tokens): FileRequest
    {
        $item = FileRequest::query()->with(['owner', 'folder'])->where('token_hash', $tokens->hash($token))->where('enabled', true)->first();
        abort_if($item === null || $item->expires_at?->isPast() || $item->folder->trashed_at !== null, 404, 'This file request is unavailable.');

        return $item;
    }

    private function resource(FileRequest $item, ?string $token = null): array
    {
        return ['id' => $item->uuid, 'title' => $item->title, 'folderId' => $item->folder?->uuid, 'folderName' => $item->folder?->name, 'enabled' => $item->enabled, 'expiresAt' => $item->expires_at?->toISOString(), 'url' => $token === null ? null : rtrim((string) config('app.frontend_url'), '/').'/request/'.$token];
    }
}
