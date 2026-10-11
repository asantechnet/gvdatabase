<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the permission grants that belonged to the retired invoicing and
 * payment-detail surfaces. Assignment rows cascade with the permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->whereIn('name', [
            'view_invoices',
            'create_invoices',
            'edit_invoices',
            'void_invoices',
            'delete_invoices',
            'view_payment_details',
            'create_payment_details',
            'edit_payment_details',
            'delete_payment_details',
        ])->delete();
    }

    public function down(): void
    {
        // Retired endpoints and grants are intentionally not restored.
    }
};
