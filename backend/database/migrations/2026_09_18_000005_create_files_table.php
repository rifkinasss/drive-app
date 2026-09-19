<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->string('original_name', 255);
            $table->string('stored_name', 255)->unique();
            $table->string('extension', 32)->nullable();
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum', 128)->nullable();
            $table->boolean('is_starred')->default(false);
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();
            $table->index('owner_id');
            $table->index('folder_id');
            $table->index('trashed_at');
            $table->index('is_starred');
            $table->index(['owner_id', 'folder_id']);
        });

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX files_owner_folder_name_unique ON files (owner_id, COALESCE(folder_id, 0), lower(original_name)) WHERE trashed_at IS NULL');
        }
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS files_owner_folder_name_unique');
        }

        Schema::dropIfExists('files');
    }
};
