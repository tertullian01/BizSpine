<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PaginationService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class PaginationServiceTest extends TestCase
{
    private PaginationService $paginationService;

    protected function setUp(): void
    {
        $this->paginationService = new PaginationService();
    }

    public function testGetPaginationParamsDefaultValues(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getQueryParams')
            ->willReturn([]);

        $params = $this->paginationService->getPaginationParams($request);

        $this->assertEquals(1, $params['page']);
        $this->assertEquals(20, $params['limit']);
        $this->assertEquals(0, $params['offset']);
    }

    public function testGetPaginationParamsWithCustomValues(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getQueryParams')
            ->willReturn(['page' => '3', 'limit' => '50']);

        $params = $this->paginationService->getPaginationParams($request);

        $this->assertEquals(3, $params['page']);
        $this->assertEquals(50, $params['limit']);
        $this->assertEquals(100, $params['offset']); // (3-1) * 50
    }

    public function testGetPaginationParamsValidatesMinimumValues(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getQueryParams')
            ->willReturn(['page' => '0', 'limit' => '-5']);

        $params = $this->paginationService->getPaginationParams($request);

        $this->assertEquals(1, $params['page']);
        $this->assertEquals(1, $params['limit']);
        $this->assertEquals(0, $params['offset']);
    }

    public function testGetPaginationParamsEnforcesMaximumLimit(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects($this->once())
            ->method('getQueryParams')
            ->willReturn(['limit' => '200']);

        $params = $this->paginationService->getPaginationParams($request);

        $this->assertEquals(100, $params['limit']);
    }

    public function testFormatPaginatedResponse(): void
    {
        $items = [['id' => 1, 'name' => 'Item 1'], ['id' => 2, 'name' => 'Item 2']];
        $total = 25;
        $page = 2;
        $limit = 10;

        $result = $this->paginationService->formatPaginatedResponse($items, $total, $page, $limit);

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertEquals($items, $result['data']);

        $pagination = $result['pagination'];
        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(3, $pagination['total_pages']);
        $this->assertTrue($pagination['has_next']);
        $this->assertTrue($pagination['has_prev']);
        $this->assertEquals(3, $pagination['next_page']);
        $this->assertEquals(1, $pagination['prev_page']);
    }

    public function testFormatPaginatedResponseFirstPage(): void
    {
        $items = [['id' => 1, 'name' => 'Item 1']];
        $total = 5;
        $page = 1;
        $limit = 10;

        $result = $this->paginationService->formatPaginatedResponse($items, $total, $page, $limit);

        $pagination = $result['pagination'];
        $this->assertFalse($pagination['has_prev']);
        $this->assertNull($pagination['prev_page']);
        $this->assertFalse($pagination['has_next']);
        $this->assertNull($pagination['next_page']);
    }

    public function testFormatPaginatedResponseLastPage(): void
    {
        $items = [['id' => 1, 'name' => 'Item 1']];
        $total = 25;
        $page = 3;
        $limit = 10;

        $result = $this->paginationService->formatPaginatedResponse($items, $total, $page, $limit);

        $pagination = $result['pagination'];
        $this->assertTrue($pagination['has_prev']);
        $this->assertEquals(2, $pagination['prev_page']);
        $this->assertFalse($pagination['has_next']);
        $this->assertNull($pagination['next_page']);
    }

    public function testGetPaginationInfo(): void
    {
        $total = 25;
        $page = 2;
        $limit = 10;

        $pagination = $this->paginationService->getPaginationInfo($total, $page, $limit);

        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(3, $pagination['total_pages']);
        $this->assertTrue($pagination['has_next']);
        $this->assertTrue($pagination['has_prev']);
        $this->assertEquals(3, $pagination['next_page']);
        $this->assertEquals(1, $pagination['prev_page']);
    }
}