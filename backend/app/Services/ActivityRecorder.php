<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityRecorder
{
    public function record(User $user, ActivityAction $action, ?Model $subject = null, array $metadata = [], ?string $subjectName = null): Activity
    {
        return $this->recordFor($user, $user, $action, $subject, $metadata, $subjectName);
    }

    public function recordFor(User $feedUser, User $actor, ActivityAction $action, ?Model $subject = null, array $metadata = [], ?string $subjectName = null): Activity
    {
        $type = match (true) {
            $subject instanceof File => 'file',
            $subject instanceof Folder => 'folder',
            default => null,
        };

        return Activity::create([
            'user_id' => $feedUser->getKey(),
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'subject_type' => $type,
            'subject_id' => $subject?->getKey(),
            'subject_uuid' => $subject?->uuid,
            'subject_name' => $subjectName ?? $this->name($subject),
            'metadata' => $this->normalize($metadata),
        ]);
    }

    private function name(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof File => $subject->original_name,
            $subject instanceof Folder => $subject->name,
            default => null,
        };
    }

    private function normalize(array $metadata): array
    {
        $forbidden = ['path', 'absolutePath', 'stored_name', 'storedName', 'disk', 'password', 'token'];

        return collect($metadata)->reject(fn ($value, $key) => in_array((string) $key, $forbidden, true))
            ->map(function ($value) {
                if (is_array($value)) {
                    return $this->normalize($value);
                }

                return $value;
            })->all();
    }
}
