<?php

use App\Support\AuditRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('subject_type', 80)->nullable()->after('auditable_id');
            $table->string('subject_label')->nullable()->after('subject_type');
            $table->foreignId('audited_by')->nullable()->after('reviewed_by')->constrained('users')->nullOnDelete();
            $table->timestamp('audited_at')->nullable()->after('reviewed_at');
            $table->index(['audited_at', 'created_at']);
            $table->index(['audited_by', 'audited_at']);
            $table->index(['subject_type', 'created_at']);
        });

        DB::table('audit_logs')->whereNotNull('reviewed_at')->update([
            'audited_by' => DB::raw('reviewed_by'),
            'audited_at' => DB::raw('reviewed_at'),
        ]);

        Schema::create('audit_resource_settings', function (Blueprint $table) {
            $table->id();
            $table->string('resource_key', 80)->unique();
            $table->boolean('enabled')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['enabled', 'resource_key']);
        });

        $now = now();
        DB::table('permissions')->updateOrInsert(['name' => 'manage_audit_scope'], [
            'category' => 'core',
            'guard_name' => 'web',
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (array_keys(AuditRegistry::resources()) as $resourceKey) {
            DB::table('audit_resource_settings')->insertOrIgnore([
                'resource_key' => $resourceKey,
                'enabled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('audit_logs')->select(['id', 'table_name', 'auditable_type', 'auditable_id'])
            ->orderBy('id')
            ->chunkById(100, function ($logs) {
                foreach ($logs as $log) {
                    $resourceKey = AuditRegistry::keyFor($log->auditable_type);
                    if ($resourceKey === 'other') {
                        $resourceKey = match ($log->table_name) {
                            'account_deactivation_requests' => 'account_deactivations',
                            'audit_resource_settings' => 'audit_settings',
                            default => 'other',
                        };
                    }

                    $label = '';
                    $class = $log->auditable_type;
                    if (class_exists($class) && is_subclass_of($class, Model::class)) {
                        $query = $class::query();
                        if (in_array(SoftDeletes::class, class_uses_recursive($class), true)) {
                            $query->withTrashed();
                        }
                        $model = $query->find($log->auditable_id);
                        $label = $model ? AuditRegistry::subjectLabel($model) : '';
                    }

                    DB::table('audit_logs')->where('id', $log->id)->update([
                        'subject_type' => $resourceKey,
                        'subject_label' => $label,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_resource_settings');
        DB::table('permissions')->where('name', 'manage_audit_scope')->delete();

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['audited_at', 'created_at']);
            $table->dropIndex(['audited_by', 'audited_at']);
            $table->dropIndex(['subject_type', 'created_at']);
            $table->dropConstrainedForeignId('audited_by');
            $table->dropColumn(['subject_type', 'subject_label', 'audited_at']);
        });
    }
};
