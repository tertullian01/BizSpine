<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface as Request;

class PaginationService
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    public function getPaginationParams(Request $request): array
    {
        $queryParams = $request->getQueryParams();

        $page = (int)($queryParams['page'] ?? self::DEFAULT_PAGE);
        $limit = (int)($queryParams['limit'] ?? self::DEFAULT_LIMIT);

        // Ensure valid values
        $page = max(1, $page);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        $offset = ($page - 1) * $limit;

        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function formatPaginatedResponse(array $items, int $total, int $page, int $limit): array
    {
        $totalPages = (int)ceil($total / $limit);

        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'prev_page' => $page > 1 ? $page - 1 : null,
            ],
        ];
    }

    public function getPaginationInfo(int $total, int $page, int $limit): array
    {
        $totalPages = (int)ceil($total / $limit);

        return [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1,
            'next_page' => $page < $totalPages ? $page + 1 : null,
            'prev_page' => $page > 1 ? $page - 1 : null,
        ];
    }
}