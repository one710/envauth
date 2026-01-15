# Phast Framework Skeleton

A skeleton application for [Phast Framework](https://github.com/phastasf/framework) - a lightweight, modern PHP framework built on PSR standards.

## Features

This skeleton application provides a clean starting point for building applications with Phast Framework, including:

- ✅ **PSR Standards**: Full compliance with PSR-7, PSR-11, PSR-15, PSR-3, PSR-6, PSR-16, and PSR-20
- ✅ **Routing**: Example routes demonstrating controller actions and closures
- ✅ **Controllers**: Base controller with helper methods for rendering views, JSON responses, and redirects
- ✅ **Views**: Template rendering with Phew template engine
- ✅ **Console Commands**: CLI entrypoint for running console commands
- ✅ **Error Handling**: Centralized exception handling
- ✅ **Dependency Injection**: Service container integration
- ✅ **Docker Support**: Complete Docker setup with Traefik, MySQL, Redis, and development tools

## Requirements

- Docker and Docker Compose
- [mkcert](https://github.com/FiloSottile/mkcert) for local SSL certificates
- PHP 8.2 or higher (for local development without Docker)

## Installation

Setup project:

```bash
# create new project
composer create-project phastasf/app my-app

# go into project dir
cd my-app

# create .env file
cp .env.example .env
```

Create SSL certificates:

```bash
mkcert local.dev '*.local.dev' localhost 127.0.0.1 ::1
```

This will create certificate files (`local.dev+4.pem` and `local.dev+4-key.pem`) in the project root.

Add hostnames to `/etc/hosts`:

```bash
sudo nano /etc/hosts
```

Add the following lines:

```
127.0.0.1 web.local.dev
127.0.0.1 phpmyadmin.local.dev
127.0.0.1 mailcatcher.local.dev
127.0.0.1 redis-insight.local.dev
```

Start services:

```bash
docker compose up -d
```

## Usage

### Available Services

Once the Docker services are running, you can access:

- **Application**: [https://web.local.dev/](https://web.local.dev/)
- **Traefik Dashboard**: [http://localhost:8080](http://localhost:8080)
- **phpMyAdmin**: [https://phpmyadmin.local.dev/](https://phpmyadmin.local.dev/)
- **MailCatcher**: [https://mailcatcher.local.dev/](https://mailcatcher.local.dev/)
- **Redis Insight**: [https://redis-insight.local.dev/](https://redis-insight.local.dev/)

### Example Routes

- `GET /` - Home page (renders welcome view)
- `GET /health` - Health check endpoint
- `GET /error` - Error handling test route
- `GET /api/status` - API status endpoint (example of route groups)

### Docker Services

The Docker Compose setup includes:

- **web** - PHP 8.5 Apache container running the application
- **worker** - Queue worker container
- **mysql** - MySQL 8 database server
- **redis** - Redis 5 cache and queue backend
- **traefik** - Reverse proxy with automatic HTTPS
- **phpmyadmin** - Database management interface
- **mailcatcher** - Email testing tool
- **redis-insight** - Redis management interface

## Structure

The project structure is as explained below:

```
phast-app/
├── app/
│   ├── Commands/            # Console commands
│   ├── Controllers/         # Application controllers
│   ├── Events/              # Event classes
│   ├── Jobs/                # Queue jobs
│   ├── Middleware/          # Custom middleware
│   ├── Models/              # Database models
│   └── Providers/           # Service providers
├── config/                  # Configuration files (optional overrides)
├── database/
│   └── migrations/          # Database migrations
├── .docker/                 # Docker configuration files
│   └── vhost.conf           # Apache virtual host configuration
├── public/                  # Web server document root
│   ├── index.php            # Web entrypoint
│   └── .htaccess            # Apache rewrite rules
├── resources/
│   └── views/               # View templates
├── routes/
│   └── web.php              # Route definitions
├── storage/
│   ├── cache/               # Application cache
│   │   ├── app/             # Application-specific cache
│   │   ├── config.php       # Cached configuration
│   │   └── routes.php       # Cached routes
│   └── logs/                # Log files
├── console                  # CLI entrypoint
├── docker-compose.yml       # Docker Compose configuration
├── Dockerfile               # PHP container definition
├── traefik.yml              # Traefik configuration
└── composer.json
```

## Console Commands

### Generators

#### Create command

Generate a new console command:

```bash
docker compose exec web php console g:command CreateUser
```

#### Create controller

Generate a new controller:

```bash
docker compose exec web php console g:controller UserController
```

#### Create model

Generate a new model:

```bash
docker compose exec web php console g:model User
```

#### Create migration

Generate a new migration:

```bash
docker compose exec web php console g:migration create_users_table
```

#### Create job

Generate a new job class:

```bash
docker compose exec web php console g:job SendEmail
```

#### Create event

Generate a new event class:

```bash
docker compose exec web php console g:event UserRegistered
```

#### Create middleware

Generate a new middleware class:

```bash
docker compose exec web php console g:middleware CustomMiddleware
```

#### Create service provider

Generate a new service provider:

```bash
docker compose exec web php console g:provider CustomService
```

### Database

#### Run migrations

Run pending migrations:

```bash
docker compose exec web php console m:up
```

#### Rollback migrations

Rollback migrations (default: 1):

```bash
docker compose exec web php console m:down
```

Rollback multiple migrations:

```bash
docker compose exec web php console m:down 3
```

### Development

#### Start development server

Start the development server:

```bash
docker compose exec web php console serve
```

#### Run queue worker

Start a queue worker:

```bash
docker compose exec worker php console worker
```

#### Interactive shell

Start an interactive PHP shell (REPL) with container access:

```bash
docker compose exec web php console shell
```

#### Clear cache

Clear cached config, routes, and application cache:

```bash
docker compose exec web php console uncache
```

## Configuration

Configuration files can be placed in the `config/` directory to override framework defaults. The framework loads default configurations from the package and merges your project-specific overrides.

Environment variables are configured in the `.env` file. The `.env.example` file includes default values configured for the Docker setup:

- Database connection uses `mysql` as hostname (Docker service name)
- Redis connection uses `redis` as hostname (Docker service name)
- All services are accessible via `.local.dev` domain with SSL certificates

### Middleware

Middleware allows you to intercept and modify HTTP requests and responses. Create a custom middleware using the generator:

```bash
docker compose exec web php console g:middleware CustomMiddleware
```

This will create a middleware in `app/Middleware/` that implements `Psr\Http\Server\MiddlewareInterface`.

The `config/middleware.php` file is automatically copied from the framework during `composer install` or `composer update` if it doesn't exist. This file defines the middleware pipeline that processes HTTP requests:

```php
<?php

return [
    // Core middleware (required for framework to work)
    \Phast\Middleware\ErrorHandlerMiddleware::class,
    \Phast\Middleware\SessionMiddleware::class,
    // \Phast\Middleware\AuthMiddleware::class,  // Uncomment to enable authentication
    // Add your custom middleware here
    // \App\Middleware\CustomMiddleware::class,
    \Phast\Middleware\RoutingMiddleware::class,
    \Phast\Middleware\DispatcherMiddleware::class,
];
```

Middleware is executed in the order defined in the array. Core middleware must remain in their positions for the framework to function correctly.

### Service Providers

Service providers allow you to register services in the dependency injection container. Create a custom provider using the generator:

```bash
docker compose exec web php console g:provider CustomService
```

This will create a provider in `app/Providers/` that implements `Phast\Providers\ProviderInterface`. The provider has two methods:

- `provide(Container $container)` - Register services in the container
- `init(Container $container)` - Initialize services after all providers are registered

The `config/providers.php` file is automatically copied from the framework during `composer install` or `composer update` if it doesn't exist. You can customize it to add your own providers:

```php
<?php

return [
    // Framework providers (ConfigProvider must be first)
    \Phast\Providers\ConfigProvider::class,
    \Phast\Providers\CacheProvider::class,
    // ... other framework providers ...

    // Your custom providers
    \App\Providers\CustomServiceProvider::class,
];
```

### Database

The default database configuration in `.env.example`:

- **Host**: `mysql` (Docker service name)
- **Database**: `phastapp`
- **Username**: `phastapp`
- **Password**: `phastapp`

### Emails

MailCatcher is configured to catch all emails. Configure your `.env` to use SMTP:

```env
MAIL_DRIVER=smtp
MAIL_HOST=mailcatcher
MAIL_PORT=1025
```

## Development

### Viewing Logs

View application logs:

```bash
docker compose logs -f web
```

View worker logs:

```bash
docker compose logs -f worker
```

### Executing Commands

Run any PHP command in the web container:

```bash
docker compose exec web php console <command>
```

Access the container shell:

```bash
docker compose exec web bash
```

### Stopping Services

Stop all services:

```bash
docker compose down
```

Stop and remove volumes:

```bash
docker compose down -v
```

## Documentation

For complete documentation, visit the [Phast Framework documentation](https://github.com/phastasf/framework).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

Built with [Phast Framework](https://github.com/phastasf/framework).
