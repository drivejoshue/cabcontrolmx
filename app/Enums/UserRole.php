<?php

namespace App\Enums;

enum UserRole: string
{
    case SYSADMIN   = 'sysadmin';
    case ADMIN      = 'admin';
    case DISPATCHER = 'dispatcher';
    case DRIVER     = 'driver';
    case NONE       = 'none';

    public static function fromDb(?string $role): self
    {
        return match ($role) {
            'sysadmin'   => self::SYSADMIN,
            'admin'      => self::ADMIN,
            'dispatcher' => self::DISPATCHER,
            'driver'     => self::DRIVER,
            default      => self::NONE,
        };
    }
}
