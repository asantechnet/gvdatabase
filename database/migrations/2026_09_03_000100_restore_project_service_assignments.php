<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restore the explicit delivery scope that was removed when projects were
     * temporarily modelled as inheriting every service from their customer.
     */
    public function up(): void
    {
        Schema::create('project_service', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->primary(['project_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_service');
    }
};
