<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeploymentScriptResource\Pages;
use App\Models\DeploymentScript;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeploymentScriptResource extends Resource
{
    protected static ?string $model = DeploymentScript::class;

    protected static ?string $slug = 'deployment-scripts';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?DeploymentScript $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?DeploymentScript $record): string => $record?->updated_at?->diffForHumans() ?? '-'),

                Textarea::make('script')
                    ->formatStateUsing(fn ($value) => nl2br(e($value)))
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
                TextColumn::make('script'),

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
            'index' => Pages\ListDeploymentScripts::route('/'),
            'create' => Pages\CreateDeploymentScript::route('/create'),
            'edit' => Pages\EditDeploymentScript::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }
}
