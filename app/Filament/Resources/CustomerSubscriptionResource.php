<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerSubscriptionResource\Pages;
use App\Filament\Resources\CustomerSubscriptionResource\RelationManagers\DeploymentScriptRelationManager;
use App\Filament\Resources\CustomerSubscriptionResource\RelationManagers\EnvVariablesRelationManager;
use App\Jobs\SendEnvToForge;
use App\Models\CustomerSubscription;
use App\Models\DeploymentScript;
use App\Models\SubscriptionType;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;

class CustomerSubscriptionResource extends Resource
{
    protected static ?string $model = CustomerSubscription::class;

    protected static ?string $slug = 'customer-subscriptions';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('url')
                    ->required(),
                TextInput::make('domain')
                    ->required(),
                TextInput::make('app_name')
                    ->required(),
                Select::make('customer_id')
                    ->relationship('customer', 'company_name')
                    ->required(),
                Select::make('subscription_type_id')
                    ->relationship('subscriptionType', 'name')
                    ->required(),
                TextInput::make('deployed_version')
                    ->maxLength(8)
                    ->nullable(),
                FileUpload::make('logo_1')
                    ->image()
                    ->directory('logos'),
                FileUpload::make('logo_2')
                    ->image()
                    ->directory('logos'),
                FileUpload::make('logo_3')
                    ->image()
                    ->directory('logos'),
                FileUpload::make('logo_4')
                    ->image()
                    ->directory('logos'),
                FileUpload::make('logo_5')
                    ->image()
                    ->directory('logos'),
                TextInput::make('database_name'),
                TextInput::make('forge_site_id'),
                ToggleColumn::make('panic_button_enabled')
                    ->label('Panic Button'),
            ]);
    }


    public static function  isThisAppTypeSubscription($subscriptionTypeId): bool
    {
        // App type subscription IDs: 3 (responder), 4 (reporter), 5 (security), 6 (driver), 7 (survey)
        return in_array($subscriptionTypeId, [3, 4, 5, 6, 7]);
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerSubscriptions::route('/'),
            'create' => Pages\CreateCustomerSubscription::route('/create'),
            'edit' => Pages\EditCustomerSubscription::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            DeploymentScriptRelationManager::class,
            EnvVariablesRelationManager::class
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function sendEnvs(Collection $collection)
    {
        foreach ($collection as $row){
            SendEnvToForge::dispatch($row->id);
        }
    }
}
