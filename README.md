# Envauth

A license verification server for Envato (CodeCanyon) products built with [Phast Framework](https://github.com/phastasf/framework). Envauth provides secure license activation and management for both client-side applications (machine ID binding) and server-side scripts (IP address binding).

## Features

- ✅ **Dual Verification Types**:
    - **Machine ID Binding**: For apps/tools that generate unique machine IDs. Licenses are bound to a specific machine ID upon first activation.
    - **IP Address Binding**: For server-side PHP scripts. Licenses are bound to the IP address from which the activation request originates.

- ✅ **Envato Integration**:
    - Purchase code verification via Envato API
    - OAuth 2.0 authentication for license reset functionality
    - Automatic purchase verification using Envato Personal Access Token

- ✅ **License Management**:
    - Secure license activation with binding enforcement
    - Web-based license reset portal
    - OAuth-protected license unlock functionality
    - Activation history tracking

- ✅ **Security**:
    - Automatic IP address detection (client-provided IPs are ignored)
    - CSRF protection for OAuth flow
    - Secure session management
    - Trusted proxy support for accurate IP detection

- ✅ **PSR Standards**: Full compliance with PSR-7, PSR-11, PSR-15, PSR-3, PSR-6, PSR-16, PSR-18, and PSR-20

## Requirements

- Docker and Docker Compose
- [mkcert](https://github.com/FiloSottile/mkcert) for local SSL certificates
- PHP 8.2 or higher (for local development without Docker)
- Envato OAuth App credentials
- Envato Personal Access Token with "View your items' sales history" permission

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url> envauth
cd envauth
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Create SSL Certificates

```bash
mkcert local.dev '*.local.dev' localhost 127.0.0.1 ::1
```

This will create certificate files (`local.dev+4.pem` and `local.dev+4-key.pem`) in the project root.

### 4. Configure Hostnames

Add the following lines to `/etc/hosts`:

```
127.0.0.1 web.local.dev
127.0.0.1 phpmyadmin.local.dev
```

### 5. Configure Environment Variables

Create a `.env` file (or copy from `.env.example` if available) and configure:

```env
# Database
DB_HOST=mysql
DB_DATABASE=envauth
DB_USERNAME=envauth
DB_PASSWORD=envauth

# Envato OAuth
ENVATO_OAUTH_CLIENT_ID=your_client_id
ENVATO_OAUTH_CLIENT_SECRET=your_client_secret
ENVATO_OAUTH_REDIRECT_URI=https://web.local.dev/oauth/callback

# Envato Personal Access Token
ENVATO_PERSONAL_TOKEN=your_personal_token
```

### 6. Configure Envato Items

Edit `config/envato.php` to map your Envato item IDs to their verification types:

```php
'items' => [
    '12345678' => 'machine_id',  // For apps/tools
    '87654321' => 'ip_address',  // For server-side scripts
],
```

### 7. Start Services

```bash
docker compose up -d
```

### 8. Run Migrations

```bash
docker compose exec web php console m:up
```

## Configuration

### Envato OAuth App Setup

1. Create an OAuth app in your Envato account
2. Configure the following scopes:
    - View and search Envato sites
    - View your Envato Account username
    - View your email address
    - View your purchases of the app creator's items
3. Set the redirect URI to match `ENVATO_OAUTH_REDIRECT_URI` in your `.env`

### Envato Personal Access Token

1. Generate a Personal Access Token in your Envato account
2. Ensure it has the **"View your items' sales history"** permission (scope: `sale:history`)
3. Add it to your `.env` as `ENVATO_PERSONAL_TOKEN`

### Item Configuration

In `config/envato.php`, map each Envato item ID to its verification type:

- `'machine_id'`: For client-side applications that generate unique machine IDs
- `'ip_address'`: For server-side PHP scripts that should be bound to IP addresses

## API Documentation

### Verify License

**Endpoint**: `POST /api/license/verify`

Verifies an Envato purchase code and activates the license with appropriate binding.

#### Request Body

```json
{
    "purchase_code": "YOUR_ENVATO_PURCHASE_CODE",
    "item_id": "12345678",
    "machine_id": "unique-machine-id-12345" // Optional, required for machine_id items
}
```

#### Response (Success)

```json
{
    "success": true,
    "message": "License verified and activated successfully"
}
```

#### Response (Error)

```json
{
    "success": false,
    "message": "Error message",
    "errors": {} // Only present for validation errors
}
```

#### Status Codes

- `200`: License verified and activated successfully
- `400`: Invalid request (validation error, invalid item ID, purchase verification failed)
- `403`: License activation failed (inactive license, already activated, binding mismatch)

#### Notes

- For `machine_id` items: `machine_id` parameter is required
- For `ip_address` items: IP address is automatically detected from request headers
- Client-provided IP addresses are ignored for security
- The `item_id` must be configured in `config/envato.php`

### Health Check

**Endpoint**: `GET /health`

Returns `OK` if the server is running.

## Web Interface

### License Reset Portal

**URL**: `/license/reset`

A web-based interface that allows users to reset/unlock their licenses:

1. Users visit `/license/reset`
2. If not logged in, they are redirected to Envato OAuth
3. After authentication, users can enter their Envato purchase code
4. The system verifies the purchase code belongs to the logged-in user via Envato API
5. The system verifies the purchase code matches the item ID in the license record
6. If verification succeeds, all existing activations are deactivated, allowing reactivation on a new machine/IP address

### OAuth Endpoints

- `GET /oauth/login`: Initiates OAuth login flow
- `GET /oauth/callback`: Handles OAuth callback from Envato
- `GET /oauth/logout`: Logs out the current user

## Project Structure

```
envauth/
├── app/
│   ├── Controllers/              # HTTP controllers
│   │   ├── HomeController.php
│   │   ├── LicenseController.php
│   │   ├── LicenseVerificationController.php
│   │   └── OAuthController.php
│   ├── Exceptions/               # Custom exceptions
│   │   ├── InvalidItemIdException.php
│   │   ├── InvalidProductTypeException.php
│   │   ├── IpAddressRequiredException.php
│   │   ├── LicenseAlreadyActivatedException.php
│   │   ├── LicenseInactiveException.php
│   │   ├── LicenseNotFoundException.php
│   │   ├── MachineIdRequiredException.php
│   │   └── PurchaseVerificationFailedException.php
│   ├── Models/                   # Database models
│   │   ├── Activation.php
│   │   ├── License.php
│   │   ├── LicenseReset.php
│   │   └── OAuthUser.php
│   ├── Providers/                # Service providers
│   │   └── LicenseServiceProvider.php
│   └── Services/                 # Business logic services
│       ├── LicenseVerificationService.php
│       └── OAuthService.php
├── config/                       # Configuration files
│   ├── envato.php               # Envato API and OAuth config
│   ├── middleware.php           # Middleware pipeline
│   └── providers.php            # Service providers
├── database/
│   └── migrations/              # Database migrations
├── public/                      # Web server document root
│   └── index.php               # Web entrypoint
├── resources/
│   └── views/                   # View templates
│       ├── home.phtml
│       └── license/
│           └── reset.phtml
├── routes/
│   └── web.php                  # Route definitions
├── storage/
│   ├── cache/                   # Application cache
│   └── logs/                    # Log files
├── console                       # CLI entrypoint
├── docker-compose.yml           # Docker Compose configuration
├── Dockerfile                   # PHP container definition
├── traefik.yml                  # Traefik configuration
└── composer.json
```

## Database Schema

### Tables

- **licenses**: Stores license information (purchase code, item ID, product type, active status)
- **activations**: Tracks license activations (machine ID or IP address binding)
- **oauth_users**: Stores OAuth user information for license reset functionality
- **license_resets**: Logs license reset operations

## Development

### Available Services

Once Docker services are running:

- **Application**: [https://web.local.dev/](https://web.local.dev/)
- **Traefik Dashboard**: [http://localhost:8080](http://localhost:8080)
- **phpMyAdmin**: [https://phpmyadmin.local.dev/](https://phpmyadmin.local.dev/)

### Console Commands

#### Database Migrations

```bash
# Run migrations
docker compose exec web php console m:up

# Rollback migrations
docker compose exec web php console m:down

# Rollback multiple migrations
docker compose exec web php console m:down 3
```

#### Generate Code

```bash
# Generate controller
docker compose exec web php console g:controller MyController

# Generate model
docker compose exec web php console g:model MyModel

# Generate migration
docker compose exec web php console g:migration create_my_table

# Generate service provider
docker compose exec web php console g:provider MyServiceProvider
```

#### Development Tools

```bash
# Clear cache
docker compose exec web php console uncache

# Interactive shell
docker compose exec web php console shell

# View logs
docker compose logs -f web
```

### Testing the API

A Postman collection is included: `Envauth.postman_collection.json`

Import it into Postman to test the API endpoints.

## How It Works

### License Verification Flow

1. **Client Request**: Client application sends purchase code, item ID, and optionally machine ID to `/api/license/verify`
2. **Purchase Verification**: Server verifies the purchase code with Envato API using Personal Access Token
3. **Item Validation**: Server checks if the item ID is configured and determines verification type
4. **Binding Check**:
    - For `machine_id` items: Checks if license is already bound to a different machine ID
    - For `ip_address` items: Checks if license is already bound to a different IP address
5. **Activation**: If valid, creates/updates license record and activation record
6. **Response**: Returns success or appropriate error message

### License Reset Flow

1. **User Access**: User visits `/license/reset`
2. **OAuth Login**: If not authenticated, redirects to Envato OAuth
3. **Reset Request**: User enters purchase code
4. **Ownership Verification**: System verifies the purchase code belongs to the logged-in OAuth user via Envato API
5. **Item Validation**: System verifies the purchase code matches the item ID in the license record
6. **Unlock**: System deactivates all existing activations for the license
7. **Logging**: Reset operation is logged for audit purposes

## Security Considerations

- **IP Address Detection**: IP addresses are automatically detected from request headers. Client-provided IPs are ignored to prevent spoofing.
- **Purchase Ownership Verification**: License resets require OAuth authentication and verify that the purchase code belongs to the logged-in user via Envato API.
- **Trusted Proxies**: Configure trusted proxies in `config/proxies.php` if running behind a reverse proxy.
- **CSRF Protection**: OAuth flow uses state parameter for CSRF protection.
- **Session Security**: Sessions use secure cookies with SameSite=Lax.
- **PSR-20 Clock Interface**: All date/time operations use PSR-20 ClockInterface for testability and consistency.

## Troubleshooting

### OAuth Issues

- Ensure OAuth scopes are correctly configured in Envato
- Verify redirect URI matches exactly in both Envato and `.env`
- Check that session cookies are working (check browser console)

### Purchase Verification Fails

- Verify Personal Access Token has "View your items' sales history" permission
- Check that the purchase code is valid and belongs to the configured item
- Ensure item ID is correctly mapped in `config/envato.php`
- For license resets: Ensure the purchase code belongs to the logged-in OAuth user's account
- For license resets: Verify the OAuth access token is valid and not expired

### IP Address Detection Issues

- If behind a proxy, configure trusted proxies in `config/proxies.php`
- Check that `ClientIpMiddleware` is in the middleware stack
- Verify `X-Forwarded-For` or `X-Real-IP` headers are being set by your proxy

## License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

Built with [Phast Framework](https://github.com/phastasf/framework) - a lightweight, modern PHP framework built on PSR standards.
