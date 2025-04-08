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
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class CustomerSubscriptionResource extends Resource
{
    protected static ?string $model = CustomerSubscription::class;

    protected static ?string $slug = 'customer-subscriptions';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')
                    ->label('Website')
                    ->formatStateUsing(fn ($state) => '<a href="' . $state . '" target="_blank" rel="noopener noreferrer">'.$state.'</a>')
                    ->html()
                    ->sortable(),
                TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('app_name')
                    ->label('App Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.company_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subscriptionType.name')
                    ->sortable()
                    ->searchable(),
                ToggleColumn::make('panic_button_enabled')
                    ->label('Panic Button')
                    ->disabled(fn ($record) => !$record || self::isThisAppTypeSubscription($record->subscription_type_id)),

                TextColumn::make('forge_site_id'),
                TextColumn::make('env_variables_count')
                    ->label('Variable Count')
                    ->counts('envVariables'),
                TextColumn::make('null_variable_count')
                    ->label('Null Count')
            ])
            ->filters([
                SelectFilter::make('subscription_type_id')
                    ->label('Subscription Type')
                    ->relationship('subscriptionType', 'name') // Assuming 'subscriptionType' is the relationship method name
                    ->options(SubscriptionType::pluck('name', 'id')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('send_ent_to_forge')
                        ->label('Send ENV To Forge')
                        ->action(fn (Collection $records) => CustomerSubscriptionResource::sendEnvs($records))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->color('primary'),
                ]),
            ]);
    }

    public static function  isThisAppTypeSubscription($subscriptionTypeId): bool
    {
        // App type subscription IDs: 3 (responder), 4 (reporter), 5 (security), 6 (driver), 7 (survey)
        return in_array($subscriptionTypeId, [3, 4, 5, 6, 7]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('customer', 'subscriptionType')
            ->with('envVariables')
            ->withCount('envVariables')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
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
