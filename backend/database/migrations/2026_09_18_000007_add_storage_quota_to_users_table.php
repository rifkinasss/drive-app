<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedBigInteger('quota_bytes')->default((int) config('cloud.default_user_quota_bytes'))->after('status');
            $table->unsignedBigInteger('used_bytes')->default(0)->after('quota_bytes');
        });

        DB::table('users')->update([
            'used_bytes' => DB::raw('(SELECT COALESCE(SUM(size_bytes), 0) FROM files WHERE files.owner_id = users.id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['quota_bytes', 'used_bytes']);
        });
    }
};
