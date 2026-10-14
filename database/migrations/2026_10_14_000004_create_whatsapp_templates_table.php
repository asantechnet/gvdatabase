<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable WhatsApp message templates.
 *
 * A template belongs to one audience — the kind of record it is written for
 * (visitor, applicant, employee). Its body is plain text only, because that is
 * everything WhatsApp renders: line breaks and the *bold* / _italic_ /
 * ~strikethrough~ / ```monospace``` markers. Record data is filled in through
 * `{{token}}` placeholders declared in App\Services\Whatsapp\WhatsappPlaceholderRegistry.
 *
 * Delivery is a `wa.me` deep link built on the client, so nothing in this table
 * is provider-specific. Adding a server-side sender later needs an outbound log
 * and a delivery service, not a change here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            // One of App\Support\WhatsappAudience::ALL.
            $table->string('audience', 32);
            $table->string('title', 150);
            $table->json('labels')->nullable();
            $table->text('message');
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            // Every read is scoped to one audience and its active templates,
            // ordered by sort_order, so one composite index covers them all.
            $table->index(['audience', 'active', 'sort_order'], 'whatsapp_templates_audience_active_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
