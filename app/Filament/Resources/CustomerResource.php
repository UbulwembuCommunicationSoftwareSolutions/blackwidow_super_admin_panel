<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers\CustomerSubscriptionsRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\CustomerUserRelationManager;
use App\Models\Customer;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\ForceDeleteBulkAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CustomerResource\RelationManagers\CustomersRelationManager;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $slug = 'customers';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Company Details')
                    ->schema([
                        TextInput::make('company_name')
                            ->required(),
                        TextInput::make('docket_description')
                            ->required(),
                        TextInput::make('task_description')
                            ->required(),
                        TextInput::make('max_users')
                            ->numeric()
                            ->minValue(1),
                    ]),
                Section::make('Company Details')
                    ->schema([
                        Toggle::make('level_one_in_use'),
                        TextInput::make('level_one_description')
                            ->required(),
                        Toggle::make('level_two_in_use'),
                        TextInput::make('level_two_description')
                            ->required(),
                        Toggle::make('level_three_in_use'),
                        TextInput::make('level_three_description')
                            ->required(),
                        TextInput::make('level_four_description')
                            ->required(),
                        TextInput::make('level_five_description')
                            ->required(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer_subscriptions_count')
                    ->label('Subscriptions')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = \Auth::user();
        if ($user->hasRole('customer_manager')) {
            return parent::getEloquentQuery()->whereHas('users', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            });
        }else{
            return parent::getEloquentQuery()
                ->with('customerSubscriptions')
                ->withCount('customerSubscriptions')
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]);
        }
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['company_name'];
    }

    public static function getRelations(): array
    {
        return [
            'subscriptions' => CustomerSubscriptionsRelationManager::class,
            'users' => CustomerUserRelationManager::class,
        ];
    }

}
