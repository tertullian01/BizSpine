<?php

namespace App\Controllers;

use App\Models\EmailLog;
use App\Services\PaginationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EmailLogController extends ApiController
{
    private PaginationService $paginationService;

    public function __construct(PaginationService $paginationService)
    {
        $this->paginationService = $paginationService;
    }

    public function getAll(Request $request, Response $response): Response
    {
        $pagination = $this->paginationService->getPaginationParams($request);
        $total = EmailLog::count();

        $logs = EmailLog::select()
                       ->orderBy('sent_at', 'DESC')
                       ->limit($pagination['limit'], $pagination['offset'])
                       ->get();

        $result = $this->paginationService->formatPaginatedResponse($logs, $total, $pagination['page'], $pagination['limit']);

        return $this->success($response, $result);
    }
}