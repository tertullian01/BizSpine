<?php

namespace App\Models;

class Testimonial extends BaseModel
{
    protected static string $tableName = 'testimonials';
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
