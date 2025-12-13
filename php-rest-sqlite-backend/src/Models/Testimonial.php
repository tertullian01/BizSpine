<?php

namespace App\Models;

class Testimonial extends BaseModel
{
    protected static string $tableName = 'testimonials';
    
    // Explicit property declarations for PHP 8.2+ compatibility
    // Note: Using mixed types for numeric fields since SQLite returns strings
    public string|int|null $customer_name = null;
    public string|null $customer_email = null;
    public string|null $testimonial_text = null;
    public string|int|null $rating = null;
    public string|int|null $is_featured = null;
    public string|int|null $published = null;
    public string|null $age_range = null;
    public string|null $image_url = null;
    public string|null $created_at = null;
    public string|null $updated_at = null;
    
    public function publish(): void
    {
        $this->published = 1;
        $this->save();
    }

    public function unpublish(): void
    {
        $this->published = 0;
        $this->save();
    }
}
