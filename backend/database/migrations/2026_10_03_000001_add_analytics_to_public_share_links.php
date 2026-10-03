<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_share_links', function (Blueprint $table): void {
            $table->unsignedBigInteger('view_count')->default(0)->after('allow_download');
            $table->unsignedBigInteger('download_count')->default(0)->after('view_count');
            $table->timestamp('last_accessed_at')->nullable()->after('download_count')->index();
        });
    }

    public function down(): void
    {
        Schema::table('public_share_links', function (Blueprint $table): void {
            $table->dropIndex(['last_accessed_at']);
            $table->dropColumn(['view_count', 'download_count', 'last_accessed_at']);
        });
    }
};
