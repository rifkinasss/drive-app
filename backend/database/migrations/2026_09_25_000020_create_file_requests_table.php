<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('folder_id')->constrained('folders')->restrictOnDelete();
            $table->string('title', 160);
            $table->string('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['owner_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_requests');
    }
};
