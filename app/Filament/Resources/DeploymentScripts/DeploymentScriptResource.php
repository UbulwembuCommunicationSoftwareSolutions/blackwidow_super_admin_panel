<?php

namespace App\Filament\Resources\DeploymentScripts;

use App\Filament\Resources\DeploymentScripts\Pages\CreateDeploymentScript;
use App\Filament\Resources\DeploymentScripts\Pages\EditDeploymentScript;
use App\Filament\Resources\DeploymentScripts\Pages\ListDeploymentScripts;
use App\Filament\Resources\DeploymentScripts\Schemas\DeploymentScriptForm;
use App\Filament\Resources\DeploymentScripts\Tables\DeploymentScriptsTable;
use App\Models\DeploymentScript;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DeploymentScriptResource extends Resource
{
    protected static ?string $model = DeploymentScript::class;

    protected static string|UnitEnum|null $navigationGroup = 'System Administration';

    protected static ?string $slug = 'deployment-scripts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DeploymentScriptForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeploymentScriptsTable::configure($table);
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
            'index' => ListDeploymentScripts::route('/'),
            'create' => CreateDeploymentScript::route('/create'),
            'edit' => EditDeploymentScript::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }
}
