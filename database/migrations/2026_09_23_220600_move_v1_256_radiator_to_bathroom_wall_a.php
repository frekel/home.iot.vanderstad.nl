<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty.
        //
        // This migration was created while the bathroom's visual wall orientation
        // was still interpreted incorrectly. It must never move furniture on a
        // database where it has not yet run. The normalized furniture migration
        // that follows copies the current production values exactly as they are.
    }

    public function down(): void
    {
        // Intentionally empty for the same reason: there is no safe historical
        // radiator position to restore from this superseded migration.
    }
};
