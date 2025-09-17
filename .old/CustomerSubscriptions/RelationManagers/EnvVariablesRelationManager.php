<?php

namespace App\Filament\Resources\CustomerSubscriptions\RelationManagers;

use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Exports\EnvVariableExporter;
use App\Jobs\SendEnvToForge;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\RequiredEnvVariables;
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

class EnvVariablesRelationManager extends RelationManager
{
    protected static string $relationship = 'envVariables';


    public function mount() : void
    {
        // This method runs when the RelationManager is mounted
        $this->onFirstLoad();
    }

    public function onFirstLoad()
    {
        $addedEnv = EnvVariables::where('customer_subscription_id', $this->ownerRecord->id)->pluck('key');

        $missing = RequiredEnvVariables::where('subscription_type_id', $this->ownerRecord->subscription_type_id)
            ->whereNotIn('key', $addedEnv)
            ->get();

        $array = '';
        foreach ($missing as $env) {
            $array .= $env->key.' , ';
        }
        if(strlen($array) > 0) {
            Notification::make()
                ->title('The following environment variables are required for this subscription: '.$array)
                ->send();
        }


    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                TextColumn::make('key')
                ->sortable()
                ->searchable(),
                TextColumn::make('value')
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
        SendEnvToForge::dispatch($this->ownerRecord->id);
    }
}
