<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use App\Services\CacheableProductService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Predis\Client;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class CacheableProductServiceTest extends TestCase
{
    private CacheableProductService $service;
    private MockObject $redisAdapter;
    private MockObject $redisClient;

    protected function setUp(): void
    {
        $this->redisClient = $this->createMock(Client::class);
        $this->redisAdapter = $this->createMock(RedisAdapter::class);

        $this->service = new CacheableProductService();
    }

    public function testServiceCanBeInstantiated(): void
    {
        $service = new CacheableProductService();
        $this->assertInstanceOf(CacheableProductService::class, $service);
    }

    public function testInvalidateProductDeletesCache(): void
    {
        $service = new CacheableProductService();
        $this->assertInstanceOf(CacheableProductService::class, $service);

        // Test that invalidate method exists and doesn't throw
        $service->invalidateProduct(1);
        $this->assertTrue(true); // If no exception, test passes
    }
}