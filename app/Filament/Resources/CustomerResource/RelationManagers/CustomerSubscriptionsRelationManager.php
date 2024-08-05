<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerSubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'customer_subscriptions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('customer_id')
                    ->label('Customer')
                    ->searchable()
                    ->relationship('customer', 'company_name') // Specify the relationship and the display column
                    ->required()
                    ->default(fn () => $this->ownerRecord->id),
                TextInput::make('url')
                    ->required()
                    ->url(),
                Select::make('subscription_type_id')
                    ->label('Subscription Type')
                    ->relationship('subscriptionType', 'name') // Specify the relationship and the display column
                    ->required(),

                FileUpload::make('logo_1')
                    ->label('Logo 1')
                    ->disk('public')
                    ->visibility('public') // Or 'private' based on your requirements
                    ->disk('public') // The disk defined in your `config/filesystems.php`
                    ->nullable()
                    ->rules(['nullable', 'file', 'max:10240']),

                FileUpload::make('logo_2')
                    ->label('Logo 2')
                    ->disk('public')
                    ->visibility('public') // Or 'private' based on your requirements
                    ->disk('public') // The disk defined in your `config/filesystems.php`
                    ->nullable()
                    ->rules(['nullable', 'file', 'max:10240']),

                FileUpload::make('logo_3')
                    ->label('Logo 3')
                    ->disk('public')
                    ->visibility('public') // Or 'private' based on your requirements
                    ->disk('public') // The disk defined in your `config/filesystems.php`
                    ->nullable()
                    ->rules(['nullable', 'file', 'max:10240']),

                FileUpload::make('logo_4')
                    ->label('Logo 4')
                    ->disk('public')
                    ->visibility('public') // Or 'private' based on your requirements
                    ->disk('public') // The disk defined in your `config/filesystems.php`
                    ->nullable()
                    ->rules(['nullable', 'file', 'max:10240']),

                FileUpload::make('logo_5')
                    ->label('Logo 5')
                    ->disk('public')
                    ->visibility('public') // Or 'private' based on your requirements
                    ->disk('public') // The disk defined in your `config/filesystems.php`
                    ->nullable()
                    ->rules(['nullable', 'file', 'max:10240']),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('url')
            ->columns([
                TextColumn::make('subscriptionType.name')
                    ->label('Subscription Type'),

                TextColumn::make('url')
                    ->label('URL'),

            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                \Filament\Tables\Actions\Action::make('navigate')
                    ->label('Navigate')
                    ->icon('heroicon-o-arrow-right')
                    ->action(function ($record) {
                        return redirect()->route('filament.admin.resources.customer-subscriptions.edit', $record);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
