<?php

namespace App\Filament\Resources\NginxTemplates;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\NginxTemplates\Pages\ListNginxTemplates;
use App\Filament\Resources\NginxTemplates\Pages\CreateNginxTemplate;
use App\Filament\Resources\NginxTemplates\Pages\EditNginxTemplate;
use App\Filament\Resources\NginxTemplateResource\Pages;
use App\Models\ForgeServer;
use App\Models\NginxTemplate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NginxTemplateResource extends Resource
{
    protected static ?string $model = NginxTemplate::class;

    protected static ?string $slug = 'nginx-templates';

    protected static string | \UnitEnum | null $navigationGroup = 'System Administration';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),

                Select::make('server_id')
                    ->required()
                    ->options(fn()=>ForgeServer::pluck('name','forge_server_id')),
                TextInput::make('template_id')
                    ->required()
                    ->integer(),

                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?NginxTemplate $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?NginxTemplate $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('template_id'),
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
