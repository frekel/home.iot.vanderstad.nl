<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toilet_devices', function (Blueprint $table) {
            $table->string('device_id', 64)->primary();
            $table->string('tank', 8)->nullable();
            $table->boolean('float_up')->nullable();
            $table->unsignedBigInteger('flushes_total')->default(0);
            $table->dateTime('low_since')->nullable();
            $table->dateTime('observed_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('last_flush_at')->nullable();
        });

        Schema::create('toilet_events', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 64);
            $table->string('event_id', 128);
            $table->string('event', 16);
            $table->unsignedBigInteger('flushes_total');
            $table->dateTime('occurred_at');
            $table->dateTime('received_at');
            $table->foreign('device_id')->references('device_id')->on('toilet_devices');
            $table->unique(['device_id', 'event_id']);
            $table->index(['device_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toilet_events');
        Schema::dropIfExists('toilet_devices');
    }
};
