<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\File;
use App\Services\ActivityRecorder;
use App\Services\FileStreamService;
use Illuminate\Http\Request;

class FileDownloadController
{
    public function __invoke(Request $request, File $file, FileStreamService $streamer, ActivityRecorder $activities)
    {
        $this->authorizeFile($request, $file);
        if (! $request->hasHeader('Range')) {
            $activities->record($request->user(), ActivityAction::FileDownloaded, $file);
        }

        return $streamer->download($file, $request);
    }

    private function authorizeFile(Request $request, File $file): void
    {
        abort_unless($request->user()->can('view', $file), 404);
        abort_if($file->trashed_at !== null, 404);
    }
}
