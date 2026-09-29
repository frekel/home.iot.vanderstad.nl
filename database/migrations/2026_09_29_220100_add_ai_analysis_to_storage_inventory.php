<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_box_photos', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('storage_box_id')->index();
        });

        Schema::create('storage_box_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_box_id')->constrained()->cascadeOnDelete();
            $table->uuid('photo_batch_id')->nullable();
            $table->string('model');
            $table->string('response_id')->nullable();
            $table->json('result');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['storage_box_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_box_analyses');

        Schema::table('storage_box_photos', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn('batch_id');
        });
    }
};
