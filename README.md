# RegTracker - Live Regulatory Change Tracker for MTOs

RegTracker is a production-grade Laravel 12 application that monitors regulatory compliance changes for Money Transfer Operators (MTOs) across multiple jurisdictions.

## Features

- **Multi-Source Monitoring**: Tracks OFAC, UK Sanctions, UN Sanctions, EU Sanctions, DFAT, AUSTRAC, FCA, FINTRAC, and Federal Register
- **Automated Change Detection**: Diff-based engine to detect regulatory updates
- **AI Classification**: Google Gemini API integration for intelligent change categorization
- **Action Briefs**: Auto-generated compliance action items for each regulatory change
- **Email Alerts**: Instant critical alerts and daily digest emails for MTO operators
- **Admin Dashboard API**: Manage regulatory sources, QA classify changes, and monitor scraper health
- **MTO Owner Dashboard API**: Track compliance alerts, manage action items, and view audit trails
- **Sanctum Authentication**: Secure token-based API authentication for MTO users

## Tech Stack

- **Framework**: Laravel 12
- **Language**: PHP 8.2+
- **Database**: MySQL 8.0+
- **Queue**: Database-backed jobs
- **Mail**: SMTP/SES support
- **External APIs**: Google Gemini API (free tier)

## Quick Start

### Installation

```bash
# Clone/setup project
cd /sessions/beautiful-wonderful-keller/regtracker

# Install dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Create database
mysql -u root -p -e "CREATE DATABASE regtracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
php artisan migrate

# Seed initial data
php artisan db:seed

# Start development server
php artisan serve
```

### Environment Variables

Edit `.env`:
- `DB_*`: MySQL credentials
- `MAIL_*`: SMTP settings (use Mailtrap or SES for production)
- `ADMIN_SECRET`: Bearer token for /api/admin routes
- `GEMINI_API_KEY`: Get from https://ai.google.dev (free tier available)

## Running Scrapers

### Manual Execution

```bash
# Run individual scrapers
php artisan scrape:ofac
php artisan scrape:uk-sanctions
php artisan scrape:un-sanctions
php artisan scrape:eu-sanctions
php artisan scrape:dfat

# Monitor sources
php artisan monitor:austrac
php artisan monitor:fca
php artisan monitor:fintrac
php artisan monitor:federal-register

# Health check
php artisan health:check

# Daily digest
php artisan alerts:daily-digest
```

### Scheduled Execution

All scrapers are scheduled in `app/Console/Kernel.php`:
- **Sanctions lists** (OFAC, UK, UN, EU, DFAT): Every 6 hours
- **Guidance monitors** (AUSTRAC, FCA, FINTRAC, Federal Register): Daily at 8 AM
- **Health check**: Every 6 hours (30 min after scrapers)
- **Daily digest emails**: Daily at 7 AM

To run the scheduler:
```bash
php artisan schedule:run
```

For production, add to crontab:
```
* * * * * cd /path/to/regtracker && php artisan schedule:run >> /dev/null 2>&1
```

## API Documentation

### Admin Routes (Bearer token required in Authorization header)

```
POST   /api/auth/login                  # Login MTO user
POST   /api/auth/logout                 # Logout (token revoke)

GET    /api/admin/mtos                  # List all MTO profiles
POST   /api/admin/mtos                  # Create MTO profile
GET    /api/admin/mtos/{id}             # Show MTO profile
PUT    /api/admin/mtos/{id}             # Update MTO profile
DELETE /api/admin/mtos/{id}             # Delete MTO profile

GET    /api/admin/changes               # List all changes (with QA status)
GET    /api/admin/changes/{id}          # Show change details
POST   /api/admin/changes/{id}/approve  # Approve change
POST   /api/admin/changes/{id}/dismiss  # Dismiss change
POST   /api/admin/changes/{id}/preview-alert  # Preview alert for MTO

GET    /api/admin/health                # All sources with last status
GET    /api/admin/health/{sourceId}     # Health history for source

GET    /api/admin/fatf                  # Current FATF grey/blacklists
POST   /api/admin/fatf                  # Update FATF lists
```

### MTO User Routes (Sanctum auth required)

```
GET    /api/mto/dashboard               # Dashboard summary
GET    /api/mto/alerts                  # Paginated alerts for this MTO
GET    /api/mto/alerts/{id}             # Full alert with action items
PUT    /api/mto/actions/{id}            # Update action item status
GET    /api/mto/compliance-log          # Audit trail (filterable by date)
GET    /api/mto/compliance-log/export   # Export as JSON
```

## Deployment to Railway

1. Connect your GitHub repo to Railway
2. Set environment variables in Railway dashboard
3. Railway will automatically:
   - Install PHP dependencies via Nixpacks
   - Run migrations on deploy
   - Start the Laravel server

See `railway.json` for configuration.

## Database Schema

### Core Tables

- `regulatory_sources` - Monitored regulatory feeds (OFAC, UK, UN, EU, DFAT, etc.)
- `raw_snapshots` - Raw content snapshots from each source
- `detected_changes` - Parsed, classified regulatory changes
- `sanctions_entries` - Individual sanctioned individuals/entities/countries
- `action_items` - Generated compliance action items per change
- `mto_profiles` - Money Transfer Operator customer profiles
- `mto_users` - Login credentials for MTO staff
- `mto_alerts` - When MTO is alerted to a change
- `mto_action_progress` - Status tracking for each action item per MTO
- `scraper_health` - Health/error logs for each scraper run

## Key Services

### DiffService
Detects changes between old and new regulatory content. Handles XML parsing (OFAC, UK, UN, EU) and text-based comparisons (guidance documents).

### QAClassifierService
Uses Google Gemini API to classify changes by:
- Change type (sanctions_update, kyc_threshold, aml_policy, etc.)
- Severity (critical, high, medium, low)
- Affected jurisdictions and corridors
- Confidence score for auto-approval

### ActionBriefService
Generates 3-6 specific compliance action items per change type with:
- Required/optional classification
- Applicable jurisdictions/corridors
- Deadline (days from effective date)

### MtoMatcherService
Identifies which MTOs are affected by each change based on:
- Licensed jurisdictions
- Active corridors
- All critical/global changes

### NotificationService
Sends email alerts via Laravel Mail with:
- Instant critical alerts
- Daily digest emails (12+ hours old, MEDIUM/LOW severity)
- Admin failure alerts

## Error Handling

- **Scraper failures**: Logged to `scraper_health` table; admin alerted if 2 consecutive failures
- **API classification failures**: Falls back to keyword-based classification
- **Email failures**: Logged; queued for retry via database queue
- **Database failures**: All commands include transaction rollback on error

## Security

- Admin routes protected by Bearer token (ADMIN_SECRET)
- MTO routes protected by Laravel Sanctum tokens
- All inputs validated and sanitized
- Password hashing using bcrypt
- API rate limiting recommended for production

## Monitoring

Check `/api/admin/health` to monitor:
- Last scrape time for each source
- Scraper failures and error messages
- Records fetched and changes detected per run
- Admin alerts sent for critical failures

## Support

For issues or questions, contact: vivek@remitso.com
