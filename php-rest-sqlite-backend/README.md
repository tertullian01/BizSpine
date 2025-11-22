# PHP REST SQLite Backend

## Overview
This project is a PHP RESTful API backend that connects to an SQLite database. It is designed to provide a simple and efficient way to manage user data and perform various API operations.

## Project Structure
```
php-rest-sqlite-backend
├── public
│   ├── index.php          # Entry point of the application
│   └── .htaccess          # URL rewriting and security
├── src
│   ├── Controllers
│   │   ├── HealthController.php  # Health check endpoint
│   │   └── ApiController.php     # API request handling
│   ├── Models
│   │   └── User.php              # User model
│   ├── Routes
│   │   └── api.php               # API routes definition
│   ├── Middleware
│   │   └── AuthMiddleware.php     # Authentication handling
│   └── Services
│       └── Database.php           # Database connection management
├── protected
│   ├── config
│   │   ├── config.php             # Application configuration settings
│   │   └── settings.php           # Additional application settings
│   └── db
│       └── database.sqlite         # SQLite database file
├── tests
│   └── ExampleTest.php            # Example unit tests
├── composer.json                   # Composer dependencies and autoloading
├── phpunit.xml                    # PHPUnit configuration
├── .env                            # Environment variables
└── README.md                       # Project documentation
```

## Installation
1. Clone the repository:
   ```
   git clone <repository-url>
   ```
2. Navigate to the project directory:
   ```
   cd php-rest-sqlite-backend
   ```
3. Install dependencies using Composer:
   ```
   composer install
   ```

## Configuration
- Update the `protected/config/config.php` file with your database connection details and other configuration settings.
- Set environment variables in the `.env` file as needed.

## Usage
- Start the PHP built-in server:
  ```
  php -S localhost:8000 -t public
  ```
- Access the API at `http://localhost:8000`.

## Testing
- Run tests using PHPUnit:
  ```
  ./vendor/bin/phpunit
  ```

## License
This project is licensed under the MIT License. See the LICENSE file for more details.