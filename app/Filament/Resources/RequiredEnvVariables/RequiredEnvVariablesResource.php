<?php

namespace App\Filament\Resources\RequiredEnvVariables;

use App\Filament\Resources\RequiredEnvVariables\Pages\CreateRequiredEnvVariables;
use App\Filament\Resources\RequiredEnvVariables\Pages\EditRequiredEnvVariables;
use App\Filament\Resources\RequiredEnvVariables\Pages\ListRequiredEnvVariables;
use App\Filament\Resources\RequiredEnvVariables\Schemas\RequiredEnvVariablesForm;
use App\Filament\Resources\RequiredEnvVariables\Tables\RequiredEnvVariablesTable;
use App\Models\RequiredEnvVariables;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RequiredEnvVariablesResource extends Resource
{
    protected static ?string $model = RequiredEnvVariables::class;

    protected static ?string $slug = 'required-env-variables';

    protected static string|UnitEnum|null $navigationGroup = 'System Administration';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return RequiredEnvVariablesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequiredEnvVariablesTable::configure($table);
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
            'index' => ListRequiredEnvVariables::route('/'),
            'create' => CreateRequiredEnvVariables::route('/create'),
            'edit' => EditRequiredEnvVariables::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['subscriptionType']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['subscriptionType.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [];

        if ($record->subscriptionType) {
            $details['SubscriptionType'] = $record->subscriptionType->name;
        }

        return $details;
    }
}
