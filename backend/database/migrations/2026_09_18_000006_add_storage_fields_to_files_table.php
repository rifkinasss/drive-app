<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->string('disk', 64)->default('cloud')->after('stored_name');
            $table->string('path', 1024)->nullable()->after('disk');
            $table->index(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropIndex(['disk', 'path']);
            $table->dropColumn(['disk', 'path']);
        });
    }
};
