<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
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
                Forms\Components\TextInput::make('url')
                    ->required()
                    ->maxLength(255),
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
                Tables\Columns\TextColumn::make('url'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
