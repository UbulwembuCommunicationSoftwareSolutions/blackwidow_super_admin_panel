<?php

namespace App\Filament\Resources\CustomerSubscriptions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerSubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')
                    ->searchable(),
                TextColumn::make('subscriptionType.name')
                    ->searchable(),
                TextColumn::make('logo_1')
                    ->searchable(),
                TextColumn::make('logo_2')
                    ->searchable(),
                TextColumn::make('logo_3')
                    ->searchable(),
                TextColumn::make('logo_4')
                    ->searchable(),
                TextColumn::make('logo_5')
                    ->searchable(),
                TextColumn::make('customer.id')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('forge_site_id')
                    ->searchable(),
                TextColumn::make('server_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('app_name')
                    ->searchable(),
                TextColumn::make('database_name')
                    ->searchable(),
                TextColumn::make('site_created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('github_sent_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('env_sent_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('deployment_script_sent_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ssl_deployed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('deployed_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('domain')
                    ->searchable(),
                IconColumn::make('panic_button_enabled')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
