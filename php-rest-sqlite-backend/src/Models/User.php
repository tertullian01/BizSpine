<?php

namespace App\Models;

use Firebase\JWT\JWT;

class User extends BaseModel
{
    protected static string $tableName = 'users';

    public ?string $email = null;
    public ?string $password_hash = null;
    public ?string $display_name = null;
    public ?int $is_email_verified = null;
    public ?string $created_at = null;
    public ?string $last_login = null;
    public ?string $role = null;
    public ?string $reset_token = null;
    public ?string $reset_expires_at = null;

    public static function findByEmail(string $email): ?User
    {
        return self::fetchOne('SELECT * FROM users WHERE email = :email', [':email' => $email]);
    }

    public static function register(string $email, string $password): User
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception('Invalid email format.');
        }

        if (strlen($password) < 8) {
            throw new \Exception('Password must be at least 8 characters.');
        }

        $existingUser = self::findByEmail($email);
        if ($existingUser) {
            throw new \Exception('User already exists');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $user = new User([
            'email' => $email,
            'password_hash' => $hash,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $user->save();

        return $user;
    }

    public function login(string $password, array $config): ?string
    {
        if (!password_verify($password, $this->password_hash)) {
            return null;
        }

        $now = time();
        $payload = [
            'iss' => $config['jwt']['issuer'] ?? 'local',
            'iat' => $now,
            'exp' => $now + ($config['jwt']['access_exp'] ?? 900),
            'sub' => (string)$this->id,
            'role' => $this->role ?? 'customer',
        ];
        return JWT::encode($payload, $config['jwt']['secret'] ?? 'dev', 'HS256');
    }
}
