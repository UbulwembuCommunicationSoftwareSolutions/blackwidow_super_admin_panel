<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use App\Models\SubscriptionType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction as ActionsCreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;


class ListCustomerSubscriptions extends ListRecords
{
    protected static string $resource = CustomerSubscriptionResource::class;


    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')
                    ->label('Website')
                    ->formatStateUsing(fn ($state) => '<a href="' . $state . '" target="_blank" rel="noopener noreferrer">'.$state.'</a>')
                    ->html()
                    ->sortable(),
                TextColumn::make('domain')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('app_name')
                    ->label('App Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.company_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subscriptionType.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('deployed_at')
                    ->label('Deployed Date')
                    ->dateTime('Y-m-d H:i:s')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('deployed_version')
                    ->label('Deployed Version')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subscriptionType.master_version')
                    ->label('Newest Version')
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('panic_button_enabled')
                    ->label('Panic Button')
                    ->disabled(fn ($record) => !$record || CustomerSubscriptionResource::isThisAppTypeSubscription($record->subscription_type_id)),
                TextColumn::make('forge_site_id'),
                TextColumn::make('env_variables_count')
                    ->label('Variable Count')
                    ->counts('envVariables'),
                TextColumn::make('null_variable_count')
                    ->label('Null Count')
            ])
            ->filters([
                SelectFilter::make('subscription_type_id')
                    ->label('Subscription Type')
                    ->relationship('subscriptionType', 'name')
                    ->options(SubscriptionType::pluck('name', 'id')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                ActionsCreateAction::make(),
                ExportAction::make('export')->exports([
                    ExcelExport::make('table')->queue()->fromTable(),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('send_ent_to_forge')
                        ->label('Send ENV To Forge')
                        ->action(fn (Collection $records) => CustomerSubscriptionResource::sendEnvs($records))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->color('primary'),
                ]),
            ]);
    }

    public function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('customer', 'subscriptionType')
            ->with('envVariables')
            ->withCount('envVariables')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
