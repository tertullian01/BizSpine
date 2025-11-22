<?php

namespace Tests\Unit;

use Tests\DatabaseTestCase;
use App\Controllers\TestimonialController;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class TestimonialControllerTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testCreateTestimonial()
    {
        $controller = new TestimonialController();
        $request = $this->createRequestWithBody('POST', '/testimonials', [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'age_range' => '35-44',
            'testimonial_text' => 'Great service and products!',
            'image_url' => 'https://example.com/photo.jpg',
        ]);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('John Doe', $data->customer_name);
        $this->assertEquals('john@example.com', $data->customer_email);
        $this->assertEquals('35-44', $data->age_range);
        $this->assertEquals('Great service and products!', $data->testimonial_text);
        $this->assertEquals(0, $data->published);
// Default false
    }

    public function testCreateTestimonialWithInvalidEmail()
    {
        $controller = new TestimonialController();
        $request = $this->createRequestWithBody('POST', '/testimonials', [
            'customer_name' => 'John Doe',
            'customer_email' => 'invalid-email',
            'testimonial_text' => 'Great service!',
        ]);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Invalid email format', $data->error);
    }

    public function testCreateTestimonialWithInvalidAgeRange()
    {
        $controller = new TestimonialController();
        $request = $this->createRequestWithBody('POST', '/testimonials', [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'age_range' => 'invalid-range',
            'testimonial_text' => 'Great service!',
        ]);
        $response = $this->createResponse();
        $response = $controller->create($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Invalid age range', $data->error);
    }

    public function testGetPublishedTestimonials()
    {
        // Insert published and unpublished testimonials
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, published) VALUES ('John Doe', 'john@example.com', 'Published testimonial', 1)");
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, published) VALUES ('Jane Smith', 'jane@example.com', 'Unpublished testimonial', 0)");
        $controller = new TestimonialController();
        $request = $this->createRequest('GET', '/testimonials');
        $response = $this->createResponse();
        $response = $controller->getAll($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data->data);
        // Only published testimonial
        $this->assertEquals('Published testimonial', $data->data[0]->testimonial_text);
    }

    public function testGetAllAdminTestimonials()
    {
        // Insert published and unpublished testimonials
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, published) VALUES ('John Doe', 'john@example.com', 'Published', 1)");
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, published) VALUES ('Jane Smith', 'jane@example.com', 'Unpublished', 0)");
        $controller = new TestimonialController();
        $request = $this->createRequest('GET', '/testimonials/admin');
        $response = $this->createResponse();
        $response = $controller->getAllAdmin($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(2, $data->data);
        // Both testimonials
    }

    public function testPublishTestimonial()
    {
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, published) VALUES ('John Doe', 'john@example.com', 'Great!', 0)");
        $id = (int)self::$db->lastInsertId();
        $controller = new TestimonialController();
        $request = $this->createRequest('POST', "/testimonials/$id/publish");
        $response = $this->createResponse();
        $response = $controller->publish($request, $response, ['id' => $id]);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $data->published);
    }

    public function testUnpublishTestimonial()
    {
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, published) VALUES ('John Doe', 'john@example.com', 'Great!', 1)");
        $id = (int)self::$db->lastInsertId();
        $controller = new TestimonialController();
        $request = $this->createRequest('POST', "/testimonials/$id/unpublish");
        $response = $this->createResponse();
        $response = $controller->unpublish($request, $response, ['id' => $id]);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(0, $data->published);
    }

    public function testDeleteTestimonial()
    {
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text) VALUES ('John Doe', 'john@example.com', 'Great!')");
        $id = (int)self::$db->lastInsertId();
        $controller = new TestimonialController(self::$db);
        $request = $this->createRequest('DELETE', "/testimonials/$id");
        $response = $this->createResponse();
        $response = $controller->delete($request, $response, ['id' => $id]);
        $this->assertEquals(204, $response->getStatusCode());
        $stmt = self::$db->query("SELECT COUNT(*) FROM testimonials WHERE id = $id");
        $this->assertEquals(0, $stmt->fetchColumn());
    }
}
