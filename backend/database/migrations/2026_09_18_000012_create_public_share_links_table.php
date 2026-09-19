<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_share_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('shareable_type', 30);
            $table->unsignedBigInteger('shareable_id');
            $table->string('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->boolean('enabled')->default(true)->index();
            $table->string('permission', 20)->default('viewer');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['shareable_type', 'shareable_id']);
            $table->index(['owner_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_share_links');
    }
};
