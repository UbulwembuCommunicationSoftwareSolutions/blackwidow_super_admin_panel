<?php

namespace App\Filament\Resources\NginxTemplates;

use App\Filament\Resources\NginxTemplates\Pages\CreateNginxTemplate;
use App\Filament\Resources\NginxTemplates\Pages\EditNginxTemplate;
use App\Filament\Resources\NginxTemplates\Pages\ListNginxTemplates;
use App\Filament\Resources\NginxTemplates\Schemas\NginxTemplateForm;
use App\Filament\Resources\NginxTemplates\Tables\NginxTemplatesTable;
use App\Models\NginxTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class NginxTemplateResource extends Resource
{
    protected static ?string $model = NginxTemplate::class;

    protected static string|UnitEnum|null $navigationGroup = 'System Administration';

    protected static ?string $slug = 'nginx-templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return NginxTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NginxTemplatesTable::configure($table);
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
            'index' => ListNginxTemplates::route('/'),
            'create' => CreateNginxTemplate::route('/create'),
            'edit' => EditNginxTemplate::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}
