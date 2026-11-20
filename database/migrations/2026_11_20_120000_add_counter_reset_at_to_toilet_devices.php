<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('toilet_devices', function (Blueprint $table) {
            // A counting-period boundary; the ESP32's raw counter stays
            // monotonic, preserving event IDs and queued retry safety.
            $table->dateTime('counter_reset_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('toilet_devices', function (Blueprint $table) {
            $table->dropColumn('counter_reset_at');
        });
    }
};
