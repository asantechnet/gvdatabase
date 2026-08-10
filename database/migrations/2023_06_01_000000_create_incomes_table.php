<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->index();
            $table->string('type');
            $table->text('description');
            $table->decimal('amount', 12, 2);
            $table->integer('quantity')->default(1);
            $table->string('currency', 3)->default('USD');
            $table->decimal('rate_to_base', 12, 6)->default(1.0);
            $table->decimal('total_amount', 12, 2);
            $table->date('date');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('incomes');
    }
};
