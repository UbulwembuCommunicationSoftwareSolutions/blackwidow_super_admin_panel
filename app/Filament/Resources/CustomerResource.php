<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use Auth;
use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers\CustomerSubscriptionsRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\CustomerUserRelationManager;
use App\Models\Customer;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
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

    protected static string | \UnitEnum | null $navigationGroup = 'Customers';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                        Toggle::make('level_one_in_use')
                            ->live()
                            ->reactive(),
                        TextInput::make('level_one_description')
                            ->hidden(fn($get) => $get('level_one_in_use') === false)
                            ->default('Level One')
                            ->required(),
                        Toggle::make('level_two_in_use')
                            ->live()
                            ->reactive(),
                        TextInput::make('level_two_description')
                            ->hidden(fn($get) => $get('level_two_in_use') === false)
                            ->default('Level Two')
                            ->required(),
                        Toggle::make('level_three_in_use')
                            ->live()
                            ->reactive(),
                        TextInput::make('level_three_description')
                            ->hidden(fn($get) => $get('level_three_in_use') === false)
                            ->default('Level Three')
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
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
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
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
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
