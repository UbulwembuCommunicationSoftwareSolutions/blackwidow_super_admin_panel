<?php

namespace App\Filament\Resources\DeploymentTemplates;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\DeploymentTemplates\Pages\ListDeploymentTemplates;
use App\Filament\Resources\DeploymentTemplates\Pages\CreateDeploymentTemplate;
use App\Filament\Resources\DeploymentTemplates\Pages\EditDeploymentTemplate;
use App\Filament\Resources\DeploymentTemplateResource\Pages;
use App\Models\DeploymentTemplate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeploymentTemplateResource extends Resource
{
    protected static ?string $model = DeploymentTemplate::class;

    protected static string | \UnitEnum | null $navigationGroup = 'System Administration';

    protected static ?string $slug = 'deployment-templates';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('script')
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('script'),

                TextColumn::make('subscriptionType.name')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
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

    public static function getPages(): array
    {
        return [
            'index' => ListDeploymentTemplates::route('/'),
            'create' => CreateDeploymentTemplate::route('/create'),
            'edit' => EditDeploymentTemplate::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with(['subscriptionType']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['subscriptionType.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [];

        if ($record->subscriptionType) {
            $details['SubscriptionType'] = $record->subscriptionType->name;
        }

        return $details;
    }
}
