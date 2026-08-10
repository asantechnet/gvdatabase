<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('assigned_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['position_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_user');
    }
};
