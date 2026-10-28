<?php

namespace App\Support;

class Phone
{
    /* Turns whatever the user typed ("+63 917 123 4567", "0917-123-4567",
       "9171234567") into the canonical 09XXXXXXXXX form. Returns null when
       there is nothing usable, so `nullable` validation still works.

       Anything that is not a plausible PH mobile number is returned as the
       digits we found; the regex rule then rejects it with a clear message. */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        return $digits;
    }
}
