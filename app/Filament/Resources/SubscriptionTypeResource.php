<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use App\Filament\Resources\SubscriptionTypeResource\Pages\ListSubscriptionTypes;
use App\Filament\Resources\SubscriptionTypeResource\Pages\CreateSubscriptionType;
use App\Filament\Resources\SubscriptionTypeResource\Pages\EditSubscriptionType;
use App\Filament\Resources\SubscriptionTypeResource\Pages;
use App\Models\SubscriptionType;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SubscriptionTypeResource extends Resource
{
    protected static ?string $model = SubscriptionType::class;

    protected static ?string $slug = 'subscription-types';

    protected static string | \UnitEnum | null $navigationGroup = 'System Administration';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?SubscriptionType $record): string => $record?->created_at?->diffForHumans() ?? '-'),
                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?SubscriptionType $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('github_repo')
                    ->required(),
                TextInput::make('branch')
                    ->required(),
                TextInput::make('project_type')
                    ->required(),
                TextInput::make('master_version')
                    ->maxLength(8)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('github_repo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('branch')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project_type')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('master_version')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionTypes::route('/'),
            'create' => CreateSubscriptionType::route('/create'),
            'edit' => EditSubscriptionType::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }
}
