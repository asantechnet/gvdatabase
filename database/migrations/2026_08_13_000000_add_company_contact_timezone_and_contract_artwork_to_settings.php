<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('settings', 'business_licence_document')) {
            $paths = \App\Models\Setting::withTrashed()
                ->whereNotNull('business_licence_document')
                ->pluck('business_licence_document')
                ->filter()
                ->all();

            if ($paths !== []) {
                Storage::disk('public')->delete($paths);
            }
        }

        $hasLicenceDocument = Schema::hasColumn('settings', 'business_licence_document');

        Schema::table('settings', function (Blueprint $table) use ($hasLicenceDocument) {
            $table->string('company_email')->nullable()->after('website_address');
            $table->string('timezone', 64)->default('Asia/Kabul')->after('company_email');
            $table->string('contract_letterhead')->nullable()->after('company_signature');
            $table->string('contract_cover_design')->nullable()->after('contract_letterhead');

            if ($hasLicenceDocument) {
                $table->dropColumn('business_licence_document');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('business_licence_document')->nullable()->after('business_licence');
            $table->dropColumn([
                'company_email',
                'timezone',
                'contract_letterhead',
                'contract_cover_design',
            ]);
        });
    }
};
