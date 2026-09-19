<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_shares', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('shareable_type', 30);
            $table->unsignedBigInteger('shareable_id');
            $table->string('permission', 20);
            $table->timestamps();

            $table->unique(['recipient_id', 'shareable_type', 'shareable_id'], 'internal_shares_recipient_item_unique');
            $table->index(['owner_id', 'created_at']);
            $table->index(['shareable_type', 'shareable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_shares');
    }
};
