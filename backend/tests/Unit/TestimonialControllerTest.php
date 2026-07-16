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
        $this->assertTrue($data->success);
        $this->assertEquals('John Doe', $data->data->customer_name);
        $this->assertEquals('john@example.com', $data->data->customer_email);
        $this->assertEquals('35-44', $data->data->age_range);
        $this->assertEquals('Great service and products!', $data->data->testimonial_text);
        $this->assertEquals(0, $data->data->published);
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
        $this->assertFalse($data->success);
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
        $this->assertFalse($data->success);
        $this->assertStringContainsString('Invalid age range', $data->error);
    }

    public function testGetPublishedTestimonials()
    {
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, rating, is_featured, published) VALUES ('John Doe', 'john@example.com', 'Published testimonial', 5, 1, 1)");
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, published) VALUES ('Jane Smith', 'jane@example.com', 'Unpublished testimonial', 0)");
        $controller = new TestimonialController();
        $request = $this->createRequest('GET', '/testimonials/published');
        $response = $this->createResponse();
        $response = $controller->getPublished($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data->success);
        $this->assertCount(1, $data->data->data);
        $this->assertEquals('Published testimonial', $data->data->data[0]->testimonial_text);
        $this->assertEquals(5, $data->data->data[0]->rating);
        $this->assertEquals(1, $data->data->data[0]->is_featured);
    }

    public function testGetFeaturedTestimonials()
    {
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, rating, is_featured, published) VALUES ('Featured User', 'featured@example.com', 'Featured review', 5, 1, 1)");
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, rating, is_featured, published) VALUES ('Regular User', 'regular@example.com', 'Regular review', 4, 0, 1)");
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, rating, is_featured, published) VALUES ('Draft User', 'draft@example.com', 'Draft review', 5, 1, 0)");
        $controller = new TestimonialController();
        $request = $this->createRequest('GET', '/testimonials/featured');
        $response = $this->createResponse();
        $response = $controller->getFeatured($request, $response);
        $body = (string) $response->getBody();
        $data = json_decode($body);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data->success);
        $this->assertCount(1, $data->data->data);
        $this->assertEquals('Featured review', $data->data->data[0]->testimonial_text);
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
        $this->assertTrue($data->success);
        $this->assertCount(2, $data->data->data);
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
        $this->assertTrue($data->success);
        $this->assertEquals(1, $data->data->published);
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
        $this->assertTrue($data->success);
        $this->assertEquals(0, $data->data->published);
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
