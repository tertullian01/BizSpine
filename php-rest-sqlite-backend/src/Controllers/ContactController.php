<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Services\Config;
use App\Services\Database;
use App\Services\EmailService;
use App\Services\Logger;
use App\Services\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use OpenApi\Annotations as OA;

class ContactController extends ApiController
{
    private PDO $db;
    private Validator $validator;
    private ?EmailService $emailService;

    /**
     * @param PDO|null $db
     * @param EmailService|null $emailService
     */
    public function __construct($db = null, $emailService = null)
    {
        $config = Config::getInstance()->getAll();

        if ($db) {
            $this->db = $db;
        } else {
            $dbPath = $config['db_path'] ?? $config['database']['database_path'] ?? null;
            $this->db = Database::get($dbPath);
        }
        $this->validator = new Validator();
        $this->emailService = $emailService ?? new EmailService($this->db, new Logger(), $config);
    }

    /**
     * @OA\Post(
     *     path="/contact",
     *     summary="Send a contact message",
     *     tags={"Contact"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "subject", "message"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="subject", type="string", example="Inquiry"),
     *             @OA\Property(property="message", type="string", example="Hello...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="message", type="string", example="Message sent successfully")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     )
     * )
     */
    public function send(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        try {
            $this->validator->validate($body, [
                'name' => v::notEmpty()->stringType()->setName('Name'),
                'email' => v::notEmpty()->email()->setName('Email'),
                'subject' => v::notEmpty()->stringType()->setName('Subject'),
                'message' => v::notEmpty()->stringType()->setName('Message'),
            ]);
        } catch (ValidationException $e) {
            return $this->error($response, $e->getFirstError(), 400);
        }

        try {
            $stmt = $this->db->query("SELECT value FROM settings WHERE key = 'store_email'");
            $storeEmail = $stmt->fetchColumn();

            if (!$storeEmail) {
                // Fallback to site_email
                $stmt = $this->db->query("SELECT value FROM settings WHERE key = 'site_email'");
                $storeEmail = $stmt->fetchColumn();
            }

            if (!$storeEmail) {
                return $this->error($response, 'Store email not configured', 500);
            }

            $placeholders = [
                'name' => $body['name'],
                'email' => $body['email'],
                'subject' => $body['subject'],
                'message' => nl2br(htmlspecialchars($body['message'])),
                'timestamp' => date('Y-m-d H:i:s'),
            ];

            $this->emailService->sendTemplate($storeEmail, 'contact_us', $placeholders);

            return $this->success($response, ['message' => 'Message sent successfully']);
        } catch (\Exception $e) {
            return $this->error($response, 'Failed to send message: ' . $e->getMessage(), 500);
        }
    }
}