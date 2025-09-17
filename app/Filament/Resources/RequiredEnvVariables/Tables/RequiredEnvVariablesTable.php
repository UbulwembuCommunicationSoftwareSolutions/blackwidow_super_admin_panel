<?php

namespace App\Filament\Resources\RequiredEnvVariables\Tables;

use App\Models\SubscriptionType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequiredEnvVariablesTable
{
    public static function configure(Table $table): Table
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
}
