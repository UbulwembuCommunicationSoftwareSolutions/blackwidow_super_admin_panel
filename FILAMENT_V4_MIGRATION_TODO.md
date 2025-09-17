# Filament v4 Migration Todo List

## Overview

This document outlines the migration tasks for updating all Filament Resources from v3 to v4 structure. The new v4 resources have been created with the template structure but are missing the functionality from the old resources.

## Migration Tasks

### 1. CustomerSubscriptionResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Fields:**

    -   [ ] `url` (required)
    -   [ ] `domain` (required)
    -   [ ] `app_name` (required)
    -   [ ] `customer_id` (relationship to customer.company_name)
    -   [ ] `subscription_type_id` (relationship to subscriptionType.name)
    -   [ ] `deployed_version` (maxLength: 8, nullable)
    -   [ ] `logo_1` through `logo_5` (FileUpload, image, directory: 'logos')
    -   [ ] `database_name`
    -   [ ] `forge_site_id`
    -   [ ] `panic_button_enabled` (Toggle, label: 'Panic Button')

-   [ ] **Methods:**

    -   [ ] `isThisAppTypeSubscription($subscriptionTypeId)` - Check if subscription type is app type (IDs: 3,4,5,6,7)
    -   [ ] `sendEnvs(Collection $collection)` - Dispatch SendEnvToForge jobs

-   [ ] **Relations:**

    -   [ ] DeploymentScriptRelationManager
    -   [ ] EnvVariablesRelationManager

-   [ ] **Navigation:**
    -   [ ] Group: 'Customers'
    -   [ ] Slug: 'customer-subscriptions'

---

### 2. CustomerResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Sections:**

    -   [ ] Company Details section with reactive toggles
    -   [ ] Level descriptions with conditional visibility based on toggles
    -   [ ] `level_one_in_use`, `level_two_in_use`, `level_three_in_use` (reactive toggles)
    -   [ ] `max_users` (numeric, minValue: 1)

-   [ ] **Table Configuration:**

    -   [ ] `customer_subscriptions_count` column (label: 'Subscriptions')
    -   [ ] TrashedFilter
    -   [ ] Soft delete actions (RestoreAction, ForceDeleteAction)

-   [ ] **Query Logic:**

    -   [ ] Role-based query for `customer_manager` users
    -   [ ] Eager loading of customerSubscriptions with count

-   [ ] **Relations:**

    -   [ ] CustomerSubscriptionsRelationManager (alias: 'subscriptions')
    -   [ ] CustomerUserRelationManager (alias: 'users')

-   [ ] **Global Search:**

    -   [ ] Searchable attributes: ['company_name']

-   [ ] **Navigation:**
    -   [ ] Group: 'Customers'
    -   [ ] Slug: 'customers'

---

### 3. CustomerUserResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Fields:**

    -   [ ] `customer_id` (required, integer)
    -   [ ] `email_address` (required)
    -   [ ] `password` (required)
    -   [ ] `first_name` (required)
    -   [ ] `last_name` (required)
    -   [ ] `is_system_admin` (Toggle, label: 'Is Super Admin')
    -   [ ] `created_at` and `updated_at` placeholders

-   [ ] **Table Configuration:**

    -   [ ] `customer.company_name` column
    -   [ ] `email_address`, `first_name`, `last_name` columns

-   [ ] **Query Logic:**

    -   [ ] Role-based query for `customer_manager` users
    -   [ ] Soft delete scope handling

-   [ ] **Navigation:**
    -   [ ] Group: 'Customers'
    -   [ ] Slug: 'customer-users'
    -   [ ] `shouldRegisterNavigation = false`

---

### 4. DeploymentScriptResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Fields:**

    -   [ ] `script` (Textarea, required)
    -   [ ] `customer_subscription_id` (required, integer)
    -   [ ] `created_at` and `updated_at` placeholders

-   [ ] **Table Configuration:**

    -   [ ] `script` and `customer_subscription_id` columns

-   [ ] **Navigation:**
    -   [ ] Group: 'System Administration'
    -   [ ] Slug: 'deployment-scripts'

---

### 5. DeploymentTemplateResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Fields:**

    -   [ ] `script` (required)
    -   [ ] `subscription_type_id` (relationship to subscriptionType.name, searchable, required)
    -   [ ] `created_at` and `updated_at` placeholders

-   [ ] **Table Configuration:**

    -   [ ] `script` and `subscriptionType.name` columns (searchable, sortable)

-   [ ] **Global Search:**

    -   [ ] Searchable attributes: ['subscriptionType.name']
    -   [ ] Global search query with subscriptionType relationship
    -   [ ] Global search result details

-   [ ] **Navigation:**
    -   [ ] Group: 'System Administration'
    -   [ ] Slug: 'deployment-templates'

---

### 6. EnvVariablesResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Fields:**

    -   [ ] `key` (required)
    -   [ ] `value` (required)
    -   [ ] `customer_subscription_id` (required, integer)
    -   [ ] `created_at` and `updated_at` placeholders

-   [ ] **Table Configuration:**

    -   [ ] `key`, `value`, `customer_subscription_id` columns

-   [ ] **Navigation:**
    -   [ ] Group: 'System Administration'
    -   [ ] Slug: 'env-variables'

---

### 7. ForgeServerResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Fields:**

    -   [ ] `forge_server_id` (required, integer)
    -   [ ] `name`
    -   [ ] `ip_address`
    -   [ ] `created_at` and `updated_at` placeholders

-   [ ] **Table Configuration:**

    -   [ ] `forge_server_id`, `name` (searchable, sortable), `ip_address` columns

-   [ ] **Global Search:**

    -   [ ] Searchable attributes: ['name']

-   [ ] **Navigation:**
    -   [ ] Group: 'System Administration'
    -   [ ] Slug: 'forge-servers'

---

### 8. NginxTemplateResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Fields:**

    -   [ ] `name` (required)
    -   [ ] `server_id` (Select with ForgeServer options: pluck('name','forge_server_id'), required)
    -   [ ] `template_id` (required, integer)
    -   [ ] `created_at` and `updated_at` placeholders

-   [ ] **Table Configuration:**

    -   [ ] `name` (searchable, sortable), `template_id` columns

-   [ ] **Global Search:**

    -   [ ] Searchable attributes: ['name']

-   [ ] **Navigation:**
    -   [ ] Group: 'System Administration'
    -   [ ] Slug: 'nginx-templates'

---

### 9. RequiredEnvVariablesResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Fields:**

    -   [ ] `key` (required)
    -   [ ] `value` (required)
    -   [ ] `subscription_type_id` (relationship to subscriptionType.name, required)
    -   [ ] `created_at` and `updated_at` placeholders

-   [ ] **Table Configuration:**

    -   [ ] `key`, `value`, `subscriptionType.name` (sortable) columns

-   [ ] **Filters:**

    -   [ ] SubscriptionType filter with Select component and SubscriptionType options

-   [ ] **Global Search:**

    -   [ ] Searchable attributes: ['subscriptionType.name']
    -   [ ] Global search query with subscriptionType relationship
    -   [ ] Global search result details

-   [ ] **Navigation:**
    -   [ ] Group: 'System Administration'
    -   [ ] Slug: 'required-env-variables'

---

### 10. RoleResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Shield Integration:**

    -   [ ] Implement `HasShieldPermissions` interface
    -   [ ] Use `HasShieldFormComponents` trait
    -   [ ] Shield form components (ShieldSelectAllToggle, Shield form components)
    -   [ ] Shield table columns (permissions_count badge, team column)
    -   [ ] Shield navigation settings

-   [ ] **Form Components:**

    -   [ ] `name` field with unique validation
    -   [ ] `guard_name` field with default value
    -   [ ] Team selection (conditional based on tenancy)
    -   [ ] Shield permission components

-   [ ] **Table Configuration:**

    -   [ ] Shield-specific columns (name, guard_name, team, permissions_count, updated_at)
    -   [ ] Shield-specific actions and bulk actions

-   [ ] **Methods:**

    -   [ ] `getPermissionPrefixes()` - Return Shield permission prefixes
    -   [ ] Shield-specific navigation and configuration methods

-   [ ] **Navigation:**
    -   [ ] Group: 'System Administration'
    -   [ ] Shield-specific navigation settings

---

### 11. UserResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Sections:**

    -   [ ] User information section (name, email, email_verified_at, password)
    -   [ ] Roles and Permissions section (roles relationship, multiple, preload)

-   [ ] **Table Configuration:**

    -   [ ] `name`, `email` (searchable), `email_verified_at` (dateTime, sortable)
    -   [ ] `created_at`, `updated_at` (dateTime, sortable, toggleable hidden by default)

-   [ ] **Navigation:**
    -   [ ] Group: 'System Administration'

---

### 12. SubscriptionTypeResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Fields:**

    -   [ ] `name` (required)
    -   [ ] `github_repo` (required)
    -   [ ] `branch` (required)
    -   [ ] `project_type` (required)
    -   [ ] `master_version` (maxLength: 8, nullable)
    -   [ ] `created_at` and `updated_at` placeholders

-   [ ] **Table Configuration:**

    -   [ ] `id`, `name`, `github_repo`, `branch`, `project_type`, `master_version` columns (all searchable/sortable)
    -   [ ] TrashedFilter
    -   [ ] Soft delete actions (RestoreAction, ForceDeleteAction)

-   [ ] **Query Logic:**

    -   [ ] Soft delete scope handling

-   [ ] **Global Search:**

    -   [ ] Searchable attributes: ['name']

-   [ ] **Navigation:**
    -   [ ] Group: 'System Administration'
    -   [ ] Slug: 'subscription-types'

---

### 13. UserCustomerResource.php

**Status:** ⏳ Pending

**Missing Functionality:**

-   [ ] **Form Fields:**

    -   [ ] `user_id` (relationship to user.email, searchable, preload, required)
    -   [ ] `customer_id` (relationship to customer.company_name, preload, required)
    -   [ ] `created_at` and `updated_at` placeholders

-   [ ] **Table Configuration:**

    -   [ ] `user.name` (searchable, sortable), `customer_id` columns
    -   [ ] TrashedFilter
    -   [ ] Soft delete actions (RestoreAction, ForceDeleteAction)

-   [ ] **Query Logic:**

    -   [ ] Soft delete scope handling

-   [ ] **Global Search:**

    -   [ ] Searchable attributes: ['user.name']
    -   [ ] Global search query with user relationship
    -   [ ] Global search result details

-   [ ] **Navigation:**
    -   [ ] Group: 'System Administration'
    -   [ ] Slug: 'user-customers'

---

## Migration Priority

### High Priority (Core Functionality)

1. **CustomerResource** - Core customer management
2. **CustomerSubscriptionResource** - Core subscription management
3. **UserResource** - User management with roles
4. **RoleResource** - Permission system

### Medium Priority (System Administration)

5. **SubscriptionTypeResource** - Product configuration
6. **DeploymentTemplateResource** - Deployment configuration
7. **RequiredEnvVariablesResource** - Environment configuration
8. **ForgeServerResource** - Server management

### Lower Priority (Supporting Resources)

9. **CustomerUserResource** - Customer-specific users
10. **UserCustomerResource** - User-customer relationships
11. **DeploymentScriptResource** - Individual scripts
12. **EnvVariablesResource** - Individual environment variables
13. **NginxTemplateResource** - Nginx configuration

## Notes

-   All resources need to maintain their original functionality while adapting to the new v4 structure
-   Pay special attention to role-based query logic and soft delete handling
-   Shield integration for RoleResource requires careful implementation
-   Global search functionality needs proper relationship loading
-   Navigation groups and slugs should match the original configuration

## Testing Checklist

-   [ ] All forms render correctly with proper validation
-   [ ] All tables display data correctly with sorting/filtering
-   [ ] Role-based access control works as expected
-   [ ] Soft delete functionality works properly
-   [ ] Global search returns correct results
-   [ ] Navigation and routing work correctly
-   [ ] Shield permissions work for RoleResource
-   [ ] File uploads work for CustomerSubscriptionResource logos
