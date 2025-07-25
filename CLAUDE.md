# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP-based URL shortener system that allows users to create, manage, and track short URLs. The system includes user authentication, click analytics, QR code generation, and administrative features with domain/keyword blacklisting.

## Database Setup

The application uses MySQL/MariaDB with the following configuration:
- Database name: `url_jp_to4_cn`
- Import the schema from `database.sql` to set up all required tables
- Default admin credentials: username `admin`, password `admin123`

## Core Architecture

### Database Layer (`config.php`)
- **Database class**: Singleton pattern PDO wrapper with common CRUD operations
- Connection configuration constants (DB_HOST, DB_NAME, DB_USER, DB_PASS)
- Utility functions for password hashing, IP detection, short code generation

### API Layer (`api.php`)
- RESTful API endpoints for URL shortening, user management, and statistics
- Routes:
  - `POST /api/shorten` - Create short URL
  - `POST /api/register` - User registration  
  - `POST /api/login` - User authentication
  - `GET /api/stats` - URL statistics
  - `GET /api/user-urls` - User's URL list
  - `GET /api/global-stats` - Global system statistics

### Core Components

1. **URL Shortening** (`api.php:39-105`)
   - Generates unique 6-character short codes
   - Validates URLs and checks domain/keyword blacklists
   - Supports optional titles and user association

2. **Analytics** (`redirect.php`)
   - Tracks clicks with IP, user agent, referer
   - Detects device type (desktop/mobile/tablet)
   - Identifies browser and operating system
   - Updates click counters

3. **Authentication** (`login.php`, `register.php`)
   - Session-based user management
   - Password hashing with PHP's password_hash()
   - User registration with email validation

4. **QR Code Generation** (`qr.php`)
   - Uses Google Charts API as primary method
   - Falls back to simple GD library image on failure
   - Configurable size parameter (100-500px)

### Database Schema

Key tables:
- `users` - User accounts with admin flags
- `short_urls` - URL mappings with metadata
- `click_stats` - Detailed analytics per click
- `domain_blacklist` - Blocked domains
- `keyword_blacklist` - Blocked keywords  
- `settings` - System configuration

## File Structure

- `index.php` - Main homepage with URL shortening form
- `config.php` - Database configuration and utility functions
- `api.php` - RESTful API endpoints
- `login.php` / `register.php` - Authentication pages
- `redirect.php` - Short URL redirect handler (expects `?code=` parameter)
- `qr.php` - QR code image generator
- `404.html` - 404 error page
- `database.sql` - Complete database schema

## Development Commands

This is a vanilla PHP application with no build system or package manager. Common development tasks:

### Local Development
```bash
# Start PHP built-in server for development
php -S localhost:8000

# Or use any web server pointing to the project root
# Apache/Nginx should be configured to serve PHP files
```

### Database Management
```bash
# Import database schema
mysql -u username -p url_jp_to4_cn < database.sql

# Create database backup
mysqldump -u username -p url_jp_to4_cn > backup.sql
```

### Configuration Setup
1. Copy and modify database credentials in `config.php`
2. Update `SITE_URL` constant to match your domain
3. Change security keys (`JWT_SECRET`, `PASSWORD_SALT`) for production

### Deployment
- Ensure PHP 7.4+ with PDO MySQL extension
- Web server (Apache/Nginx) with PHP support
- MySQL/MariaDB database server
- Set proper file permissions for web server access
- Configure virtual host to point to project root

## Development Notes

- No build system, linting, or testing framework configured
- Uses Bootstrap 5.1.3 and Bootstrap Icons via CDN
- Session management handles user state
- URL routing uses simple switch statements on REQUEST_METHOD
- Error responses use appropriate HTTP status codes with JSON

## Security Features

- SQL injection prevention via prepared statements
- Password hashing using PHP's password_hash()
- Domain and keyword blacklisting system
- URL validation before shortening
- CORS headers configured in API

## Configuration

Key constants in `config.php`:
- `SITE_URL` - Base URL for short links
- `SHORT_CODE_LENGTH` - Length of generated codes (default: 6)
- `SHORT_CODE_CHARS` - Character set for code generation
- Database connection parameters

## Debugging and Troubleshooting

### Common Issues
- **Database connection errors**: Check credentials in `config.php` and ensure MySQL service is running
- **404 errors on short URLs**: Verify `redirect.php` is accessible and URL rewriting is configured
- **Session issues**: Ensure PHP sessions are enabled and session directory is writable
- **API errors**: Check browser developer tools for CORS or JSON response errors

### Log Locations
- PHP errors: Check web server error logs (typically `/var/log/apache2/error.log` or `/var/log/nginx/error.log`)
- Database errors: Check MySQL error logs
- Application uses `die()` statements for database connection failures

### URL Structure
- Short URLs: `SITE_URL/SHORT_CODE` (requires URL rewriting to `redirect.php?code=SHORT_CODE`)
- API endpoints: `SITE_URL/api.php?path=ENDPOINT`
- QR codes: `SITE_URL/qr.php?code=SHORT_CODE&size=SIZE`