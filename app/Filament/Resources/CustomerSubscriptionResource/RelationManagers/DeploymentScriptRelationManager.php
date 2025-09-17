<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Exports\EnvVariableExporter;
use App\Jobs\SendDeploymentScriptToForge;
use App\Jobs\SendEnvToForge;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Tables\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;

class DeploymentScriptRelationManager extends RelationManager
{
    protected static string $relationship = 'deploymentScript';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('script')
                    ->rows(50)
                    ->required(),
                Select::make('customer_subscription_id')
                    ->label('Subscription')
                    ->searchable()
                    ->hidden()
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
                TextColumn::make('string')
                ->sortable()
                ->searchable(),
            ])
            ->filters([
                Filter::make('is_null')
                    ->query(fn (Builder $query): Builder => $query->where('value', null))
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('SendToServer')
                    ->label('Send To Server')
                    ->action(fn ($record) => $this->sendToServer($record))
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->color('primary'),
                ExportAction::make()
                    ->exporter(EnvVariableExporter::class)
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

    public function sendToServer($record)
    {
        SendDeploymentScriptToForge::dispatch($this->ownerRecord->id);
    }
}
