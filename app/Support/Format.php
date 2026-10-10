<?php

namespace App\Support;

final class Format
{
    /** "45 min", "3 h 10 min", "2 d 4 h". */
    public static function minutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }
        if ($minutes < 60) {
            return $minutes.' min';
        }
        if ($minutes < 60 * 24) {
            return intdiv($minutes, 60).' h '.($minutes % 60).' min';
        }

        return intdiv($minutes, 60 * 24).' d '.intdiv($minutes % (60 * 24), 60).' h';
    }

    public static function label(?string $value): string
    {
        return $value === null ? '—' : ucfirst(str_replace('_', ' ', $value));
    }
}
