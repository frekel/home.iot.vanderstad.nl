<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('furniture_layouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedInteger('model_revision')->nullable();
            $table->string('status')->default('ready');
            $table->json('overrides');
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });
        DB::table('furniture_layouts')->insert(['id' => 1, 'overrides' => '{}', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('furniture_layouts');
    }
};
