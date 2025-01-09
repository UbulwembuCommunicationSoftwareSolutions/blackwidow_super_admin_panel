<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeploymentTemplateResource\Pages;
use App\Models\DeploymentTemplate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DeploymentTemplateResource extends Resource
{
    protected static ?string $model = DeploymentTemplate::class;

    protected static ?string $navigationGroup = 'System Administration';

    protected static ?string $slug = 'deployment-templates';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeploymentTemplates::route('/'),
            'create' => Pages\CreateDeploymentTemplate::route('/create'),
            'edit' => Pages\EditDeploymentTemplate::route('/{record}/edit'),
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
