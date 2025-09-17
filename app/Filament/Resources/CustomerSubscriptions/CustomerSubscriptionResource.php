<?php

namespace App\Filament\Resources\CustomerSubscriptions;

use App\Filament\Resources\CustomerSubscriptions\Pages\CreateCustomerSubscription;
use App\Filament\Resources\CustomerSubscriptions\Pages\EditCustomerSubscription;
use App\Filament\Resources\CustomerSubscriptions\Pages\ListCustomerSubscriptions;
use App\Filament\Resources\CustomerSubscriptions\Pages\ViewCustomerSubscription;
use App\Filament\Resources\CustomerSubscriptions\Schemas\CustomerSubscriptionForm;
use App\Filament\Resources\CustomerSubscriptions\Tables\CustomerSubscriptionsTable;
use App\Jobs\SendEnvToForge;
use App\Models\CustomerSubscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use UnitEnum;

class CustomerSubscriptionResource extends Resource
{
    protected static ?string $model = CustomerSubscription::class;

    protected static ?string $slug = 'customer-subscriptions';

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CustomerSubscriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerSubscriptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // TODO: Add relation managers when they are created
            // DeploymentScriptRelationManager::class,
            // EnvVariablesRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerSubscriptions::route('/'),
            'create' => CreateCustomerSubscription::route('/create'),
            'view' => ViewCustomerSubscription::route('/{record}'),
            'edit' => EditCustomerSubscription::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function isThisAppTypeSubscription($subscriptionTypeId): bool
    {
        // App type subscription IDs: 3 (responder), 4 (reporter), 5 (security), 6 (driver), 7 (survey)
        return in_array($subscriptionTypeId, [3, 4, 5, 6, 7]);
    }

    public static function sendEnvs(Collection $collection)
    {
        foreach ($collection as $row) {
            SendEnvToForge::dispatch($row->id);
        }
    }
}
