<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicSharedItemResource;
use App\Models\File;
use App\Services\FileStreamService;
use App\Services\PublicShareAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicShareController
{
    public function show(string $token, PublicShareAccessService $access): JsonResponse
    {
        $context = $access->root($token);

        return ApiResponse::success((new PublicSharedItemResource((object) ['item' => $context['item'], 'type' => $context['type'], 'owner' => $context['link']->owner]))->resolve(request()));
    }

    public function preview(Request $request, string $token, FileStreamService $stream, PublicShareAccessService $access)
    {
        $context = $access->root($token);
        abort_if($context['type'] !== 'file', 404);

        return $stream->preview($context['item'], $request);
    }

    public function download(Request $request, string $token, FileStreamService $stream, PublicShareAccessService $access)
    {
        $context = $access->root($token);
        abort_if($context['type'] !== 'file', 404);

        return $stream->download($context['item'], $request);
    }

    public function filePreview(Request $request, string $token, File $file, FileStreamService $stream, PublicShareAccessService $access)
    {
        $access->file($token, $file);

        return $stream->preview($file, $request);
    }

    public function fileDownload(Request $request, string $token, File $file, FileStreamService $stream, PublicShareAccessService $access)
    {
        $access->file($token, $file);

        return $stream->download($file, $request);
    }
}
