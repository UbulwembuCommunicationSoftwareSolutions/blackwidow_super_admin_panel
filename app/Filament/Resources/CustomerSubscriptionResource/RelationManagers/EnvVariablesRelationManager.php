<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\RelationManagers;

use App\Filament\Exports\EnvVariableExporter;
use App\Jobs\SendEnvToForge;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class EnvVariablesRelationManager extends RelationManager
{
    protected static string $relationship = 'envVariables';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('key')
                    ->required(),

                TextInput::make('value')
                    ->required(),

                Select::make('customer_subscription_id')
                    ->label('Subscription')
                    ->searchable()
                    ->relationship('customerSubscription', 'url') // Specify the relationship and the display column
                    ->required()
                    ->default(fn () => $this->ownerRecord->id),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('key')
            ->columns([
                Tables\Columns\TextColumn::make('key')
                ->sortable()
                ->searchable(),
                Tables\Columns\TextColumn::make('value')
            ])
            ->filters([
                Tables\Filters\Filter::make('is_null')
                    ->query(fn (Builder $query): Builder => $query->where('value', null))
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\Action::make('SendToServer')
                    ->label('Send To Server')
                    ->action(fn ($record) => $this->sendToServer($record))
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->color('primary'),
                ExportAction::make()
                    ->exporter(EnvVariableExporter::class)
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public function sendToServer($record)
    {
        SendEnvToForge::dispatch($this->ownerRecord->id);
    }
}
