<?php

namespace Tests\Unit;

use App\Services\Config;
use PHPUnit\Framework\TestCase;

class ConfigUrlResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetConfigSingleton();
    }

    protected function tearDown(): void
    {
        unset($_ENV['STOREFRONT_URL'], $_ENV['PASSWORD_RESET_URL'], $_ENV['PASSWORD_RESET_PATH']);
        $this->resetConfigSingleton();
        parent::tearDown();
    }

    private function resetConfigSingleton(): void
    {
        $ref = new \ReflectionClass(Config::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    public function testBuildsPasswordResetUrlFromStorefrontAndPath(): void
    {
        $_ENV['STOREFRONT_URL'] = 'https://example.com';
        unset($_ENV['PASSWORD_RESET_URL'], $_ENV['PASSWORD_RESET_PATH']);

        $config = Config::getInstance()->getAll();

        $this->assertSame('https://example.com', $config['app']['storefront_url']);
        $this->assertSame('https://example.com/reset.html', $config['app']['password_reset_url']);
    }

    public function testPasswordResetUrlOverride(): void
    {
        $_ENV['STOREFRONT_URL'] = 'https://example.com';
        $_ENV['PASSWORD_RESET_URL'] = 'https://example.com/custom-reset.html';

        $config = Config::getInstance()->getAll();

        $this->assertSame('https://example.com/custom-reset.html', $config['app']['password_reset_url']);
    }

    public function testPlaceholderValuesAreIgnored(): void
    {
        $_ENV['STOREFRONT_URL'] = 'https://INSERT_URL_HERE';
        unset($_ENV['PASSWORD_RESET_URL']);

        $config = Config::getInstance()->getAll();

        $this->assertSame('', $config['app']['storefront_url']);
        $this->assertSame('', $config['app']['password_reset_url']);
    }
}
