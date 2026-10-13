<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_devices', function (Blueprint $table) {
            $table->string('device_id', 128)->primary();
            $table->string('name');
            $table->string('zone')->nullable();
            $table->string('driver');
            $table->boolean('available')->nullable();
            $table->dateTime('observed_at')->nullable();
            $table->dateTime('received_at')->nullable();
        });
        Schema::create('availability_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 128);
            $table->foreign('device_id')->references('device_id')->on('availability_devices');
            $table->string('name');
            $table->string('zone')->nullable();
            $table->string('driver');
            $table->boolean('available');
            $table->dateTime('observed_at');
            $table->dateTime('received_at');
            $table->unique(['device_id', 'observed_at']);
            $table->index('observed_at');
        });
        Schema::create('availability_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->unique()->constrained('availability_snapshots');
            $table->string('device_id', 128);
            $table->foreign('device_id')->references('device_id')->on('availability_devices');
            $table->boolean('from_available');
            $table->boolean('to_available');
            $table->dateTime('previous_observed_at');
            $table->dateTime('observed_at');
            $table->index(['device_id', 'observed_at']);
            $table->index('observed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_transitions');
        Schema::dropIfExists('availability_snapshots');
        Schema::dropIfExists('availability_devices');
    }
};
