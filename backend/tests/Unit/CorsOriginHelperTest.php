<?php

namespace Tests\Unit;

use App\Services\CorsOriginHelper;
use PHPUnit\Framework\TestCase;

class CorsOriginHelperTest extends TestCase
{
    public function testExpandWwwVariants(): void
    {
        $origins = CorsOriginHelper::expandWwwVariants(['https://example.com']);

        $this->assertContains('https://example.com', $origins);
        $this->assertContains('https://www.example.com', $origins);
    }

    public function testFinalizeIncludesDevOriginsWhenDebugEnabled(): void
    {
        $origins = CorsOriginHelper::finalize(['https://example.com'], true);

        $this->assertContains('http://localhost:5173', $origins);
        $this->assertContains('https://www.example.com', $origins);
    }
}
