<?php

namespace App\Controllers;

use App\Models\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EmployeeController extends ApiController
{
    public function getAll(Request $request, Response $response): Response
    {
        // Fetch all users with role 'employee' or 'admin'
        $sql = "SELECT * FROM users WHERE role IN ('employee', 'admin') ORDER BY display_name";
        $employees = User::fetchAll($sql);

        // Remove sensitive data
        foreach ($employees as $employee) {
            unset($employee->password_hash);
            unset($employee->reset_token);
        }

        return $this->success($response, $employees);
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        if (empty($body['email']) || empty($body['password']) || empty($body['display_name'])) {
            return $this->error($response, 'Email, password, and display name are required', 400);
        }

        try {
            // Check if user exists
            if (User::findByEmail($body['email'])) {
                return $this->error($response, 'User already exists', 400);
            }

            $user = new User([
                'email' => $body['email'],
                'password_hash' => password_hash($body['password'], PASSWORD_DEFAULT),
                'display_name' => $body['display_name'],
                'role' => $body['role'] ?? 'employee', // Default to employee
                'is_email_verified' => 1, // Auto-verify employees
                'created_at' => date('Y-m-d H:i:s'),
                'first_name' => $body['first_name'] ?? null,
                'last_name' => $body['last_name'] ?? null,
                'country' => $body['country'] ?? null,
                'street_line_1' => $body['street_line_1'] ?? null,
                'street_line_2' => $body['street_line_2'] ?? null,
                'city' => $body['city'] ?? null,
                'state' => $body['state'] ?? null,
                'postal_code' => $body['postal_code'] ?? null,
                'mobile_number' => $body['mobile_number'] ?? null,
                'whatsapp_number' => $body['whatsapp_number'] ?? null,
                'instagram_link' => $body['instagram_link'] ?? null,
                'facebook_link' => $body['facebook_link'] ?? null,
            ]);
            $user->save();

            unset($user->password_hash);
            return $this->success($response, $user, 201);
        } catch (\Exception $e) {
            return $this->error($response, 'Error creating employee: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $user = User::find($id);

        if (!$user) {
            return $this->error($response, 'Employee not found', 404);
        }

        $body = $request->getParsedBody();

        try {
            if (isset($body['display_name']))
                $user->display_name = $body['display_name'];
            if (isset($body['email'])) {
                $existing = User::findByEmail($body['email']);
                if ($existing && $existing->id !== $id) {
                    return $this->error($response, 'Email already exists', 400);
                }
                $user->email = $body['email'];
            }
            if (isset($body['role']))
                $user->role = $body['role'];
            if (isset($body['password']) && !empty($body['password'])) {
                $user->password_hash = password_hash($body['password'], PASSWORD_DEFAULT);
            }

            // Profile fields
            $profileFields = [
                'first_name',
                'last_name',
                'country',
                'street_line_1',
                'street_line_2',
                'city',
                'state',
                'postal_code',
                'mobile_number',
                'whatsapp_number',
                'instagram_link',
                'facebook_link'
            ];
            foreach ($profileFields as $field) {
                if (isset($body[$field])) {
                    $user->$field = $body[$field];
                }
            }

            $user->save();

            unset($user->password_hash);
            unset($user->reset_token);

            return $this->success($response, $user);
        } catch (\Exception $e) {
            return $this->error($response, 'Error updating employee: ' . $e->getMessage(), 500);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $user = User::find($id);

        if (!$user) {
            return $this->error($response, 'Employee not found', 404);
        }

        try {
            $user->delete();
            return $this->success($response, ['message' => 'Employee deleted']);
        } catch (\PDOException $e) {
            if ($e->getCode() == '23000') {
                return $this->error($response, 'Cannot delete employee because they are associated with other records (e.g., orders, reviews).', 409);
            }
            return $this->error($response, 'Database error: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            return $this->error($response, 'Error deleting employee: ' . $e->getMessage(), 500);
        }
    }
}
