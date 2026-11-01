<?php

use App\Support\BuiltInService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Normalize visitor services to the four built-in keys.
 *
 * Visitor service used to be free text, so rows can carry a display label
 * ("Scholarship") instead of a key. Only exact, known labels are rewritten;
 * anything else is left untouched and logged for review rather than coerced to
 * a service the visitor never asked about.
 *
 * `legacy_service` keeps the original text so `down()` restores it exactly.
 */
return new class extends Migration
{
    /** Known free-text values mapped to canonical keys. */
    private const MAP = [
        'scholarship' => BuiltInService::SCHOLARSHIP,
        'private' => BuiltInService::PRIVATE,
        'test offer' => BuiltInService::TEST_OFFER,
        'test_offer' => BuiltInService::TEST_OFFER,
        'testoffer' => BuiltInService::TEST_OFFER,
        'academy' => BuiltInService::ACADEMY,
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('visitors', 'legacy_service')) {
            Schema::table('visitors', function (Blueprint $table): void {
                $table->string('legacy_service')->nullable()->after('service');
            });
        }

        // Record every current value before rewriting anything.
        DB::table('visitors')->whereNull('legacy_service')->update(['legacy_service' => DB::raw('service')]);

        $unmapped = [];

        DB::table('visitors')->select('id', 'service')->orderBy('id')->chunkById(200, function ($rows) use (&$unmapped): void {
            foreach ($rows as $row) {
                $normalized = preg_replace('/[\s-]+/', ' ', strtolower(trim((string) $row->service)));
                $target = self::MAP[$normalized] ?? null;

                if ($target !== null && $target !== $row->service) {
                    DB::table('visitors')->where('id', $row->id)->update(['service' => $target]);
                } elseif (! in_array($row->service, BuiltInService::ALL, true)) {
                    $unmapped[$row->service] = true;
                }
            }
        });

        if ($unmapped !== []) {
            Log::warning('Visitor services left unchanged because they are not a built-in service.', [
                'values' => array_keys($unmapped),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('visitors', 'legacy_service')) {
            return;
        }

        // Restore the exact original text, including its original casing.
        DB::table('visitors')->whereNotNull('legacy_service')->update(['service' => DB::raw('legacy_service')]);

        Schema::table('visitors', function (Blueprint $table): void {
            $table->dropColumn('legacy_service');
        });
    }
};
