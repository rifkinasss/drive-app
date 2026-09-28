<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_share_links', function (Blueprint $table): void {
            $table->string('password_hash')->nullable()->after('permission');
            $table->boolean('allow_download')->default(true)->after('password_hash');
        });
    }

    public function down(): void
    {
        Schema::table('public_share_links', function (Blueprint $table): void {
            $table->dropColumn(['password_hash', 'allow_download']);
        });
    }
};
