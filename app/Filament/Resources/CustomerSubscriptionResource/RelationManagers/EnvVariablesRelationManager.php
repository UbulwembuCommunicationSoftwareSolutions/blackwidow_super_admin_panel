<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\RelationManagers;

use App\Filament\Exports\EnvVariableExporter;
use App\Jobs\SendEnvToForge;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\RequiredEnvVariables;
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
            \Filament\Notifications\Notification::make()
                ->title('The following environment variables are required for this subscription: '.$array)
                ->send();
        }


    }

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
