<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Times sent by the apps, converted to the app's timezone before they're saved or compared.
 * Laravel stores a date's wall-clock time without converting it, so an app sending UTC
 * ("...T13:11:17Z") would otherwise be read back three hours early in Africa/Kampala.
 */
final class ClientTime
{
    public static function parse(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->setTimezone(config('app.timezone'));
    }
}
