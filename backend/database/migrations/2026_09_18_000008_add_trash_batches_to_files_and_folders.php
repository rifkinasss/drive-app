<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->uuid('trash_batch_id')->nullable()->index()->after('trashed_at');
        });

        Schema::table('folders', function (Blueprint $table): void {
            $table->uuid('trash_batch_id')->nullable()->index()->after('trashed_at');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn('trash_batch_id');
        });

        Schema::table('folders', function (Blueprint $table): void {
            $table->dropColumn('trash_batch_id');
        });
    }
};
