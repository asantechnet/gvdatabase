<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the lead concept from the schema.
 *
 * Visitors convert straight to an applicant; there is no intermediate
 * qualification pipeline. The squashed CRM migration no longer creates the
 * table, so this is only meaningful for databases migrated before the removal,
 * and every step is guarded so a fresh database runs cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('leads');
    }

    public function down(): void
    {
        // Retired entities and discarded data are not reconstructed.
    }
};
