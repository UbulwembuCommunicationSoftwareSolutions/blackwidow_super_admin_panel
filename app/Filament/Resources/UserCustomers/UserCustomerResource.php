<?php

namespace App\Filament\Resources\UserCustomers;

use App\Filament\Resources\UserCustomers\Pages\CreateUserCustomer;
use App\Filament\Resources\UserCustomers\Pages\EditUserCustomer;
use App\Filament\Resources\UserCustomers\Pages\ListUserCustomers;
use App\Filament\Resources\UserCustomers\Schemas\UserCustomerForm;
use App\Filament\Resources\UserCustomers\Tables\UserCustomersTable;
use App\Models\UserCustomer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserCustomerResource extends Resource
{
    protected static ?string $model = UserCustomer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return UserCustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserCustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserCustomers::route('/'),
            'create' => CreateUserCustomer::route('/create'),
            'edit' => EditUserCustomer::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
