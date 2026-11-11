<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Outgoing email is disabled in Settings. This is a configuration error, not a
 * transport failure, and every mail-producing action returns the same code.
 */
class EmailDeliveryNotConfiguredException extends Exception
{
    public function __construct()
    {
        parent::__construct('Email delivery is not set up. Enable it in Settings → Email delivery.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'business.email_delivery_not_configured',
            'message' => $this->getMessage(),
        ], 422);
    }
}
