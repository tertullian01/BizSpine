<?php

namespace App\Controllers;

use App\Exceptions\ValidationException;
use App\Services\Database;
use App\Services\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="EmailTemplate",
 *     type="object",
 *     required={"name", "subject", "body"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="store_id", type="integer", nullable=true, example=1, description="Store ID associated with this template. Null for default system templates."),
 *     @OA\Property(property="name", type="string", example="order_confirmation"),
 *     @OA\Property(property="template_type", type="string", nullable=true, example="transactional"),
 *     @OA\Property(property="subject", type="string", example="Order Confirmation #{{order_number}}"),
 *     @OA\Property(property="body", type="string", example="<p>Thank you for your order...</p>"),
 *     @OA\Property(property="placeholders", type="string", description="JSON string of placeholders", example="[""order_number"", ""total""]"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class EmailTemplateController extends ApiController
{
    private PDO $db;
    private Validator $validator;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->validator = new Validator();
    }

    /**
     * @OA\Get(
     *     path="/email-templates",
     *     summary="Get all email templates",
     *     tags={"Email Templates"},
     *     @OA\Response(
     *         response=200,
     *         description="List of email templates",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmailTemplate"))
     *         )
     *     )
     * )
     */
    public function getAll(Request $request, Response $response): Response
    {
        $stmt = $this->db->query("SELECT id, store_id, name, template_type, subject, body, placeholders, created_at, updated_at FROM email_templates ORDER BY name, store_id");
        return $this->success($response, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @OA\Get(
     *     path="/email-templates/{id}",
     *     summary="Get email template by ID",
     *     tags={"Email Templates"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Email template details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/EmailTemplate")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Template not found")
     * )
     */
    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $stmt = $this->db->prepare("SELECT id, store_id, name, template_type, subject, body, placeholders, created_at, updated_at FROM email_templates WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            return $this->error($response, 'Template not found', 404);
        }

        return $this->success($response, $template);
    }

    /**
     * @OA\Post(
     *     path="/email-templates",
     *     summary="Create a new email template",
     *     tags={"Email Templates"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "subject", "body"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="template_type", type="string", nullable=true),
     *             @OA\Property(property="subject", type="string"),
     *             @OA\Property(property="body", type="string"),
     *             @OA\Property(property="store_id", type="integer", nullable=true),
     *             @OA\Property(property="placeholders", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Template created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", @OA\Property(property="id", type="integer"))
     *         )
     *     )
     * )
     */
    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        try {
            $this->validator->validate($body, [
                'name' => v::notEmpty()->slug()->setName('Name'),
                'subject' => v::notEmpty()->setName('Subject'),
                'body' => v::notEmpty()->setName('Body'),
                'store_id' => v::optional(v::intVal())->setName('Store ID'),
                'template_type' => v::optional(v::stringType())->setName('Template Type'),
            ]);
        } catch (ValidationException $e) {
            return $this->error($response, $e->getFirstError(), 400);
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO email_templates (name, store_id, template_type, subject, body, placeholders, created_at, updated_at) VALUES (:name, :store_id, :template_type, :subject, :body, :placeholders, datetime('now'), datetime('now'))");
            $stmt->execute([
                ':name' => $body['name'],
                ':store_id' => $body['store_id'] ?? null,
                ':template_type' => $body['template_type'] ?? null,
                ':subject' => $body['subject'],
                ':body' => $body['body'],
                ':placeholders' => isset($body['placeholders']) ? json_encode($body['placeholders']) : null
            ]);
            return $this->success($response, ['id' => $this->db->lastInsertId()], 201);
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                return $this->error($response, 'Template name already exists for this store', 409);
            }
            return $this->error($response, 'Database error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/email-templates/{id}",
     *     summary="Update an email template",
     *     tags={"Email Templates"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="subject", type="string"),
     *             @OA\Property(property="body", type="string"),
     *             @OA\Property(property="template_type", type="string", nullable=true),
     *             @OA\Property(property="store_id", type="integer", nullable=true),
     *             @OA\Property(property="placeholders", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Template updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", @OA\Property(property="message", type="string"))
     *         )
     *     )
     * )
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        
        $fields = [];
        $params = [':id' => $id];

        if (isset($body['subject'])) { $fields[] = 'subject = :subject'; $params[':subject'] = $body['subject']; }
        if (isset($body['body'])) { $fields[] = 'body = :body'; $params[':body'] = $body['body']; }
        if (isset($body['template_type'])) { $fields[] = 'template_type = :template_type'; $params[':template_type'] = $body['template_type']; }
        if (isset($body['placeholders'])) { $fields[] = 'placeholders = :placeholders'; $params[':placeholders'] = json_encode($body['placeholders']); }
        if (array_key_exists('store_id', $body)) { $fields[] = 'store_id = :store_id'; $params[':store_id'] = $body['store_id']; }

        if (empty($fields)) {
            return $this->error($response, 'No fields to update', 400);
        }

        $fields[] = "updated_at = datetime('now')";
        $sql = "UPDATE email_templates SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            return $this->error($response, 'Template not found or no changes made', 404);
        }

        return $this->success($response, ['message' => 'Template updated successfully']);
    }

    /**
     * @OA\Delete(
     *     path="/email-templates/{id}",
     *     summary="Delete an email template",
     *     tags={"Email Templates"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Template deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object", @OA\Property(property="message", type="string"))
     *         )
     *     )
     * )
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $stmt = $this->db->prepare("DELETE FROM email_templates WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        if ($stmt->rowCount() === 0) {
            return $this->error($response, 'Template not found', 404);
        }

        return $this->success($response, ['message' => 'Template deleted successfully']);
    }
}