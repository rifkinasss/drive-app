<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\FileStreamService;
use Illuminate\Http\Request;

class FilePreviewController
{
    public function __invoke(Request $request, File $file, FileStreamService $streamer)
    {
        abort_unless($request->user()->can('view', $file), 404);
        abort_if($file->trashed_at !== null, 404);

        return $streamer->preview($file, $request);
    }
}
