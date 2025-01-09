<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerUserResource\Pages;
use App\Models\CustomerUser;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerUserResource extends Resource
{
    protected static ?string $model = CustomerUser::class;

    protected static ?string $slug = 'customer-users';
    protected static ?string $navigationGroup = 'Customers';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerUsers::route('/'),
            'create' => Pages\CreateCustomerUser::route('/create'),
            'edit' => Pages\EditCustomerUser::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }
}
