<?php

namespace App\Filament\Resources\ForgeServers;

use App\Filament\Resources\ForgeServers\Pages\CreateForgeServer;
use App\Filament\Resources\ForgeServers\Pages\EditForgeServer;
use App\Filament\Resources\ForgeServers\Pages\ListForgeServers;
use App\Filament\Resources\ForgeServers\Schemas\ForgeServerForm;
use App\Filament\Resources\ForgeServers\Tables\ForgeServersTable;
use App\Models\ForgeServer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ForgeServerResource extends Resource
{
    protected static ?string $model = ForgeServer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ForgeServerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ForgeServersTable::configure($table);
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
            'index' => ListForgeServers::route('/'),
            'create' => CreateForgeServer::route('/create'),
            'edit' => EditForgeServer::route('/{record}/edit'),
        ];
    }
}
