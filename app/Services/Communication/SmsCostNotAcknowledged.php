<?php

namespace App\Services\Communication;

use RuntimeException;

/**
 * An SMS send whose cost wasn't confirmed, or was confirmed at an amount that no longer
 * matches (the audience, numbers or text changed since). Carries the current cost.
 */
class SmsCostNotAcknowledged extends RuntimeException
{
    public function __construct(public readonly int $currentCost)
    {
        parent::__construct("The SMS cost (UGX {$currentCost}) must be acknowledged before sending.");
    }
}
