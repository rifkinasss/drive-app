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
    public function show(Request $request, string $token, PublicShareAccessService $access): JsonResponse
    {
        $context = $access->root($token, $request->header('X-Share-Password'));
        app(\App\Services\PublicShareService::class)->recordView($context['link']);

        return ApiResponse::success((new PublicSharedItemResource((object) ['item' => $context['item'], 'type' => $context['type'], 'owner' => $context['link']->owner, 'link' => $context['link']]))->resolve(request()));
    }

    public function preview(Request $request, string $token, FileStreamService $stream, PublicShareAccessService $access)
    {
        $context = $access->root($token, $request->header('X-Share-Password'));
        abort_if($context['type'] !== 'file', 404);

        return $stream->preview($context['item'], $request);
    }

    public function download(Request $request, string $token, FileStreamService $stream, PublicShareAccessService $access)
    {
        $context = $access->root($token, $request->header('X-Share-Password'));
        abort_if($context['type'] !== 'file', 404);

        abort_unless($context['link']->allow_download, 403, 'Downloads are disabled for this link.');
        $response = $stream->download($context['item'], $request);
        app(\App\Services\PublicShareService::class)->recordDownload($context['link']);

        return $response;
    }

    public function filePreview(Request $request, string $token, File $file, FileStreamService $stream, PublicShareAccessService $access)
    {
        $context = $access->file($token, $file, $request->header('X-Share-Password'));

        return $stream->preview($file, $request);
    }

    public function fileDownload(Request $request, string $token, File $file, FileStreamService $stream, PublicShareAccessService $access)
    {
        $context = $access->file($token, $file, $request->header('X-Share-Password'));
        abort_unless($context['link']->allow_download, 403, 'Downloads are disabled for this link.');
        $response = $stream->download($file, $request);
        app(\App\Services\PublicShareService::class)->recordDownload($context['link']);

        return $response;
    }
}
