<?php

namespace App\Filament\Resources\CustomerUsers;

use App\Filament\Resources\CustomerUsers\Pages\CreateCustomerUser;
use App\Filament\Resources\CustomerUsers\Pages\EditCustomerUser;
use App\Filament\Resources\CustomerUsers\Pages\ListCustomerUsers;
use App\Filament\Resources\CustomerUsers\Schemas\CustomerUserForm;
use App\Filament\Resources\CustomerUsers\Tables\CustomerUsersTable;
use App\Models\CustomerUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerUserResource extends Resource
{
    protected static ?string $model = CustomerUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CustomerUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerUsersTable::configure($table);
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
            'index' => ListCustomerUsers::route('/'),
            'create' => CreateCustomerUser::route('/create'),
            'edit' => EditCustomerUser::route('/{record}/edit'),
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
