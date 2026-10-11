<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company email delivery is optional. When disabled the company SMTP values are
 * ignored entirely and Laravel falls back to the environment mailer, so an
 * install with no mail configuration still saves (and runs) normally.
 *
 * Defaults to `true` so an existing install keeps using its configured SMTP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('email_delivery_enabled')->default(true)->after('mail_reply_to');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('email_delivery_enabled');
        });
    }
};
