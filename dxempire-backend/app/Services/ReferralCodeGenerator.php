<?php

namespace App\Services;

use App\Models\Dealer;

class ReferralCodeGenerator
{
    private const CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I — avoids look-alike codes

    public static function generate(): string
    {
        do {
            $code = 'DX' . collect(range(1, 4))
                ->map(fn () => self::CHARS[random_int(0, strlen(self::CHARS) - 1)])
                ->implode('');
        } while (Dealer::where('referral_code', $code)->exists());

        return $code;
    }
}
