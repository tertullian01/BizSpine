<?php

namespace App\Controllers;

use App\Services\Config;
use App\Exceptions\ValidationException;
use App\Services\Database;
use App\Services\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;

class BookkeepingController
{
    private PDO $db;
    private Validator $validator;
    public function __construct(PDO $db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            $this->db = Database::get(Config::get('database.database_path'));
        }
        $this->validator = new Validator();
    }

    // ========== INCOME METHODS ==========

    public function getAllIncome(Request $request, Response $response): Response
    {
        $sql = <<<'SQL'
SELECT 
    i.*,
    o.order_number
FROM income i
LEFT JOIN orders o ON i.order_id = o.id
ORDER BY i.payment_date DESC
SQL;
        $stmt = $this->db->query($sql);
        $income = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\Income');
        $response->getBody()->write(json_encode($income));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getIncomeById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $sql = <<<'SQL'
SELECT 
    i.*,
    o.order_number
FROM income i
LEFT JOIN orders o ON i.order_id = o.id
WHERE i.id = :id
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $income = $stmt->fetchObject('App\Models\Income');
        if (!$income) {
            $response->getBody()->write(json_encode(['error' => 'Income record not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($income));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function createIncome(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $this->validator->validate($body, [
            'amount' => v::notEmpty()->floatVal()->positive()->setName('Amount'),
        ]);
        $sql = <<<'SQL'
INSERT INTO income 
    (order_id, amount, payment_method, payment_date, description, notes, created_at, updated_at) 
VALUES 
    (:order_id, :amount, :payment_method, :payment_date, :description, :notes, datetime("now"), datetime("now"))
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':order_id' => $body['order_id'] ?? null,
            ':amount' => (float)$body['amount'],
            ':payment_method' => $body['payment_method'] ?? null,
            ':payment_date' => $body['payment_date'] ?? date('Y-m-d H:i:s'),
            ':description' => $body['description'] ?? null,
            ':notes' => $body['notes'] ?? null,
        ]);
        $id = (int)$this->db->lastInsertId();
        return $this->getIncomeById($request, $response->withStatus(201), ['id' => $id]);
    }

    public function deleteIncome(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $checkStmt = $this->db->prepare('SELECT id FROM income WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Income record not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $stmt = $this->db->prepare('DELETE FROM income WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $response->withStatus(204);
    }

    // ========== EXPENSE METHODS ==========

    public function getAllExpenses(Request $request, Response $response): Response
    {
        $sql = <<<'SQL'
SELECT 
    e.*,
    o.order_number
FROM expenses e
LEFT JOIN orders o ON e.order_id = o.id
ORDER BY e.expense_date DESC
SQL;
        $stmt = $this->db->query($sql);
        $expenses = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\Expense');
        $response->getBody()->write(json_encode($expenses));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getExpensesByCategory(Request $request, Response $response, array $args): Response
    {
        $category = $args['category'];
// Assuming category is passed as an argument or query param?
        // Wait, the original code didn't use args, but it was likely intended for a route like /expenses/category/{category}
        // But looking at api.php, there is no route for getExpensesByCategory.
        // I'll keep it but it might be unused.

        $sql = <<<'SQL'
SELECT 
    e.*,
    o.order_number
FROM expenses e
LEFT JOIN orders o ON e.order_id = o.id
WHERE e.category = :category
ORDER BY e.expense_date DESC
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':category' => $category]);
        $expenses = $stmt->fetchAll(PDO::FETCH_CLASS, 'App\Models\Expense');
        $response->getBody()->write(json_encode($expenses));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getExpenseById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $sql = <<<'SQL'
SELECT 
    e.*,
    o.order_number
FROM expenses e
LEFT JOIN orders o ON e.order_id = o.id
WHERE e.id = :id
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $expense = $stmt->fetchObject('App\Models\Expense');
        if (!$expense) {
            $response->getBody()->write(json_encode(['error' => 'Expense record not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($expense));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function createExpense(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $this->validator->validate($body, [
            'category' => v::notEmpty()->setName('Category'),
            'amount' => v::notEmpty()->floatVal()->positive()->setName('Amount'),
        ]);
        $sql = <<<'SQL'
INSERT INTO expenses 
    (order_id, vendor, category, amount, expense_date, description, receipt_image_url, notes, created_at, updated_at) 
VALUES 
    (:order_id, :vendor, :category, :amount, :expense_date, :description, :receipt_image_url, :notes, datetime("now"), datetime("now"))
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':order_id' => $body['order_id'] ?? null,
            ':vendor' => $body['vendor'] ?? null,
            ':category' => $body['category'],
            ':amount' => (float)$body['amount'],
            ':expense_date' => $body['expense_date'] ?? date('Y-m-d H:i:s'),
            ':description' => $body['description'] ?? null,
            ':receipt_image_url' => $body['receipt_image_url'] ?? null,
            ':notes' => $body['notes'] ?? null,
        ]);
        $id = (int)$this->db->lastInsertId();
        return $this->getExpenseById($request, $response->withStatus(201), ['id' => $id]);
    }

    public function updateExpense(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        $checkStmt = $this->db->prepare('SELECT id FROM expenses WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Expense record not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $this->validator->validate($body, [
            'amount' => v::optional(v::floatVal()->positive()->setName('Amount')),
        ]);
        $updates = [];
        $params = [':id' => $id];
        if (isset($body['vendor'])) {
            $updates[] = 'vendor = :vendor';
            $params[':vendor'] = $body['vendor'];
        }

        if (isset($body['category'])) {
            $updates[] = 'category = :category';
            $params[':category'] = $body['category'];
        }

        if (isset($body['amount'])) {
            $updates[] = 'amount = :amount';
            $params[':amount'] = (float)$body['amount'];
        }

        if (isset($body['description'])) {
            $updates[] = 'description = :description';
            $params[':description'] = $body['description'];
        }

        if (isset($body['receipt_image_url'])) {
            $updates[] = 'receipt_image_url = :receipt_image_url';
            $params[':receipt_image_url'] = $body['receipt_image_url'];
        }

        if (isset($body['notes'])) {
            $updates[] = 'notes = :notes';
            $params[':notes'] = $body['notes'];
        }

        if (empty($updates)) {
            throw new ValidationException('No valid fields to update');
        }

        $updates[] = 'updated_at = datetime("now")';
        $sql = 'UPDATE expenses SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->getExpenseById($request, $response, ['id' => $id]);
    }

    public function deleteExpense(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $checkStmt = $this->db->prepare('SELECT id FROM expenses WHERE id = :id');
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            $response->getBody()->write(json_encode(['error' => 'Expense record not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $stmt = $this->db->prepare('DELETE FROM expenses WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $response->withStatus(204);
    }

    public function getSummary(Request $request, Response $response): Response
    {
        // Get total income
        $incomeStmt = $this->db->query('SELECT COALESCE(SUM(amount), 0) as total FROM income');
        $totalIncome = (float)$incomeStmt->fetchColumn();
// Get total expenses
        $expenseStmt = $this->db->query('SELECT COALESCE(SUM(amount), 0) as total FROM expenses');
        $totalExpenses = (float)$expenseStmt->fetchColumn();
// Calculate profit
        $profit = $totalIncome - $totalExpenses;
// Get expense breakdown by category
        $categoryStmt = $this->db->query('SELECT category, SUM(amount) as total FROM expenses GROUP BY category ORDER BY total DESC');
        $expensesByCategory = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
        $summary = [
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'profit' => $profit,
            'expenses_by_category' => $expensesByCategory,
        ];
        $response->getBody()->write(json_encode($summary));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
