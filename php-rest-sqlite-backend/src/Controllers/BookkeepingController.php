<?php
namespace App\Controllers;

use App\Models\Income;
use App\Models\Expense;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BookkeepingController
{
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
        
        $income = Income::fetchAll($sql);
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
        
        $income = Income::fetchOne($sql, [':id' => $id]);
        
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
        
        if (!isset($body['amount']) || $body['amount'] <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Valid amount is required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        try {
            $income = new Income([
                'order_id' => $body['order_id'] ?? null,
                'amount' => (float)$body['amount'],
                'payment_method' => $body['payment_method'] ?? null,
                'payment_date' => $body['payment_date'] ?? date('Y-m-d H:i:s'),
                'description' => $body['description'] ?? null,
                'notes' => $body['notes'] ?? null,
            ]);
            $income->save();
            
            return $this->getIncomeById($request, $response->withStatus(201), ['id' => $income->id]);
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function deleteIncome(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $income = Income::find($id);
        if (!$income) {
            $response->getBody()->write(json_encode(['error' => 'Income record not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $income->delete();
        
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
        
        $expenses = Expense::fetchAll($sql);
        $response->getBody()->write(json_encode($expenses));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getExpensesByCategory(Request $request, Response $response, array $args): Response
    {
        $category = $args['category'];
        
        $sql = <<<'SQL'
SELECT 
    e.*,
    o.order_number
FROM expenses e
LEFT JOIN orders o ON e.order_id = o.id
WHERE e.category = :category
ORDER BY e.expense_date DESC
SQL;
        
        $expenses = Expense::fetchAll($sql, [':category' => $category]);
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
        
        $expense = Expense::fetchOne($sql, [':id' => $id]);
        
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
        
        if (empty($body['category']) || !isset($body['amount']) || $body['amount'] <= 0) {
            $response->getBody()->write(json_encode(['error' => 'category and valid amount are required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        try {
            $expense = new Expense([
                'order_id' => $body['order_id'] ?? null,
                'vendor' => $body['vendor'] ?? null,
                'category' => $body['category'],
                'amount' => (float)$body['amount'],
                'expense_date' => $body['expense_date'] ?? date('Y-m-d H:i:s'),
                'description' => $body['description'] ?? null,
                'receipt_image_url' => $body['receipt_image_url'] ?? null,
                'notes' => $body['notes'] ?? null,
            ]);
            $expense->save();
            
            return $this->getExpenseById($request, $response->withStatus(201), ['id' => $expense->id]);
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function updateExpense(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        
        $expense = Expense::find($id);
        if (!$expense) {
            $response->getBody()->write(json_encode(['error' => 'Expense record not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        if (isset($body['vendor'])) {
            $expense->vendor = $body['vendor'];
        }
        
        if (isset($body['category'])) {
            $expense->category = $body['category'];
        }
        
        if (isset($body['amount'])) {
            if ($body['amount'] <= 0) {
                $response->getBody()->write(json_encode(['error' => 'Amount must be greater than 0']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $expense->amount = (float)$body['amount'];
        }
        
        if (isset($body['description'])) {
            $expense->description = $body['description'];
        }
        
        if (isset($body['receipt_image_url'])) {
            $expense->receipt_image_url = $body['receipt_image_url'];
        }
        
        if (isset($body['notes'])) {
            $expense->notes = $body['notes'];
        }
        
        $expense->save();

        return $this->getExpenseById($request, $response, ['id' => $id]);
    }

    public function deleteExpense(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $expense = Expense::find($id);
        if (!$expense) {
            $response->getBody()->write(json_encode(['error' => 'Expense record not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $expense->delete();
        
        return $response->withStatus(204);
    }

    public function getSummary(Request $request, Response $response): Response
    {
        $totalIncome = Income::getTotalIncome();
        $totalExpenses = Expense::getTotalExpenses();
        $profit = $totalIncome - $totalExpenses;
        $expensesByCategory = Expense::getExpensesByCategory();
        
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