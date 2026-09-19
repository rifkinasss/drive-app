<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

class FileStreamService
{
    public function download(File $file, Request $request): Response
    {
        return $this->stream($file, $request, false);
    }

    public function preview(File $file, Request $request): Response
    {
        $mimeType = $this->mimeType($file);
        if (! in_array($mimeType, config('cloud.previewable_mime_types', []), true)) {
            abort(415, 'Preview is not available for this file type.');
        }

        return $this->stream($file, $request, true, $mimeType);
    }

    private function stream(File $file, Request $request, bool $inline, ?string $mimeType = null): Response
    {
        $storage = $this->storage($file);
        if ($storage === null || $file->path === null || ! $storage->exists($file->path)) {
            Log::warning('File metadata has no available physical object.', ['file_uuid' => $file->uuid]);
            abort(404, 'File is unavailable.');
        }

        try {
            $actualSize = $storage->size($file->path);
        } catch (Throwable $exception) {
            Log::warning('Unable to inspect physical file size.', ['file_uuid' => $file->uuid, 'exception' => $exception]);
            abort(404, 'File is unavailable.');
        }

        if ($file->size_bytes !== $actualSize) {
            Log::warning('File metadata size differs from physical file size.', [
                'file_uuid' => $file->uuid,
                'metadata_size' => $file->size_bytes,
                'physical_size' => $actualSize,
            ]);
        }

        $range = $this->range($request->header('Range'), $actualSize);
        if ($range === false) {
            return response('', 416, [
                'Accept-Ranges' => 'bytes',
                'Content-Range' => 'bytes */'.$actualSize,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        [$start, $end] = $range ?? [0, max(0, $actualSize - 1)];
        $length = $actualSize === 0 ? 0 : $end - $start + 1;
        $status = $range === null ? 200 : 206;
        $headers = [
            'Content-Type' => $mimeType ?? $this->mimeType($file),
            'Content-Length' => (string) $length,
            'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition(
                $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $this->safeFilename($file->original_name),
                $this->fallbackFilename($file->original_name),
            ),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Accept-Ranges' => 'bytes',
        ];
        if ($range !== null) {
            $headers['Content-Range'] = 'bytes '.$start.'-'.$end.'/'.$actualSize;
        }

        return response()->stream(function () use ($storage, $file, $start, $length): void {
            $stream = $storage->readStream($file->path);
            if ($stream === false) {
                Log::warning('Unable to open physical file stream.', ['file_uuid' => $file->uuid]);

                return;
            }

            try {
                if ($start > 0) {
                    fseek($stream, $start);
                }
                $remaining = $length;
                while ($remaining > 0 && ! feof($stream)) {
                    $chunk = fread($stream, min(8192, $remaining));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                }
            } finally {
                fclose($stream);
            }
        }, $status, $headers);
    }

    private function storage(File $file): ?FilesystemAdapter
    {
        try {
            return Storage::disk($file->disk ?: config('cloud.disk'));
        } catch (Throwable $exception) {
            Log::warning('File metadata references an unavailable storage disk.', ['file_uuid' => $file->uuid, 'exception' => $exception]);

            return null;
        }
    }

    private function mimeType(File $file): string
    {
        $mimeType = trim((string) $file->mime_type);

        return $mimeType !== '' && preg_match('/^[^\r\n]+$/', $mimeType) === 1
            ? $mimeType
            : 'application/octet-stream';
    }

    private function safeFilename(string $filename): string
    {
        $filename = trim($filename);
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '_', $filename) ?: 'download';
        $filename = str_replace(['\\', '/'], '_', $filename);

        return $filename === '' ? 'download' : $filename;
    }

    private function fallbackFilename(string $filename): string
    {
        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $this->safeFilename($filename)) ?: 'download';
        $fallback = str_replace(['"', "'", '\\', '/'], '_', $fallback);

        return trim($fallback) === '' ? 'download' : trim($fallback);
    }

    private function range(?string $header, int $size): array|false|null
    {
        if ($header === null) {
            return null;
        }
        if ($size === 0 || ! preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $matches)) {
            return false;
        }

        $start = $matches[1] === '' ? null : (int) $matches[1];
        $end = $matches[2] === '' ? null : (int) $matches[2];
        if ($start === null) {
            $suffixLength = $end ?? 0;
            if ($suffixLength <= 0) {
                return false;
            }
            $start = max(0, $size - $suffixLength);
            $end = $size - 1;
        } else {
            if ($start >= $size) {
                return false;
            }
            $end = $end === null ? $size - 1 : min($end, $size - 1);
            if ($end < $start) {
                return false;
            }
        }

        return [$start, $end];
    }
}
