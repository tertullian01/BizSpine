<?php

declare(strict_types=1);

namespace Tests\Integration;

use OpenApi\Generator;
use PHPUnit\Framework\TestCase;

class ApiContractTest extends TestCase
{
    public function testApiMatchesSpecification(): void
    {
        // Generate OpenAPI specification from annotations
        $openapi = Generator::scan([__DIR__ . '/../../src']);

        // Convert to array for easier testing
        $spec = json_decode($openapi->toJson(), true);

        // Test that the spec was generated
        $this->assertIsArray($spec);
        $this->assertArrayHasKey('openapi', $spec);
        $this->assertArrayHasKey('info', $spec);

        // Test that components/schemas exist
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('schemas', $spec['components']);

        // Validate basic structure
        $this->assertEquals('3.0.0', $spec['openapi']);
        $this->assertArrayHasKey('title', $spec['info']);
        $this->assertArrayHasKey('version', $spec['info']);
    }

    public function testProductSchemaIsDefined(): void
    {
        $openapi = Generator::scan([__DIR__ . '/../../src']);
        $spec = json_decode($openapi->toJson(), true);

        // Check if Product schema exists
        $this->assertArrayHasKey('Product', $spec['components']['schemas']);

        $productSchema = $spec['components']['schemas']['Product'];

        // Test required fields
        $this->assertContains('id', $productSchema['properties']);
        $this->assertContains('name', $productSchema['properties']);
        $this->assertContains('cost', $productSchema['properties']);

        // Test data types
        $this->assertEquals('integer', $productSchema['properties']['id']['type']);
        $this->assertEquals('string', $productSchema['properties']['name']['type']);
        $this->assertEquals('number', $productSchema['properties']['cost']['type']);
    }

    public function testErrorResponseSchemaIsDefined(): void
    {
        $openapi = Generator::scan([__DIR__ . '/../../src']);
        $spec = json_decode($openapi->toJson(), true);

        // Check if Error schema exists
        $this->assertArrayHasKey('Error', $spec['components']['schemas']);

        $errorSchema = $spec['components']['schemas']['Error'];

        // Test error structure
        $this->assertArrayHasKey('properties', $errorSchema);
        $this->assertArrayHasKey('error', $errorSchema['properties']);
        $this->assertEquals('string', $errorSchema['properties']['error']['type']);
    }

    public function testValidationErrorSchemaIsDefined(): void
    {
        $openapi = Generator::scan([__DIR__ . '/../../src']);
        $spec = json_decode($openapi->toJson(), true);

        // Check if ValidationError schema exists
        $this->assertArrayHasKey('ValidationError', $spec['components']['schemas']);

        $validationErrorSchema = $spec['components']['schemas']['ValidationError'];

        // Test validation error structure
        $this->assertArrayHasKey('properties', $validationErrorSchema);
        $this->assertArrayHasKey('errors', $validationErrorSchema['properties']);
        $this->assertEquals('object', $validationErrorSchema['properties']['errors']['type']);
    }
}