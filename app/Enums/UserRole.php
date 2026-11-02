<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Cashier = 'cashier';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Cashier => 'Kasir',
        };
    }

    /**
     * Route name a user of this role lands on after logging in.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Owner => 'owner.dashboard',
            self::Cashier => 'cashier.pos',
        };
    }
}
