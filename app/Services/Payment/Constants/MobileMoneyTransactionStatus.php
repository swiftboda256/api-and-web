<?php

namespace App\Services\Payment\Constants;

enum MobileMoneyTransactionStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Indeterminate = 'indeterminate';
}
