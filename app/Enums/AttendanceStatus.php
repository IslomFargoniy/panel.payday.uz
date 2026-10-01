<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case IN = 'checkIn';
    case OUT = 'checkOut';

    public const IN_VALUES = ['checkIn', 'CheckIn', 'keldi', 'entered', 'breakIn'];
    public const OUT_VALUES = ['checkOut', 'CheckOut', 'ketdi', 'exited', 'breakOut'];
    public const ALL_VALUES = [
        'checkIn', 'CheckIn', 'keldi', 'entered', 'breakIn',
        'checkOut', 'CheckOut', 'ketdi', 'exited', 'breakOut',
    ];

    /**
     * Normalize incoming raw status to canonical AttendanceStatus value or null.
     */
    public static function normalize(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $trimmed = trim($status);
        if ($trimmed === '' || strtolower($trimmed) === 'undefined' || strtolower($trimmed) === 'null') {
            return null;
        }

        if (in_array($trimmed, self::IN_VALUES, true) || in_array(strtolower($trimmed), ['checkin', 'keldi', 'entered', 'breakin'], true)) {
            return self::IN->value;
        }

        if (in_array($trimmed, self::OUT_VALUES, true) || in_array(strtolower($trimmed), ['checkout', 'ketdi', 'exited', 'breakout'], true)) {
            return self::OUT->value;
        }

        return null;
    }

    /**
     * Get human readable label for status.
     */
    public static function label(?string $status): string
    {
        $normalized = self::normalize($status);
        return $normalized === self::IN->value ? 'Keldi' : 'Ketdi';
    }

    /**
     * Array of values representing checkIn in queries.
     */
    public static function inValues(): array
    {
        return self::IN_VALUES;
    }

    /**
     * Array of values representing checkOut in queries.
     */
    public static function outValues(): array
    {
        return self::OUT_VALUES;
    }

    /**
     * Array of all attendance status values.
     */
    public static function allValues(): array
    {
        return self::ALL_VALUES;
    }

    /**
     * Comma-separated quoted string for raw SQL IN statements for checkIn.
     */
    public static function inSqlList(): string
    {
        return "'" . implode("', '", self::IN_VALUES) . "'";
    }

    /**
     * Comma-separated quoted string for raw SQL IN statements for checkOut.
     */
    public static function outSqlList(): string
    {
        return "'" . implode("', '", self::OUT_VALUES) . "'";
    }

    /**
     * Comma-separated quoted string for raw SQL IN statements for all statuses.
     */
    public static function allSqlList(): string
    {
        return "'" . implode("', '", self::ALL_VALUES) . "'";
    }
}
