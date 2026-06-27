<?php

declare(strict_types=1);

namespace App\Services;

final class CorsOriginHelper
{
    /** @return list<string> */
    public static function expandWwwVariants(array $origins): array
    {
        $expanded = $origins;

        foreach ($origins as $origin) {
            if (!is_string($origin) || $origin === '') {
                continue;
            }

            if (preg_match('#^(https?)://(www\.)?([^/]+)#i', rtrim($origin, '/'), $matches)) {
                $scheme = strtolower($matches[1]);
                $host = $matches[3];
                $expanded[] = "{$scheme}://{$host}";
                $expanded[] = "{$scheme}://www.{$host}";
            }
        }

        return array_values(array_unique(array_filter($expanded)));
    }

    /** @return list<string> */
    public static function devOrigins(): array
    {
        return [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'http://localhost:8000',
            'http://127.0.0.1:8000',
        ];
    }

    /** @return list<string> */
    public static function finalize(array $origins, bool $includeDevOrigins = false): array
    {
        if ($includeDevOrigins) {
            $origins = array_merge($origins, self::devOrigins());
        }

        return self::expandWwwVariants($origins);
    }
}
