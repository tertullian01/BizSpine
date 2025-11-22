<?php
namespace App\Controllers;

use App\Models\Testimonial;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TestimonialController
{
    public function getAll(Request $request, Response $response): Response
    {
        $testimonials = Testimonial::fetchAll('SELECT * FROM testimonials WHERE published = 1 ORDER BY created_at DESC');
        $response->getBody()->write(json_encode($testimonials));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getAllAdmin(Request $request, Response $response): Response
    {
        $testimonials = Testimonial::fetchAll('SELECT * FROM testimonials ORDER BY created_at DESC');
        $response->getBody()->write(json_encode($testimonials));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getById(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $testimonial = Testimonial::find($id);
        
        if (!$testimonial) {
            $response->getBody()->write(json_encode(['error' => 'Testimonial not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $response->getBody()->write(json_encode($testimonial));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        
        if (empty($body['customer_name']) || empty($body['customer_email']) || empty($body['testimonial_text'])) {
            $response->getBody()->write(json_encode(['error' => 'customer_name, customer_email, and testimonial_text are required']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        if (!filter_var($body['customer_email'], FILTER_VALIDATE_EMAIL)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid email format']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        $validAgeRanges = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];
        if (isset($body['age_range']) && !in_array($body['age_range'], $validAgeRanges)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid age range. Valid options: ' . implode(', ', $validAgeRanges)]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        
        try {
            $testimonial = new Testimonial([
                'customer_name' => $body['customer_name'],
                'customer_email' => $body['customer_email'],
                'age_range' => $body['age_range'] ?? null,
                'testimonial_text' => $body['testimonial_text'],
                'image_url' => $body['image_url'] ?? null,
                'published' => 0,
            ]);
            $testimonial->save();
            
            return $this->getById($request, $response->withStatus(201), ['id' => $testimonial->id]);
            
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = $request->getParsedBody();
        
        $testimonial = Testimonial::find($id);
        if (!$testimonial) {
            $response->getBody()->write(json_encode(['error' => 'Testimonial not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        if (isset($body['customer_name'])) {
            $testimonial->customer_name = $body['customer_name'];
        }
        
        if (isset($body['customer_email'])) {
            if (!filter_var($body['customer_email'], FILTER_VALIDATE_EMAIL)) {
                $response->getBody()->write(json_encode(['error' => 'Invalid email format']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $testimonial->customer_email = $body['customer_email'];
        }
        
        if (isset($body['age_range'])) {
            $validAgeRanges = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];
            if ($body['age_range'] !== null && !in_array($body['age_range'], $validAgeRanges)) {
                $response->getBody()->write(json_encode(['error' => 'Invalid age range']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $testimonial->age_range = $body['age_range'];
        }
        
        if (isset($body['testimonial_text'])) {
            $testimonial->testimonial_text = $body['testimonial_text'];
        }
        
        if (isset($body['image_url'])) {
            $testimonial->image_url = $body['image_url'];
        }
        
        $testimonial->save();

        return $this->getById($request, $response, ['id' => $id]);
    }

    public function publish(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $testimonial = Testimonial::find($id);
        if (!$testimonial) {
            $response->getBody()->write(json_encode(['error' => 'Testimonial not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $testimonial->publish();
        
        return $this->getById($request, $response, ['id' => $id]);
    }

    public function unpublish(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $testimonial = Testimonial::find($id);
        if (!$testimonial) {
            $response->getBody()->write(json_encode(['error' => 'Testimonial not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $testimonial->unpublish();
        
        return $this->getById($request, $response, ['id' => $id]);
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        
        $testimonial = Testimonial::find($id);
        if (!$testimonial) {
            $response->getBody()->write(json_encode(['error' => 'Testimonial not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }
        
        $testimonial->delete();
        
        return $response->withStatus(204);
    }
}