<?php

namespace App\Filament\Resources\DeploymentTemplates;

use App\Filament\Resources\DeploymentTemplates\Pages\CreateDeploymentTemplate;
use App\Filament\Resources\DeploymentTemplates\Pages\EditDeploymentTemplate;
use App\Filament\Resources\DeploymentTemplates\Pages\ListDeploymentTemplates;
use App\Filament\Resources\DeploymentTemplates\Schemas\DeploymentTemplateForm;
use App\Filament\Resources\DeploymentTemplates\Tables\DeploymentTemplatesTable;
use App\Models\DeploymentTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DeploymentTemplateResource extends Resource
{
    protected static ?string $model = DeploymentTemplate::class;

    protected static string|UnitEnum|null $navigationGroup = 'System Administration';

    protected static ?string $slug = 'deployment-templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DeploymentTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeploymentTemplatesTable::configure($table);
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
            'index' => ListDeploymentTemplates::route('/'),
            'create' => CreateDeploymentTemplate::route('/create'),
            'edit' => EditDeploymentTemplate::route('/{record}/edit'),
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
