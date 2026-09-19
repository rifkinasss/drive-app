<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->string('name', 255);
            $table->timestamp('trashed_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'parent_id']);
            $table->index('trashed_at');
        });

        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX folders_owner_parent_name_unique ON folders (owner_id, COALESCE(parent_id, 0), lower(name)) WHERE trashed_at IS NULL');
        }
    }

    public function down(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS folders_owner_parent_name_unique');
        }

        Schema::dropIfExists('folders');
    }
};
