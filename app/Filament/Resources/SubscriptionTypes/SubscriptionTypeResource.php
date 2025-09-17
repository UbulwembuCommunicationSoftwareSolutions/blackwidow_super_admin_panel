<?php

namespace App\Filament\Resources\SubscriptionTypes;

use App\Filament\Resources\SubscriptionTypes\Pages\CreateSubscriptionType;
use App\Filament\Resources\SubscriptionTypes\Pages\EditSubscriptionType;
use App\Filament\Resources\SubscriptionTypes\Pages\ListSubscriptionTypes;
use App\Filament\Resources\SubscriptionTypes\Schemas\SubscriptionTypeForm;
use App\Filament\Resources\SubscriptionTypes\Tables\SubscriptionTypesTable;
use App\Models\SubscriptionType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SubscriptionTypeResource extends Resource
{
    protected static ?string $model = SubscriptionType::class;

    protected static ?string $slug = 'subscription-types';

    protected static string|UnitEnum|null $navigationGroup = 'System Administration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SubscriptionTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionTypes::route('/'),
            'create' => CreateSubscriptionType::route('/create'),
            'edit' => EditSubscriptionType::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
