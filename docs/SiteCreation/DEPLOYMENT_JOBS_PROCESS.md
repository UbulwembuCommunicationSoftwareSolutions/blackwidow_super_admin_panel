# Deployment Jobs Process Documentation

## Overview
This document outlines the automated deployment process for customer subscriptions in the BlackWidow Super Admin Panel. The deployment process consists of multiple sequential jobs that are executed with specific timing intervals to ensure proper site setup and configuration.

## Job Execution Flow

### 1. CreateSiteOnForgeJob
**Timing:** Immediate (0 seconds)
**Purpose:** Creates the initial site on the Forge server
**Process:**
- Establishes the basic site infrastructure on Forge
- Sets up the initial site configuration
- Creates the site container and basic structure

**Technical Implementation:**
- **File:** `app/Jobs/SiteDeployment/CreateSiteOnForgeJob.php`
- **Constructor:** Accepts `$customerSubscriptionId`
- **Handle Method:** 
  - Instantiates `ForgeApi` helper
  - Retrieves `CustomerSubscription` model
  - Calls `ForgeApi::createSite($server_id, $customerSubscription)`
- **ForgeApi Integration:** Uses Laravel Forge SDK with API key from environment
- **Site Creation Payload:**
  - Domain configuration
  - Project type (Laravel, etc.)
  - PHP version (php83)
  - Nginx template ID
  - Git repository details
  - Database configuration (for subscription types 1, 2, 9, 10)

### 2. SyncForgeJob
**Timing:** 30 seconds after start
**Purpose:** Synchronizes the newly created site with Forge's systems
**Process:**
- Ensures the site is properly registered in Forge
- Syncs site metadata and configuration
- Verifies site creation was successful

**Technical Implementation:**
- **File:** `app/Jobs/SyncForgeJob.php`
- **Constructor:** No parameters required
- **Handle Method:**
  - Instantiates `ForgeApi` helper
  - Calls `ForgeApi::syncForge()`
- **Sync Process:**
  - Retrieves all `ForgeServer` records
  - Dispatches `GetSitesForServerJob` for each server
  - Maps Forge sites to customer subscriptions
  - Updates `forge_site_id` in database

### 3. AddGitRepoOnForgeJob
**Timing:** 60 seconds after start
**Purpose:** Connects the Git repository to the Forge site
**Process:**
- Links the source code repository to the deployment
- Sets up automatic deployment triggers
- Configures branch deployment settings

**Technical Implementation:**
- **File:** `app/Jobs/SiteDeployment/AddGitRepoOnForgeJob.php`
- **Constructor:** Accepts `$customerSubscriptionId`
- **Handle Method:**
  - Instantiates `ForgeApi` helper
  - Retrieves `CustomerSubscription` model
  - Calls `ForgeApi::sendGitRepository($customerSubscription)`
- **Git Configuration:**
  - Provider: GitHub
  - Repository: From `subscriptionType->github_repo`
  - Branch: From `subscriptionType->branch`
- **Forge API Call:** `installGitRepositoryOnSite()`

### 4. AddEnvVariablesOnForgeJob
**Timing:** 90 seconds after start
**Purpose:** Configures environment variables for the application
**Process:**
- Sets up database connections
- Configures application-specific variables
- Establishes security credentials

**Technical Implementation:**
- **File:** `app/Jobs/SiteDeployment/AddEnvVariablesOnForgeJob.php`
- **Constructor:** Accepts `$customerSubscriptionId`
- **Handle Method:**
  - Instantiates `ForgeApi` helper
  - Retrieves `CustomerSubscription` model
  - Calls `ForgeApi::addMissingEnv($customerSubscription)`
  - Calls `ForgeApi::sendEnv($customerSubscription)`
- **Environment Process:**
  - Collects required environment variables from `RequiredEnvVariables` model
  - Generates database credentials
  - Creates `.env` file content
  - Sends environment variables to Forge site

### 5. AddDeploymentScriptOnForgeJob
**Timing:** 120 seconds after start
**Purpose:** Deploys the custom deployment script to the site
**Process:**
- Uploads and configures deployment automation
- Sets up build and deployment processes
- Configures deployment hooks

**Technical Implementation:**
- **File:** `app/Jobs/SiteDeployment/AddDeploymentScriptOnForgeJob.php`
- **Constructor:** Accepts `$customerSubscriptionId`
- **Handle Method:**
  - Retrieves `CustomerSubscription` model
  - Instantiates `ForgeApi` helper
  - Checks for existing `DeploymentScript`
  - If no script exists:
    - Retrieves `DeploymentTemplate` by subscription type
    - Replaces `#WEBSITE_URL#` placeholder with domain
    - Creates/updates `DeploymentScript` record
  - Calls `ForgeApi::sendDeploymentScript()`
- **Template Processing:** Uses `DeploymentTemplate` with domain substitution
- **Forge API Call:** `updateSiteDeploymentScript()`

### 6. AddSSLOnSiteJob
**Timing:** 150 seconds after start
**Purpose:** Secures the site with SSL certificate
**Process:**
- Requests and installs SSL certificate
- Configures HTTPS redirects
- Ensures secure communication

**Technical Implementation:**
- **File:** `app/Jobs/SiteDeployment/AddSSLOnSiteJob.php`
- **Constructor:** Accepts `$customerSubscriptionId`
- **Handle Method:**
  - Retrieves `CustomerSubscription` model
  - Instantiates `ForgeApi` helper
  - Calls `ForgeApi::letsEncryptCertificate($customerSubscription)`
- **SSL Process:** Uses Let's Encrypt for free SSL certificates
- **Forge API Call:** Automatically handles SSL certificate generation and installation

### 7. DeploySite (First Deployment)
**Timing:** 180 seconds after start
**Purpose:** Performs the initial site deployment
**Process:**
- Executes the first deployment using configured scripts
- Builds the application
- Sets up the initial application state

**Technical Implementation:**
- **File:** `app/Jobs/SiteDeployment/DeploySite.php`
- **Constructor:** Accepts `$customerSubscriptionId`
- **Handle Method:**
  - Retrieves `CustomerSubscription` model
  - Instantiates `ForgeApi` helper
  - Updates `deployed_at` timestamp
  - Sets `deployed_version` from subscription type
  - Saves model changes
  - Calls `ForgeApi::deploySite($server_id, $forge_site_id)`
- **Deployment Tracking:** Records deployment timestamp and version
- **Forge API Call:** Triggers site deployment process

## Conditional Jobs (Subscription Types 1, 2, 9, 10, 11)

For specific subscription types, additional configuration jobs are executed:

### 8. Generate Application Key
**Timing:** 210 seconds after start
**Command:** `php artisan key:generate --force`
**Purpose:** Creates the Laravel application encryption key
**Process:**
- Generates a unique application key
- Updates the .env file with the new key
- Ensures secure application encryption

**Technical Implementation:**
- **File:** `app/Jobs/SendCommandToForgeJob.php`
- **Constructor:** Accepts `$customerSubscriptionId` and `$command`
- **Handle Method:**
  - Instantiates `ForgeApi` helper
  - Calls `ForgeApi::sendCommand($customerSubscriptionId, $command)`
- **Command Execution:** Uses Forge's `executeSiteCommand()` API
- **Force Flag:** `--force` ensures key generation even if key exists

### 9. Database Migration
**Timing:** 240 seconds after start
**Command:** `php artisan migrate --force`
**Purpose:** Sets up the database schema
**Process:**
- Runs all pending database migrations
- Creates necessary database tables
- Establishes database structure

**Technical Implementation:**
- **File:** `app/Jobs/SendCommandToForgeJob.php`
- **Command:** `php artisan migrate --force`
- **Force Flag:** `--force` ensures migrations run in production
- **Database Setup:** Creates all required tables and relationships

### 10. Database Seeding
**Timing:** 270 seconds after start
**Command:** `php artisan db:seed BaseLineSeeder --force`
**Purpose:** Populates the database with initial data
**Process:**
- Seeds baseline configuration data
- Sets up default user accounts
- Establishes initial application state

**Technical Implementation:**
- **File:** `app/Jobs/SendCommandToForgeJob.php`
- **Command:** `php artisan db:seed BaseLineSeeder --force`
- **Seeder:** Uses `BaseLineSeeder` for initial data population
- **Force Flag:** `--force` ensures seeding in production environment

### 11. DeploySite (Second Deployment)
**Timing:** 300 seconds after start
**Purpose:** Redeploys after database setup
**Process:**
- Ensures all changes are properly deployed
- Verifies database integration
- Finalizes application setup

**Technical Implementation:**
- **File:** `app/Jobs/SiteDeployment/DeploySite.php`
- **Same Implementation:** As first deployment job
- **Purpose:** Ensures database changes are properly deployed

### 12. System Configuration
**Timing:** 330 seconds after start
**Purpose:** Sends system configuration to the customer
**Process:**
- Configures customer-specific settings
- Sets up user access and permissions
- Establishes customer environment

**Technical Implementation:**
- **File:** `app/Jobs/SiteDeployment/SendSystemConfigJob.php`
- **Constructor:** Accepts `$customerId`
- **Handle Method:**
  - Retrieves `Customer` model
  - Instantiates `CMSService`
  - Finds console subscription (type 1)
  - Calls `CMSService::setConsoleSystemConfigs($console)`
- **CMS Integration:** Configures system settings through CMS service

### 13. Storage Link Creation
**Timing:** 360 seconds after start
**Command:** `php artisan storage:link`
**Purpose:** Creates symbolic link for file storage
**Process:**
- Links public storage directory
- Enables file uploads and downloads
- Configures public file access

**Technical Implementation:**
- **File:** `app/Jobs/SendCommandToForgeJob.php`
- **Command:** `php artisan storage:link`
- **Purpose:** Creates symbolic link from `public/storage` to `storage/app/public`

## Job Progress Tracking

Each job is tracked with:
- **Job ID:** Unique identifier for the dispatched job
- **Progress:** Initial progress value (0)
- **Timing:** Sequential execution with 30-second intervals

## Total Deployment Time

- **Basic Deployments:** 3 minutes (180 seconds)
- **Full Deployments:** 6 minutes (360 seconds) for subscription types 1, 2, 9, 10, 11

## Error Handling

Jobs are designed to fail gracefully and can be retried. The system includes:
- Automatic retry mechanisms
- Progress tracking for monitoring
- Notification system for deployment status

## Monitoring and Notifications

- Success notifications are sent upon completion
- Job progress is stored in JSON format
- Deployment status can be monitored through the admin panel

## Dependencies

Each job depends on the successful completion of previous jobs:
- Site creation must complete before synchronization
- Git repository must be connected before deployment
- Environment variables must be set before application deployment
- Database setup must complete before final deployment

## Technical Architecture

### Job Structure
All jobs implement Laravel's `ShouldQueue` interface and use:
- `Dispatchable` trait for job dispatching
- `InteractsWithQueue` trait for queue interaction
- `Queueable` trait for queue configuration
- `SerializesModels` trait for model serialization

### ForgeApi Helper
Central helper class that:
- Manages Laravel Forge SDK integration
- Handles all Forge API calls
- Provides consistent interface for site operations
- Manages API key authentication

### Database Models
Key models involved:
- `CustomerSubscription`: Main subscription record
- `DeploymentScript`: Custom deployment scripts
- `DeploymentTemplate`: Template-based scripts
- `RequiredEnvVariables`: Environment variable definitions
- `ForgeServer`: Server configuration

## Best Practices

1. **Timing Intervals:** 30-second intervals ensure proper sequencing
2. **Error Handling:** Each job includes proper error handling
3. **Monitoring:** Progress tracking enables deployment monitoring
4. **Rollback:** Failed deployments can be rolled back and restarted
5. **Logging:** Comprehensive logging for troubleshooting
6. **Queue Management:** Jobs use Laravel's queue system for reliability
7. **API Integration:** Centralized Forge API management through helper class

## Troubleshooting

Common issues and solutions:
- **Job Failures:** Check job logs and retry mechanisms
- **Timing Issues:** Verify server performance and job queue status
- **Configuration Errors:** Validate environment variables and deployment scripts
- **Database Issues:** Ensure proper database connectivity and permissions
- **Forge API Issues:** Verify API key and rate limiting
- **Git Repository Issues:** Check repository access and branch existence

## Console Commands

Additional console commands available for manual operations:
- `app:create-site-on-forge {customer-subscription-id}`
- `app:complete-creation {customer-subscription-id}`
- `app:send-command-to-site {site-id}`
- `app:send-env-to-all-consoles`
- `app:send-site-git-repository {customer-subscription-id}`
- `app:send-site-deployment-script {customer-subscription-id}`
