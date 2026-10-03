<?php

namespace App\Enums;

enum UserStatus: string
{
    case WAITING = 'waiting';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Get all status values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get label for user status.
     */
    public function label(): string
    {
        return match ($this) {
            self::WAITING => 'Kutilmoqda',
            self::APPROVED => 'Tasdiqlangan',
            self::REJECTED => 'Rad etilgan',
        };
    }
}
