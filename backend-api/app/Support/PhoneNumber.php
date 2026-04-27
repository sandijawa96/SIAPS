<?php

namespace App\Support;

final class PhoneNumber
{
    private const WA_REGEX = '/^628[0-9]{7,12}$/';

    /**
     * Normalize Indonesian phone number for WhatsApp canonical format: 628xxxxxxxxx
     */
    public static function normalizeIndonesianWa(?string $raw): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $raw) ?? '';
        if ($digits === '') {
            return '';
        }

        // Convert international dial prefix.
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Handle common user inputs: 08..., 8..., +62..., 62...
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62' . $digits;
        } elseif (!str_starts_with($digits, '62')) {
            return '';
        }

        // Defensive fix for malformed "6208..." style.
        if (str_starts_with($digits, '6208')) {
            $digits = '628' . substr($digits, 4);
        }

        if (!preg_match(self::WA_REGEX, $digits)) {
            return '';
        }

        return $digits;
    }

    /**
     * Convert canonical 628... to local UI representation 8...
     */
    public static function toLocalSubscriber(?string $canonical): string
    {
        $normalized = self::normalizeIndonesianWa($canonical);
        if ($normalized === '') {
            return preg_replace('/[^0-9]/', '', (string) $canonical) ?? '';
        }

        return substr($normalized, 2);
    }
}

