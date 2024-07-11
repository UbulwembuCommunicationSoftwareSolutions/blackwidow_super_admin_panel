<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerSubscriptionResource\Pages;
use App\Models\CustomerSubscription;
use Filament\Forms\Components\FileUpload;
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

class CustomerSubscriptionResource extends Resource
{
    protected static ?string $model = CustomerSubscription::class;

    protected static ?string $slug = 'customer-subscriptions';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name') // Specify the relationship and the display column
                    ->required(),

                Placeholder::make('created_at')
                    ->label('Created Date')
                    ->content(fn(?CustomerSubscription $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                Placeholder::make('updated_at')
                    ->label('Last Modified Date')
                    ->content(fn(?CustomerSubscription $record): string => $record?->updated_at?->diffForHumans() ?? '-'),

                TextInput::make('url')
                    ->required()
                    ->url(),

                TextInput::make('subscription_type_id')
                    ->required()
                    ->integer(),

                FileUpload::make('logo_1')
                    ->label('Upload File')
                    ->directory('uploads') // The directory where files will be stored
                    ->visibility('public') // Or 'private' based on your requirements
                    ->disk('public'), // The disk defined in your `config/filesystems.php`

                FileUpload::make('logo_2')
                    ->label('Upload File')
                    ->directory('uploads') // The directory where files will be stored
                    ->visibility('public') // Or 'private' based on your requirements
                    ->disk('public'), // The disk defined in your `config/filesystems.php`

                FileUpload::make('logo_3')
                    ->label('Upload File')
                    ->directory('uploads') // The directory where files will be stored
                    ->visibility('public') // Or 'private' based on your requirements
                    ->disk('public'), // The disk defined in your `config/filesystems.php`

                FileUpload::make('logo_4')
                    ->label('Upload File')
                    ->directory('uploads') // The directory where files will be stored
                    ->visibility('public') // Or 'private' based on your requirements
                    ->disk('public'), // The disk defined in your `config/filesystems.php`

                FileUpload::make('logo_5')
                    ->label('Upload File')
                    ->directory('uploads') // The directory where files will be stored
                    ->visibility('public') // Or 'private' based on your requirements
                    ->disk('public'), // The disk defined in your `config/filesystems.php`

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url'),

                TextColumn::make('subscription_type_id'),

                TextColumn::make('logo_1'),

                TextColumn::make('logo_2'),

                TextColumn::make('logo_3'),

                TextColumn::make('logo_4'),

                TextColumn::make('logo_5'),

                TextColumn::make('customer_id'),
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
            'index' => Pages\ListCustomerSubscriptions::route('/'),
            'create' => Pages\CreateCustomerSubscription::route('/create'),
            'edit' => Pages\EditCustomerSubscription::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }
}
