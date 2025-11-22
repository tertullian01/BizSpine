<?php

declare(strict_types=1);

use Slim\App;

/** @var App $app */

// Register all routes
\App\Routes\ProductRoutes::register($app);
\App\Routes\OrderRoutes::register($app);
\App\Routes\AuthRoutes::register($app);
\App\Routes\StoreRoutes::register($app);
\App\Routes\InventoryRoutes::register($app);
\App\Routes\ReviewRoutes::register($app);
\App\Routes\TestimonialRoutes::register($app);
\App\Routes\BookkeepingRoutes::register($app);
\App\Routes\ReferralRoutes::register($app);
\App\Routes\CouponRoutes::register($app);
\App\Routes\TaxRoutes::register($app);
\App\Routes\ReturnRoutes::register($app);
\App\Routes\HealthRoutes::register($app);
