# API Documentation

This directory contains the OpenAPI documentation for the Small Business Web Backend API.

## Files

- `index.html` - Interactive API documentation using Swagger UI
- `../openapi.yaml` - OpenAPI 3.0 specification file

## Viewing the Documentation

### Option 1: Local Web Server
1. Start a local web server in the project root:
   ```bash
   cd php-rest-sqlite-backend
   php -S localhost:8000 -t .
   ```

2. Open your browser and navigate to:
   ```
   http://localhost:8000/docs/
   ```

### Option 2: Direct File Access
Open `docs/index.html` directly in your browser. Note that some features may not work due to CORS restrictions when opening HTML files directly.

## API Overview

The Small Business Web Backend API provides comprehensive functionality for:

- **Authentication**: JWT-based user authentication
- **Products**: Catalog management with inventory tracking
- **Orders**: Complete order lifecycle management
- **Stores**: Multi-location store management
- **Inventory**: Stock level monitoring and adjustments
- **Reviews**: Customer product reviews and ratings
- **Testimonials**: Customer testimonials management
- **Bookkeeping**: Financial records and reporting
- **Referrals**: Referral program with points system
- **Coupons**: Discount code management
- **Returns**: Return request processing and refunds
- **Tax**: Multi-region tax rate configuration

## Authentication

Most endpoints require JWT authentication. Include the token in requests:

```
Authorization: Bearer <your-jwt-token>
```

### Getting Started

1. **Register**: `POST /auth/register`
2. **Login**: `POST /auth/login` (returns JWT token)
3. **Use API**: Include token in Authorization header

## Key Features

### Database Query Optimization
The API uses optimized database queries:
- Column-specific selections instead of `SELECT *`
- Efficient query building with WHERE, ORDER BY, LIMIT
- Reduced memory usage and faster response times

### PSR Compliance
- PSR-7 HTTP Message Interfaces
- PSR-15 Middleware Interfaces
- PSR-4 Autoloading

### Modern Architecture
- Slim Framework 4.0
- Dependency injection
- Clean separation of concerns
- Comprehensive test coverage

## Development

The API is built with:
- **PHP 8.2+**
- **Slim Framework 4.0**
- **SQLite Database**
- **JWT Authentication**
- **PHPUnit Testing**

## Support

For API support or questions, please refer to the interactive documentation or contact the development team.