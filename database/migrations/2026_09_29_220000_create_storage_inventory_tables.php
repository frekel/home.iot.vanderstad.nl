<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storage_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('location', 64);
            $table->unsignedInteger('number');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['location', 'number']);
            $table->index(['location', 'updated_at']);
        });

        Schema::create('storage_box_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_box_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['storage_box_id', 'name']);
        });

        Schema::create('storage_box_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storage_box_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->index(['storage_box_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_box_photos');
        Schema::dropIfExists('storage_box_items');
        Schema::dropIfExists('storage_boxes');
    }
};
