<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\BaseModel;
use App\Services\Container;
use App\Services\Database;
use Slim\Factory\AppFactory;
use Tests\DatabaseTestCase;

class TestimonialApiTest extends DatabaseTestCase
{
    private $app;

    protected function setUp(): void
    {
        parent::setUp();

        BaseModel::setDatabase(self::$db);
        Database::setInstance(self::$db);

        $container = new Container();
        AppFactory::setContainer($container);

        $container->bind(\App\Middleware\AuthMiddleware::class, fn ($c) => new \App\Middleware\AuthMiddleware('test-secret'));
        $container->bind(\App\Controllers\TestimonialController::class, fn ($c) => new \App\Controllers\TestimonialController(
            self::$db,
            new \App\Services\PaginationService()
        ));

        $this->app = AppFactory::create();
        $this->app->addBodyParsingMiddleware();
        $this->app->addRoutingMiddleware();
        $this->app->addErrorMiddleware(true, true, true);

        \App\Routes\TestimonialRoutes::register($this->app);
    }

    public function testFeaturedRouteDoesNotRequireAuth(): void
    {
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, rating, is_featured, published) VALUES ('Featured User', 'featured@example.com', 'Featured review', 5, 1, 1)");

        $request = $this->createRequest('GET', '/testimonials/featured');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']['data']);
        $this->assertEquals('Featured review', $data['data']['data'][0]['testimonial_text']);
    }

    public function testPublishedRouteIncludesIsFeatured(): void
    {
        self::$db->exec("INSERT INTO testimonials (customer_name, customer_email, testimonial_text, rating, is_featured, published) VALUES ('John Doe', 'john@example.com', 'Published review', 5, 1, 1)");

        $request = $this->createRequest('GET', '/testimonials/published');
        $response = $this->app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('is_featured', $data['data']['data'][0]);
        $this->assertEquals(1, $data['data']['data'][0]['is_featured']);
        $this->assertEquals(5, $data['data']['data'][0]['rating']);
    }

    public function testAdminRouteRequiresAuth(): void
    {
        $request = $this->createRequest('GET', '/testimonials/admin');
        $response = $this->app->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }
}
