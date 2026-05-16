<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class CacheableProductService
{
    private mixed $cache;

    public function __construct()
    {
        $this->cache = new ArrayAdapter();
    }

    public function getProduct(int $id): ?Product
    {
        $cacheKey = "product_{$id}";

        return $this->cache->get($cacheKey, function() use ($id) {
            return Product::find($id);
        });
    }

    public function invalidateProduct(int $id): void
    {
        $cacheKey = "product_{$id}";
        $this->cache->delete($cacheKey);
    }
}