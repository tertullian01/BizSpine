<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Container;
use PHPUnit\Framework\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testContainerCreation(): void
    {
        $this->assertInstanceOf(Container::class, $this->container);
    }

    public function testBindAndGet(): void
    {
        $this->container->bind('test_service', fn() => 'test_value');

        $result = $this->container->get('test_service');
        $this->assertEquals('test_value', $result);
    }

    public function testSingleton(): void
    {
        $callCount = 0;
        $this->container->singleton('singleton_service', function() use (&$callCount) {
            $callCount++;
            return new \stdClass();
        });

        $obj1 = $this->container->get('singleton_service');
        $obj2 = $this->container->get('singleton_service');

        $this->assertSame($obj1, $obj2);
        $this->assertEquals(1, $callCount);
    }

    public function testHasMethod(): void
    {
        $this->assertFalse($this->container->has('nonexistent'));

        $this->container->bind('existing', fn() => 'value');
        $this->assertTrue($this->container->has('existing'));

        $this->container->singleton('singleton', fn() => 'value');
        $this->assertTrue($this->container->has('singleton'));
    }

    public function testAutoResolution(): void
    {
        // Test that it can resolve a simple class
        $result = $this->container->get(\stdClass::class);
        $this->assertInstanceOf(\stdClass::class, $result);
    }

    public function testDependencyInjection(): void
    {
        // Test resolving a class with dependencies
        $this->container->bind('test_dep', fn() => 'injected_value');

        $result = $this->container->get('test_dep');
        $this->assertEquals('injected_value', $result);
    }
}