<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Folder;
use App\Models\InternalShare;
use App\Models\User;
use App\Services\StorageNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');
        Storage::fake('cloud');
    }

    public function test_share_received_notification_is_private_and_readable(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $other = User::factory()->create();
        $file = File::factory()->create(['owner_id' => $owner->id, 'original_name' => 'Proposal.pdf']);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/files/'.$file->uuid.'/shares', ['recipientId' => $recipient->id, 'permission' => 'viewer'])
            ->assertCreated();

        $this->assertDatabaseCount('notifications', 1);
        $this->actingAs($owner, 'sanctum')->getJson('/api/notifications/unread-count')->assertJsonPath('data.unreadCount', 0);
        $this->actingAs($recipient, 'sanctum')
            ->getJson('/api/notifications?status=unread')
            ->assertOk()
            ->assertJsonPath('data.items.0.type', 'share.received')
            ->assertJsonPath('data.items.0.data.itemName', 'Proposal.pdf')
            ->assertJsonPath('data.items.0.data.permission', 'viewer')
            ->assertJsonMissingPath('data.items.0.data.owner.email');

        $notificationId = $recipient->notifications()->firstOrFail()->id;
        $this->actingAs($other, 'sanctum')->postJson('/api/notifications/'.$notificationId.'/read')->assertNotFound();
        $this->actingAs($recipient, 'sanctum')->postJson('/api/notifications/'.$notificationId.'/read')->assertOk();
        $this->postJson('/api/notifications/'.$notificationId.'/read')->assertOk();
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('data.unreadCount', 0);
    }

    public function test_permission_change_and_revoke_keep_recipient_snapshots(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $file = File::factory()->create(['owner_id' => $owner->id, 'original_name' => 'Shared.txt']);
        $this->actingAs($owner, 'sanctum');
        $this->postJson('/api/files/'.$file->uuid.'/shares', ['recipientId' => $recipient->id, 'permission' => 'viewer'])->assertCreated();
        $share = InternalShare::query()->firstOrFail();

        $this->patchJson('/api/shares/'.$share->uuid, ['permission' => 'editor'])->assertOk();
        $this->deleteJson('/api/shares/'.$share->uuid)->assertOk();

        $notifications = $recipient->notifications()->oldest('created_at')->get();
        $this->assertCount(3, $notifications);
        $this->assertSame('share.permission_changed', $notifications[1]->type);
        $this->assertSame('editor', $notifications[1]->data['newPermission']);
        $this->assertSame('share.revoked', $notifications[2]->type);
        $this->assertSame('Shared.txt', $notifications[2]->data['itemName']);
        $this->assertNull($notifications[2]->data['target']);
    }

    public function test_folder_share_creates_a_private_notification_for_the_recipient(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $owner->id, 'name' => 'Design assets']);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/folders/'.$folder->uuid.'/shares', [
                'recipientId' => $recipient->id,
                'permission' => 'viewer',
            ])
            ->assertCreated();

        $this->actingAs($recipient, 'sanctum')
            ->getJson('/api/notifications?status=unread')
            ->assertOk()
            ->assertJsonPath('data.items.0.type', 'share.received')
            ->assertJsonPath('data.items.0.data.itemName', 'Design assets')
            ->assertJsonPath('data.items.0.data.itemType', 'folder');
    }

    public function test_read_all_and_filters_are_scoped_to_current_user(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $file = File::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner, 'sanctum')->postJson('/api/files/'.$file->uuid.'/shares', ['recipientId' => $recipient->id, 'permission' => 'viewer']);

        $this->actingAs($recipient, 'sanctum')->postJson('/api/notifications/read-all')
            ->assertOk()->assertJsonPath('data.unreadCount', 0);
        $this->getJson('/api/notifications?status=read')->assertJsonCount(1, 'data.items');
        $this->getJson('/api/notifications?status=unread')->assertJsonCount(0, 'data.items');
    }

    public function test_quota_warning_crossing_deduplicates_and_resets_thresholds(): void
    {
        $user = User::factory()->create(['quota_bytes' => 100, 'used_bytes' => 79]);
        $service = app(StorageNotificationService::class);

        $service->evaluate($user);
        $user->update(['used_bytes' => 81]);
        $service->evaluate($user);
        $this->assertDatabaseCount('notifications', 1);
        $user->update(['used_bytes' => 85]);
        $service->evaluate($user);
        $this->assertDatabaseCount('notifications', 1);
        $user->update(['used_bytes' => 91]);
        $service->evaluate($user);
        $this->assertDatabaseCount('notifications', 2);
        $user->update(['used_bytes' => 70]);
        $service->evaluate($user);
        $user->update(['used_bytes' => 81]);
        $service->evaluate($user);

        $this->assertDatabaseCount('notifications', 3);
        $this->assertSame(80, $user->notifications()->latest('created_at')->firstOrFail()->data['threshold']);
    }
}
