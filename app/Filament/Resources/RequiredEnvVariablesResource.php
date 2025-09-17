<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Filters\Filter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\RequiredEnvVariablesResource\Pages\ListRequiredEnvVariables;
use App\Filament\Resources\RequiredEnvVariablesResource\Pages\CreateRequiredEnvVariables;
use App\Filament\Resources\RequiredEnvVariablesResource\Pages\EditRequiredEnvVariables;
use App\Filament\Resources\RequiredEnvVariablesResource\Pages;
use App\Models\RequiredEnvVariables;
use App\Models\SubscriptionType;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RequiredEnvVariablesResource extends Resource
{
    protected static ?string $model = RequiredEnvVariables::class;

    protected static ?string $slug = 'required-env-variables';

    protected static string | \UnitEnum | null $navigationGroup = 'System Administration';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?RequiredEnvVariables $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?RequiredEnvVariables $record): string => $record?->updated_at?->diffForHumans() ?? '-'),

                TextInput::make('key')
                    ->required(),

                TextInput::make('value')
                    ->required(),

                Select::make('subscription_type_id')
                    ->relationship('subscriptionType', 'name')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key'),

                TextColumn::make('value'),

                TextColumn::make('subscriptionType.name')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('subscriptionType')
                    ->label('Product')
                    ->schema([
                        Select::make('subscriptionType')
                            ->options(
                                SubscriptionType::pluck('name', 'id'),
                            )
                            ->label('Product'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->where('subscription_type_id', $data['subscriptionType']);
                    }),
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
            'index' => ListRequiredEnvVariables::route('/'),
            'create' => CreateRequiredEnvVariables::route('/create'),
            'edit' => EditRequiredEnvVariables::route('/{record}/edit'),
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
