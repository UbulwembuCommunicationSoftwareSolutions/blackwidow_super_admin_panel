<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Resources\CustomerResource;
use App\Models\SubscriptionType;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerUserRelationManager extends RelationManager
{
    protected static string $relationship = 'customerUsers';

    protected static string $resource = CustomerResource::class;
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
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('email_address')
                    ->required(),
                TextInput::make('password')
                    ->required(),
                Forms\Components\Toggle::make('console_access')
                    ->required(),
                Forms\Components\Toggle::make('firearm_access')
                    ->required(),
                Forms\Components\Toggle::make('responder_access')
                    ->required(),
                Forms\Components\Toggle::make('reporter_access')
                    ->required(),
                Forms\Components\Toggle::make('security_access')
                    ->required(),
                Forms\Components\Toggle::make('survey_access')
                    ->required(),
                Forms\Components\Toggle::make('time_and_attendance_access')
                    ->required(),
                Forms\Components\Toggle::make('stock_access')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customerUser.email')
                    ->label('Email'),
                TextColumn::make('customerUser.first_name')
                    ->label('First Name'),
                TextColumn::make('customerUser.last_name')
                    ->label('Last Name'),
                TextColumn::make('customerUser.cellphone')
                    ->label('Cellphone'),
                TextColumn::make('customerUser.console_access')
                    ->label('Console Access'),
                TextColumn::make('customerUser.firearm_access')
                    ->label('Firearm Access'),
                TextColumn::make('customerUser.responder_access')
                    ->label('Responder Access'),
                TextColumn::make('customerUser.reporter_access')
                    ->label('Reporter Access'),
                TextColumn::make('customerUser.security_access')
                    ->label('Security Access'),
                TextColumn::make('customerUser.survey_access')
                    ->label('Survey Access'),
                TextColumn::make('customerUser.time_and_attendance_access')
                    ->label('Time and Attendance Access'),
                TextColumn::make('customerUser.stock_access')
                    ->label('Stock Access'),

            ])
            ->filters([

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
