<?php

namespace App\Filament\Resources\EnvVariables;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\EnvVariables\Pages\ListEnvVariables;
use App\Filament\Resources\EnvVariables\Pages\CreateEnvVariables;
use App\Filament\Resources\EnvVariables\Pages\EditEnvVariables;
use App\Filament\Resources\EnvVariablesResource\Pages;
use App\Models\EnvVariables;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnvVariablesResource extends Resource
{
    protected static ?string $model = EnvVariables::class;


    protected static string | \UnitEnum | null $navigationGroup = 'System Administration';

    protected static ?string $slug = 'env-variables';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?EnvVariables $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?EnvVariables $record): string => $record?->updated_at?->diffForHumans() ?? '-'),

                TextInput::make('key')
                    ->required(),

                TextInput::make('value')
                    ->required(),

                TextInput::make('customer_subscription_id')
                    ->required()
                    ->integer(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key'),

                TextColumn::make('value'),

                TextColumn::make('customer_subscription_id'),
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
            'index' => ListEnvVariables::route('/'),
            'create' => CreateEnvVariables::route('/create'),
            'edit' => EditEnvVariables::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }
}
