<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table): void {
            $table->boolean('is_starred')->default(false)->after('trash_batch_id');
            $table->index('is_starred');
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table): void {
            $table->dropIndex(['is_starred']);
            $table->dropColumn('is_starred');
        });
    }
};
