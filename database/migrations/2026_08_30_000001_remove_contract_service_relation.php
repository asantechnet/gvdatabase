<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('contract_service');
    }

    public function down(): void
    {
        // The contract-service relation has intentionally been removed.
        // Services are inferred from customer_service assignments.
    }
};
