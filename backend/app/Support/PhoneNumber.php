<?php

namespace App\Support;

final class PhoneNumber
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/[\s().-]+/', '', trim($value));

        return $normalized === '' ? null : $normalized;
    }
}
