<?php

namespace App\Filament\Resources\DeploymentScripts;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\DeploymentScripts\Pages\ListDeploymentScripts;
use App\Filament\Resources\DeploymentScripts\Pages\CreateDeploymentScript;
use App\Filament\Resources\DeploymentScripts\Pages\EditDeploymentScript;
use App\Filament\Resources\DeploymentScriptResource\Pages;
use App\Models\DeploymentScript;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeploymentScriptResource extends Resource
{
    protected static ?string $model = DeploymentScript::class;
    protected static string | \UnitEnum | null $navigationGroup = 'System Administration';
    protected static ?string $slug = 'deployment-scripts';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?DeploymentScript $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?DeploymentScript $record): string => $record?->updated_at?->diffForHumans() ?? '-'),

                Textarea::make('script')
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
