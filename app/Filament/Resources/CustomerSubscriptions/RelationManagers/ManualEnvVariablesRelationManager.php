<?php

namespace App\Filament\Resources\CustomerSubscriptions\RelationManagers;

use App\Filament\Resources\CustomerSubscriptions\CustomerSubscriptionResource;
use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\RequiredEnvVariables;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ManualEnvVariablesRelationManager extends RelationManager
{
    protected static string $relationship = 'envVariables';

    protected static ?string $title = 'Manual environment variables';

    protected static string $resource = CustomerSubscriptionResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->disabled()
                    ->dehydrated(true),
                TextInput::make('value')
                    ->label('Value')
                    ->nullable()
                    ->maxLength(65535)
                    ->hint(function (?EnvVariables $record) {
                        if ($record === null || ! $this->ownerRecord instanceof CustomerSubscription || ! $this->ownerRecord->subscription_type_id) {
                            return null;
                        }
                        $req = RequiredEnvVariables::query()
                            ->where('subscription_type_id', $this->ownerRecord->subscription_type_id)
                            ->where('key', $record->key)
                            ->first();

                        return $req?->help_text;
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $owner = $this->ownerRecord;
                if (! $owner instanceof CustomerSubscription || ! $owner->subscription_type_id) {
                    return $query->whereRaw('1 = 0');
                }

                $keys = RequiredEnvVariables::query()
                    ->where('subscription_type_id', $owner->subscription_type_id)
                    ->where('requires_manual_fill', true)
                    ->pluck('key');

                if ($keys->isEmpty()) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereIn('key', $keys);
            })
            ->columns([
                TextColumn::make('key')
                    ->label('Key')
                    ->searchable(),
                TextColumn::make('display_label')
                    ->label('Label')
                    ->getStateUsing(function (Model $record) {
                        if (! $this->ownerRecord instanceof CustomerSubscription || ! $this->ownerRecord->subscription_type_id) {
                            return '';
                        }
                        $req = RequiredEnvVariables::query()
                            ->where('subscription_type_id', $this->ownerRecord->subscription_type_id)
                            ->where('key', $record->key)
                            ->first();

                        return $req?->admin_label ?: $record->key;
                    }),
                TextColumn::make('help_text')
                    ->label('Help')
                    ->getStateUsing(function (Model $record) {
                        if (! $this->ownerRecord instanceof CustomerSubscription || ! $this->ownerRecord->subscription_type_id) {
                            return '';
                        }

                        return RequiredEnvVariables::query()
                            ->where('subscription_type_id', $this->ownerRecord->subscription_type_id)
                            ->where('key', $record->key)
                            ->value('help_text') ?? '';
                    })
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('value')
                    ->label('Value')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }
}
