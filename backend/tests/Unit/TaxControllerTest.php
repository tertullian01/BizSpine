<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\TaxController;
use App\Models\TaxRate;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class TaxControllerTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
// Insert default tax rate
        self::$db->exec("INSERT INTO tax_rates (name, rate, region, is_default, is_active) VALUES ('Default Tax', 19.0, 'DE', 1, 1)");
        self::$db->exec("INSERT INTO tax_rates (name, rate, region, is_default, is_active) VALUES ('US Tax', 7.5, 'US', 0, 1)");
    }

    public function testGetAllTaxRates()
    {
        $controller = new TaxController();
        $request = $this->createRequest('GET', '/api/tax-rates');
        $response = $this->createResponse();
        $response = $controller->getAll($request, $response, []);
        $body = json_decode($response->getBody()->__toString());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($body->success);
        $this->assertCount(2, $body->data->data);
    }

    public function testGetDefaultTaxRate()
    {
        $controller = new TaxController();
        $request = $this->createRequest('GET', '/api/tax-rates/default');
        $response = $this->createResponse();
        $response = $controller->getDefault($request, $response, []);
        $body = json_decode($response->getBody()->__toString());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($body->success);
        $this->assertEquals('Default Tax', $body->data->name);
        $this->assertEquals(19.0, $body->data->rate);
        $this->assertEquals(1, $body->data->is_default);
    }

    public function testCalculateTaxWithDefaultRate()
    {
        $result = TaxRate::calculateTax(100.00, null);
        $this->assertEquals(19.0, $result['tax_rate']);
        $this->assertEquals(19.00, $result['tax_amount']);
        $this->assertEquals(119.00, $result['total_with_tax']);
    }

    public function testCalculateTaxWithRegionRate()
    {
        $result = TaxRate::calculateTax(100.00, 'US');
        $this->assertEquals(7.5, $result['tax_rate']);
        $this->assertEquals(7.50, $result['tax_amount']);
        $this->assertEquals(107.50, $result['total_with_tax']);
    }

    public function testCreateTaxRate()
    {
        $controller = new TaxController();
        $requestData = [
            'name' => 'UK VAT',
            'rate' => 20.0,
            'region' => 'UK',
            'description' => 'UK VAT rate',
        ];
        $request = $this->createRequestWithBody('POST', '/api/tax-rates', $requestData);
        $response = $this->createResponse();
        $response = $controller->create($request, $response, []);
        $body = json_decode($response->getBody()->__toString());
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($body->success);
        $this->assertEquals('UK VAT', $body->data->name);
        $this->assertEquals(20.0, $body->data->rate);
        $this->assertEquals('UK', $body->data->region);
    }
}
