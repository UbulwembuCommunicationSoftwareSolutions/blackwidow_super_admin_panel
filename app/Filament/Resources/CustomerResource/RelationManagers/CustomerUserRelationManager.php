<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Resources\CustomerResource;
use App\Jobs\SendSubscriptionEmailJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\CustomerSubscription;
use App\Models\CustomerUser;
use App\Models\Payment;
use App\Models\SubscriptionType;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class CustomerUserRelationManager extends RelationManager
{
    protected static string $relationship = 'customerUsers';

    protected static string $resource = CustomerResource::class;
    public function form(Form $form): Form
    {
        return $form
            ->schema([

            Forms\Components\Section::make('User Information')
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
                    PhoneInput::make('cellphone')
                        ->required(),
                    TextInput::make('email_address')
                        ->required(),
                    TextInput::make('password')
                        ->password()
                        ->required(),
            ]),
            Forms\Components\Section::make('Access Rights')
                ->schema([
                    Toggle::make('is_system_admin')
                        ->label('Is Super Admin'),
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
                ])->columns(2)
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email_address')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('first_name')
                    ->label('First Name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Last Name')
                    ->searchable(),
                TextColumn::make('cellphone')
                    ->label('Cellphone')
                    ->searchable(),
                TextColumn::make('console_access')
                    ->label('Console Access'),
                Tables\Columns\CheckboxColumn::make('console_access')
                    ->label('Console Access')
                    ->toggleable(),
                Tables\Columns\CheckboxColumn::make('firearm_access')
                    ->label('Firearm Access'),
                Tables\Columns\CheckboxColumn::make('responder_access')
                    ->label('Responder Access'),
                Tables\Columns\CheckboxColumn::make('reporter_access')
                    ->label('Reporter Access'),
                Tables\Columns\CheckboxColumn::make('security_access')
                    ->label('Security Access'),
                Tables\Columns\CheckboxColumn::make('survey_access')
                    ->label('Survey Access'),
                Tables\Columns\CheckboxColumn::make('time_and_attendance_access')
                    ->label('Time and Attendance Access'),
                Tables\Columns\CheckboxColumn::make('stock_access')
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
                \Filament\Tables\Actions\Action::make('Send Welcome Email')
                    ->label('Send Welcome Email')
                    ->action(function ($record) {
                        $user = CustomerUser::find($record->id);
                        SendWelcomeEmailJob::dispatch($user);
                    }),
                \Filament\Tables\Actions\Action::make('Send Login Email')
                    ->label('Send Login Email')
                    ->form(fn ($record) => [
                        Select::make('subscription_type_id')
                            ->label('Subscription Type')
                            ->required()
                            ->options(fn () => SubscriptionType::pluck('name', 'id')->toArray()),
                    ])
                    ->action(function ($record, $data) {
                        $user = CustomerUser::find($record->id);
                        $subscription = CustomerSubscription::where('customer_id', $record->customer_id)
                            ->where('subscription_type_id', $data['subscription_type_id'])
                            ->first();

                        if ($user && $subscription) {
                            if($user->checkAccess($data['subscription_type_id'])){
                                SendSubscriptionEmailJob::dispatch($user, $subscription);
                            }else{
                                Notification::make()
                                    ->title('User does not have access to this subscription')
                                    ->danger()
                                    ->send();
                            }
                        }else{
                            Notification::make()
                                ->title('Subscription not found')
                                ->danger()
                                ->send();
                        }


                    })
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('Send Login Email')
                        ->label('Send Login Email')
                        ->form([
                            Select::make('subscription_type_id')
                                ->label('Subscription Type')
                                ->required()
                                ->options(fn () => SubscriptionType::pluck('name', 'id')->toArray()),
                        ])
                        ->action(function ($records, $data) {
                            foreach ($records as $record) {
                                $user = CustomerUser::find($record->id);
                                $subscription = CustomerSubscription::where('customer_id', $record->customer_id)
                                    ->where('subscription_type_id', $data['subscription_type_id'])
                                    ->first();

                                if ($user && $subscription) {
                                    if ($user->checkAccess($data['subscription_type_id'])) {
                                        SendSubscriptionEmailJob::dispatch($user, $subscription);
                                    } else {
                                        Notification::make()
                                            ->title("User {$user->name} does not have access to this subscription")
                                            ->danger()
                                            ->send();
                                    }
                                } else {
                                    Notification::make()
                                        ->title("Subscription not found for user {$user->name}")
                                        ->danger()
                                        ->send();
                                }
                            }
                    })
                ])
            ]);
    }
}
