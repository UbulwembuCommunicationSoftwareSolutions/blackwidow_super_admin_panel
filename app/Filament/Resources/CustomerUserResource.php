<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Auth;
use App\Filament\Resources\CustomerUserResource\Pages\ListCustomerUsers;
use App\Filament\Resources\CustomerUserResource\Pages\CreateCustomerUser;
use App\Filament\Resources\CustomerUserResource\Pages\EditCustomerUser;
use App\Filament\Resources\CustomerUserResource\Pages;
use App\Models\CustomerUser;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerUserResource extends Resource
{
    protected static ?string $model = CustomerUser::class;

    protected static ?string $slug = 'customer-users';
    protected static string | \UnitEnum | null $navigationGroup = 'Customers';
    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_id')
                    ->required()
                    ->integer(),

                TextInput::make('email_address')
                    ->required(),

                TextInput::make('password')
                    ->required(),

                TextInput::make('first_name')
                    ->required(),

                TextInput::make('last_name')
                    ->required(),

                Toggle::make('is_system_admin')
                    ->label('Is Super Admin'),

                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?CustomerUser $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?CustomerUser $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.company_name'),

                TextColumn::make('email_address'),

                TextColumn::make('first_name'),

                TextColumn::make('last_name'),
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

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerUsers::route('/'),
            'create' => CreateCustomerUser::route('/create'),
            'edit' => EditCustomerUser::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }
}
