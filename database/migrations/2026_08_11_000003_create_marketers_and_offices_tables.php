<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30);
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        $marketerNames = DB::table('customers')->whereNotNull('marketer')->pluck('marketer')
            ->merge(DB::table('visitors')->whereNotNull('marketer')->pluck('marketer'))
            ->map(fn ($name) => trim((string) $name))->filter()->unique();
        foreach ($marketerNames as $name) {
            DB::table('marketers')->insert([
                'name' => $name,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('stocks')->whereNotNull('office')->pluck('office')->map(fn ($name) => trim((string) $name))->filter()->unique() as $name) {
            $code = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), 0, 30));
            DB::table('offices')->insert([
                'name' => $name,
                'code' => trim($code, '-') ?: 'OFF-'.strtoupper(substr(sha1($name), 0, 8)),
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
        Schema::dropIfExists('marketers');
    }
};
