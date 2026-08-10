<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('customer_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->date('deadline');
            $table->timestamps();

            $table->unique(['customer_id', 'service_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_service');
    }
};
