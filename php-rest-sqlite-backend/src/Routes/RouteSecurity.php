<?php

declare(strict_types=1);

namespace App\Routes;

use App\Middleware\PrivilegedRoleMiddleware;
use App\Services\Config;

/**
 * Central place to build role-gated middleware without duplicating JWT secret resolution.
 */
final class RouteSecurity
{
    public static function jwtSecret(): string
    {
        return (string) (Config::getInstance()->get('jwt.secret') ?: 'default_secret');
    }

    public static function requireAdmin(): PrivilegedRoleMiddleware
    {
        return new PrivilegedRoleMiddleware(self::jwtSecret(), ['admin']);
    }

    /** Admin or employee (back-office staff). */
    public static function requireStaff(): PrivilegedRoleMiddleware
    {
        return new PrivilegedRoleMiddleware(self::jwtSecret(), ['admin', 'employee']);
    }
}
