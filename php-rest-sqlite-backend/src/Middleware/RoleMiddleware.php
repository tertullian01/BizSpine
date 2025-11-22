<?php
namespace App\Middleware;

class RoleMiddleware
{
    public static function checkRole(object $token, array $allowedRoles): bool
    {
        $userRole = $token->role ?? 'customer';
        return in_array($userRole, $allowedRoles);
    }
}