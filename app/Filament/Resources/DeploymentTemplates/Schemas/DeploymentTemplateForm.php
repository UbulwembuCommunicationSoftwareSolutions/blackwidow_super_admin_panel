<?php

namespace App\Filament\Resources\DeploymentTemplates\Schemas;

use App\Models\DeploymentTemplate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DeploymentTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('script')
                    ->required(),
                Select::make('subscription_type_id')
                    ->relationship('subscriptionType', 'name')
                    ->searchable()
                    ->required(),
                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?DeploymentTemplate $record): string => $record?->created_at?->diffForHumans() ?? '-'),
                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?DeploymentTemplate $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }
}
