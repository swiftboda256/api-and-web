<?php

namespace App\Services\Payment\Constants;

/**
 * What the gateway last answered to a status check (transactions.last_gateway_status).
 * Informational only: the last four all leave the transaction pending.
 */
enum GatewayStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Indeterminate = 'indeterminate';
    case Unreachable = 'unreachable';
    case NotFound = 'not_found';
    case Error = 'error';
}
