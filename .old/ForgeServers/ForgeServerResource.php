<?php

namespace App\Filament\Resources\ForgeServers;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ForgeServers\Pages\ListForgeServers;
use App\Filament\Resources\ForgeServers\Pages\CreateForgeServer;
use App\Filament\Resources\ForgeServers\Pages\EditForgeServer;
use App\Filament\Resources\ForgeServerResource\Pages;
use App\Models\ForgeServer;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ForgeServerResource extends Resource
{
    protected static ?string $model = ForgeServer::class;

    protected static string | \UnitEnum | null $navigationGroup = 'System Administration';

    protected static ?string $slug = 'forge-servers';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('forge_server_id')
                    ->required()
                    ->integer(),

                TextInput::make('name'),

                TextInput::make('ip_address'),

                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?ForgeServer $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?ForgeServer $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('forge_server_id'),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ip_address'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForgeServers::route('/'),
            'create' => CreateForgeServer::route('/create'),
            'edit' => EditForgeServer::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}
