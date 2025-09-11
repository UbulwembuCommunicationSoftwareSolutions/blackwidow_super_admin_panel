# Model Events Refactoring Plan

## Overview

Refactor all model boot functions and Filament resource methods to use proper Laravel events and listeners instead of inline model event handling.

## Current State Analysis

### Models with Boot Functions

1. **Customer Model** - UUID generation, system config job dispatch
2. **CustomerUser Model** - Email jobs, CMS service calls, access control

### Filament Resources with Event Methods

1. **CustomerSubscription Create** - Multiple deployment jobs with delays
2. **Role Create** - Permission creation and syncing
3. **Role Edit** - Permission syncing

## Refactoring Strategy

### Phase 1: Create Event Classes

Create event classes for each model lifecycle event that currently has logic.

### Phase 2: Create Listener Classes

Create listener classes for each specific action currently performed in the events.

### Phase 3: Create Observer Classes

Replace model boot methods with observer classes that dispatch events.

### Phase 4: Update Filament Resources

Replace afterCreate/afterSave methods with event dispatching.

### Phase 5: Register Events and Listeners

Update EventServiceProvider to register all events and listeners.

### Phase 6: Testing and Cleanup

Test all functionality and remove old code.

## Detailed Implementation Plan

### Step 1: Create Event Classes

#### Customer Events

-   `CustomerCreated` - Fired when customer is created
-   `CustomerUpdated` - Fired when customer is updated (with changed fields info)

#### CustomerUser Events

-   `CustomerUserCreated` - Fired when customer user is created
-   `CustomerUserUpdated` - Fired when customer user is updated (with changed fields info)
-   `CustomerUserDeleted` - Fired when customer user is deleted

#### CustomerSubscription Events

-   `CustomerSubscriptionCreated` - Fired when subscription is created

#### Role Events

-   `RoleCreated` - Fired when role is created
-   `RoleUpdated` - Fired when role is updated

### Step 2: Create Listener Classes

#### Customer Listeners

-   `GenerateCustomerUuidListener` - Generates UUID and token
-   `DispatchSystemConfigJobListener` - Dispatches system config job

#### CustomerUser Listeners

-   `SendWelcomeEmailListener` - Sends welcome email for console access
-   `SendSubscriptionEmailListener` - Sends subscription emails for various access types
-   `SyncUsersToCMSListener` - Syncs users to CMS service
-   `SuspendCMSUserListener` - Suspends user in CMS service

#### CustomerSubscription Listeners

-   `DispatchDeploymentJobsListener` - Dispatches all deployment jobs with delays
-   `DispatchArtisanCommandsListener` - Dispatches artisan commands for specific subscription types

#### Role Listeners

-   `CreateAndSyncPermissionsListener` - Creates and syncs permissions

### Step 3: Create Observer Classes

#### CustomerObserver

-   `created()` - Dispatch CustomerCreated event
-   `updating()` - Dispatch CustomerUpdated event

#### CustomerUserObserver

-   `created()` - Dispatch CustomerUserCreated event
-   `updated()` - Dispatch CustomerUserUpdated event
-   `deleted()` - Dispatch CustomerUserDeleted event

### Step 4: Update Filament Resources

#### CustomerSubscriptionResource

-   Replace `afterCreate()` method with event dispatching
-   Dispatch `CustomerSubscriptionCreated` event

#### RoleResource

-   Replace `afterCreate()` and `afterSave()` methods with event dispatching
-   Dispatch `RoleCreated` or `RoleUpdated` events

### Step 5: Update EventServiceProvider

Register all events and listeners in the proper order to ensure dependencies are met.

### Step 6: Testing Strategy

1. **Unit Tests** - Test each event and listener individually
2. **Integration Tests** - Test the full flow from model creation to job dispatch
3. **Feature Tests** - Test Filament resource creation/editing flows
4. **Manual Testing** - Verify all functionality works in the admin panel

## File Structure After Refactoring

```
app/
├── Events/
│   ├── Customer/
│   │   ├── CustomerCreated.php
│   │   └── CustomerUpdated.php
│   ├── CustomerUser/
│   │   ├── CustomerUserCreated.php
│   │   ├── CustomerUserUpdated.php
│   │   └── CustomerUserDeleted.php
│   ├── CustomerSubscription/
│   │   └── CustomerSubscriptionCreated.php
│   └── Role/
│       ├── RoleCreated.php
│       └── RoleUpdated.php
├── Listeners/
│   ├── Customer/
│   │   ├── GenerateCustomerUuidListener.php
│   │   └── DispatchSystemConfigJobListener.php
│   ├── CustomerUser/
│   │   ├── SendWelcomeEmailListener.php
│   │   ├── SendSubscriptionEmailListener.php
│   │   ├── SyncUsersToCMSListener.php
│   │   └── SuspendCMSUserListener.php
│   ├── CustomerSubscription/
│   │   ├── DispatchDeploymentJobsListener.php
│   │   └── DispatchArtisanCommandsListener.php
│   └── Role/
│       └── CreateAndSyncPermissionsListener.php
├── Observers/
│   ├── CustomerObserver.php
│   └── CustomerUserObserver.php
└── Providers/
    └── EventServiceProvider.php (updated)
```

## Benefits of Refactoring

1. **Separation of Concerns** - Model logic separated from business logic
2. **Testability** - Each listener can be tested independently
3. **Maintainability** - Easier to modify individual behaviors
4. **Reusability** - Events can be listened to by multiple listeners
5. **Queue Support** - Listeners can be queued for better performance
6. **Laravel Best Practices** - Following Laravel conventions

## Migration Steps

1. Create all event classes
2. Create all listener classes
3. Create observer classes
4. Update EventServiceProvider
5. Update Filament resources
6. Remove boot methods from models
7. Test thoroughly
8. Deploy and monitor

## Risk Mitigation

1. **Backup Current Code** - Keep original implementation until fully tested
2. **Gradual Rollout** - Test one model at a time
3. **Rollback Plan** - Ability to quickly revert to old implementation
4. **Monitoring** - Watch for any failed events or listeners
5. **Documentation** - Document all new events and listeners

## Timeline Estimate

-   **Week 1**: Create events and listeners
-   **Week 2**: Create observers and update EventServiceProvider
-   **Week 3**: Update Filament resources and remove old code
-   **Week 4**: Testing and deployment

## Success Criteria

1. All existing functionality preserved
2. No performance degradation
3. All events and listeners properly registered
4. All tests passing
5. Clean, maintainable code structure
6. Proper error handling and logging
