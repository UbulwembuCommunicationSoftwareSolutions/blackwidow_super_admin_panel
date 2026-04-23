<?php

namespace App\Filament\Resources\CustomerSubscriptions\RelationManagers;

use App\Filament\Resources\CustomerSubscriptions\CustomerSubscriptionResource;
use App\Models\CustomerSubscriptionDeploymentJob;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DeploymentJobsRelationManager extends RelationManager
{
    protected static string $relationship = 'deploymentJobs';

    protected static ?string $title = 'Site deployment jobs';

    protected static string $resource = CustomerSubscriptionResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('job_name')
                    ->label('Job')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('job_name')
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    ->orderByDesc('created_at')
                    ->orderBy('position');
            })
            ->columns([
                TextColumn::make('batch_id')
                    ->label('Batch')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Batch id copied')
                    ->toggleable(),
                TextColumn::make('position')
                    ->label('Step')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('job_name')
                    ->label('Job')
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->colors([
                        'gray' => CustomerSubscriptionDeploymentJob::STATUS_PENDING,
                        'warning' => CustomerSubscriptionDeploymentJob::STATUS_RUNNING,
                        'success' => CustomerSubscriptionDeploymentJob::STATUS_COMPLETED,
                        'danger' => CustomerSubscriptionDeploymentJob::STATUS_FAILED,
                    ]),
                TextColumn::make('error_message')
                    ->label('Error')
                    ->wrap()
                    ->limit(200)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('parameters')
                    ->label('Parameters')
                    ->formatStateUsing(function ($state): string {
                        if (! is_array($state) || $state === []) {
                            return '—';
                        }
                        $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—';
                        if (strlen($json) > 100) {
                            return substr($json, 0, 97).'…';
                        }

                        return $json;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('finished_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Record created')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(Model $record): bool
    {
        return false;
    }

    public function canDelete(Model $record): bool
    {
        return false;
    }
}
