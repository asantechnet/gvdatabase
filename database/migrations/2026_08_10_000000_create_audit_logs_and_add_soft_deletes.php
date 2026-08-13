<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    private array $tables = [
        'users',
        'attendances',
        'boards',
        'comments',
        'contracts',
        'contract_templates',
        'currencies',
        'customers',
        'customer_service',
        'expenses',
        'incomes',
        'invoices',
        'permissions',
        'positions',
        'salaries',
        'services',
        'settings',
        'stocks',
        'subtasks',
        'tags',
        'tasks',
        'visitors',
        'worthy_employees',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn(Blueprint $blueprint) => $blueprint->softDeletes());
            }
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 20);
            $table->string('table_name');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_columns')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['table_name', 'event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn(Blueprint $blueprint) => $blueprint->dropSoftDeletes());
            }
        }
    }
};
