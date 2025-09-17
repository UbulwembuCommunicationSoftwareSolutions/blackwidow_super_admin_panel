<?php

namespace App\Filament\Resources\CustomerUsers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CustomerUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.id')
                    ->searchable(),
                TextColumn::make('email_address')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('console_access')
                    ->boolean(),
                IconColumn::make('firearm_access')
                    ->boolean(),
                IconColumn::make('responder_access')
                    ->boolean(),
                IconColumn::make('reporter_access')
                    ->boolean(),
                IconColumn::make('security_access')
                    ->boolean(),
                IconColumn::make('driver_access')
                    ->boolean(),
                IconColumn::make('survey_access')
                    ->boolean(),
                IconColumn::make('time_and_attendance_access')
                    ->boolean(),
                IconColumn::make('stock_access')
                    ->boolean(),
                TextColumn::make('cellphone')
                    ->searchable(),
                IconColumn::make('is_system_admin')
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
