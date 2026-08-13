<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('geofence_radius_meters')->default(100)->after('longitude');
            $table->boolean('geofence_enabled')->default(false)->after('geofence_radius_meters');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('check_in_latitude', 10, 7)->nullable()->after('checked_out_at');
            $table->decimal('check_in_longitude', 10, 7)->nullable()->after('check_in_latitude');
            $table->unsignedInteger('check_in_accuracy_meters')->nullable()->after('check_in_longitude');
            $table->unsignedInteger('check_in_distance_meters')->nullable()->after('check_in_accuracy_meters');
            $table->string('check_in_source', 30)->nullable()->after('check_in_distance_meters');
            $table->decimal('check_out_latitude', 10, 7)->nullable()->after('check_in_source');
            $table->decimal('check_out_longitude', 10, 7)->nullable()->after('check_out_latitude');
            $table->unsignedInteger('check_out_accuracy_meters')->nullable()->after('check_out_longitude');
            $table->unsignedInteger('check_out_distance_meters')->nullable()->after('check_out_accuracy_meters');
            $table->string('check_out_source', 30)->nullable()->after('check_out_distance_meters');
            $table->timestamp('geofence_exit_detected_at')->nullable()->after('check_out_source');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'check_in_latitude', 'check_in_longitude', 'check_in_accuracy_meters',
                'check_in_distance_meters', 'check_in_source', 'check_out_latitude',
                'check_out_longitude', 'check_out_accuracy_meters', 'check_out_distance_meters',
                'check_out_source', 'geofence_exit_detected_at',
            ]);
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geofence_radius_meters', 'geofence_enabled']);
        });
    }
};
