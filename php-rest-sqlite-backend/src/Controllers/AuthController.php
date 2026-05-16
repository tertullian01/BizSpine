<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Services\Database;
use App\Services\Validator;
use App\Services\Config;
use App\Services\EmailService;
use Firebase\JWT\JWT;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class AuthController extends ApiController
{
    private array $config;
    private PDO $db;
    private Validator $validator;
    private EmailService $emailService;

    public function __construct(array $config = [], ?EmailService $emailService = null)
    {
        $this->config = $config;
        $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
        $this->db = Database::get($dbPath);
        $this->validator = new Validator();
        $this->emailService = $emailService ?? new EmailService($this->db);
    }

    public function register(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        try {
            $this->validator->validate($body, [
                'email' => v::notEmpty()->email()->setName('Email'),
                'password' => v::notEmpty()->length(8, null)->setName('Password'),
            ]);
        } catch (ValidationException $e) {
            return $this->error($response, $e->getFirstError(), 400);
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 400);
        }

        $email = trim($body['email']);
        $password = trim($body['password']);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('INSERT INTO users (email, password_hash, created_at) VALUES (:e,:p,datetime("now"))');
        try {
            $stmt->execute([':e' => $email, ':p' => $hash]);
            return $this->success($response, ['message' => 'User created', 'email' => $email], 201);
        } catch (\PDOException $ex) {
            if ($ex->getCode() == '23000') {
                return $this->error($response, 'User already exists', 409);
            }
            throw $ex;
        }
    }

    public function login(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $email = $body['email'] ?? '';
        $password = $body['password'] ?? '';

        // Basic validation, can be enhanced
        if (empty($email) || empty($password)) {
            return $this->error($response, 'Email and password are required', 400);
        }

        $stmt = $this->db->prepare('SELECT id, password_hash, role FROM users WHERE email = :e');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return $this->error($response, 'Invalid credentials', 401);
        }

        if (!password_verify($password, $user['password_hash'])) {
            return $this->error($response, 'Invalid credentials', 401);
        }

        $now = time();
        $payload = [
            'iss' => $this->config['jwt']['issuer'] ?? 'local',
            'iat' => $now,
            'exp' => $now + ($this->config['jwt']['access_exp'] ?? 900),
            'sub' => (string) $user['id'],
            'role' => $user['role'] ?? 'customer',
        ];
        $secret = (string)(Config::getInstance()->get('jwt.secret') ?: 'default_secret');
        $token = JWT::encode($payload, $secret, 'HS256');

        return $this->success($response, ['access_token' => $token, 'role' => $user['role'] ?? 'customer']);
    }

    public function logout(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        if (!$userId) {
            throw new ValidationException('Missing user_id');
        }

        // Mark refresh tokens as revoked for this user (optional implementation)
        return $this->success($response, ['message' => 'Logged out successfully']);
    }

    public function refresh(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        if (!$userId) {
            throw new ValidationException('Missing user_id');
        }
        $stmt = $this->db->prepare('SELECT role FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $roleFromDb = $stmt->fetchColumn();
        $role = is_string($roleFromDb) && $roleFromDb !== '' ? $roleFromDb : 'customer';

        // Issue new access token
        $now = time();
        $payload = [
            'iss' => $this->config['jwt']['issuer'] ?? 'local',
            'iat' => $now,
            'exp' => $now + ($this->config['jwt']['access_exp'] ?? 900),
            'sub' => (string) $userId,
            'role' => $role,
        ];
        $secret = (string)(Config::getInstance()->get('jwt.secret') ?: 'default_secret');
        $token = JWT::encode($payload, $secret, 'HS256');
        return $this->success($response, ['access_token' => $token]);
    }

    public function oauthRedirect(Request $request, Response $response): Response
    {
        return $this->error($response, 'Not implemented', 501);
    }

    public function oauthCallback(Request $request, Response $response): Response
    {
        return $this->error($response, 'Not implemented', 501);
    }

    public function forgotPassword(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $this->validator->validate($body, [
            'email' => v::notEmpty()->email()->setName('Email'),
        ]);
        $email = trim($body['email']);

        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :e');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            // For security, don't reveal if email exists
            return $this->success($response, ['message' => 'If the email exists, a reset link has been sent.']);
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        $stmt = $this->db->prepare('UPDATE users SET reset_token = :t, reset_expires_at = :e WHERE id = :id');
        $stmt->execute([':t' => $token, ':e' => $expiresAt, ':id' => $user['id']]);

        // Send email
        $emailSent = $this->emailService->sendPasswordResetEmail($email, $token);

        if (!$emailSent) {
            // Log error, but still return success for security
            return $this->success($response, ['message' => 'If the email exists, a reset link has been sent.']);
        }

        return $this->success($response, ['message' => 'If the email exists, a reset link has been sent.']);
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        try {
            $this->validator->validate($body, [
                'token' => v::notEmpty()->setName('Token'),
                'password' => v::notEmpty()->length(8, null)->setName('Password'),
            ]);
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 400);
        }

        $token = trim($body['token']);
        $password = trim($body['password']);

        $stmt = $this->db->prepare('SELECT id, reset_expires_at FROM users WHERE reset_token = :t');
        $stmt->execute([':t' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return $this->error($response, 'Invalid or expired token', 400);
        }

        if (strtotime($user['reset_expires_at']) < time()) {
            return $this->error($response, 'Invalid or expired token', 400);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare('UPDATE users SET password_hash = :p, reset_token = NULL, reset_expires_at = NULL WHERE id = :id');
        $stmt->execute([':p' => $hash, ':id' => $user['id']]);

        return $this->success($response, ['message' => 'Password reset successfully']);
    }

    public function changePassword(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        $body = $request->getParsedBody();

        try {
            $this->validator->validate($body, [
                'current_password' => v::notEmpty()->setName('Current Password'),
                'new_password' => v::notEmpty()->length(8, null)->setName('New Password'),
            ]);
        } catch (\Exception $e) {
            return $this->error($response, $e->getMessage(), 400);
        }

        $currentPassword = trim($body['current_password']);
        $newPassword = trim($body['new_password']);

        // Get user from database
        $stmt = $this->db->prepare('SELECT id, password_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }

        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return $this->error($response, 'Current password is incorrect', 400);
        }

        // Update password
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :p WHERE id = :id');
        $stmt->execute([':p' => $hash, ':id' => $userId]);

        return $this->success($response, ['message' => 'Password changed successfully']);
    }

    public function me(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');

        if (!$userId) {
            return $this->error($response, 'Unauthorized', 401);
        }

        $stmt = $this->db->prepare('SELECT id, email, display_name, role, created_at, first_name, last_name, country, street_line_1, street_line_2, city, state, postal_code, mobile_number, whatsapp_number, instagram_link, facebook_link, is_email_verified, last_login FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }

        return $this->success($response, $user);
    }
}
