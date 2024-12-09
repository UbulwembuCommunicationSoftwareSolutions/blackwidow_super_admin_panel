<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EnvVariablesResource\Pages;
use App\Models\EnvVariables;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EnvVariablesResource extends Resource
{
    protected static ?string $model = EnvVariables::class;

    protected static ?string $navigationGroup = 'System Settings';

    protected static ?string $slug = 'env-variables';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEnvVariables::route('/'),
            'create' => Pages\CreateEnvVariables::route('/create'),
            'edit' => Pages\EditEnvVariables::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }
}
