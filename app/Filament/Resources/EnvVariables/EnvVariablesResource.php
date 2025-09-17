<?php

namespace App\Filament\Resources\EnvVariables;

use App\Filament\Resources\EnvVariables\Pages\CreateEnvVariables;
use App\Filament\Resources\EnvVariables\Pages\EditEnvVariables;
use App\Filament\Resources\EnvVariables\Pages\ListEnvVariables;
use App\Filament\Resources\EnvVariables\Schemas\EnvVariablesForm;
use App\Filament\Resources\EnvVariables\Tables\EnvVariablesTable;
use App\Models\EnvVariables;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EnvVariablesResource extends Resource
{
    protected static ?string $model = EnvVariables::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return EnvVariablesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EnvVariablesTable::configure($table);
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
            'index' => ListEnvVariables::route('/'),
            'create' => CreateEnvVariables::route('/create'),
            'edit' => EditEnvVariables::route('/{record}/edit'),
        ];
    }
}
