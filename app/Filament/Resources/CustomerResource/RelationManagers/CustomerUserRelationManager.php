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
use Illuminate\Validation\Rule;
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
                        ->required()
                        ->rules(function ($record) {
                            return [
                                Rule::unique('customer_users', 'cellphone')
                                    ->where(fn ($query) => $query->where('customer_id', $record->customer_id ?? null))
                                    ->ignore($record?->id),
                            ];
                        }),
                    TextInput::make('email_address')
                        ->required()
                        ->email()
                        ->rules(function ($record) {
                            return [
                                Rule::unique('customer_users', 'email_address')
                                    ->where(fn ($query) => $query->where('customer_id', $record->customer_id ?? null))
                                    ->ignore($record?->id),
                            ];
                        }),
                    TextInput::make('password')
                        ->password()
                        ->required()
                        ->minLength(6)
                        ->required(fn (string $operation): bool => $operation === 'create'),
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
            ])
            ->filters([

            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('updatePassword')
                    ->label('Update Password')
                    ->icon('heroicon-m-key')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(6),
                        \Filament\Forms\Components\TextInput::make('confirm_password')
                            ->label('Confirm Password')
                            ->password()
                            ->required()
                            ->minLength(6),
                    ])
                    ->action(function (CustomerUser $record, array $data) {
                        if($data['new_password'] !== $data['confirm_password']){
                            Notification::make()
                                ->title('Passwords do not match')
                                ->danger()
                                ->send();
                            return;
                        }else{
                            $record->password = $data['new_password'];
                            $record->save();

                            Notification::make()
                                ->title('Password updated successfully')
                                ->success()
                                ->send();
                        }
                    }),
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


                    }),
                Tables\Actions\Action::make('manageAccessRights')
                    ->label('Manage Access Rights')
                    ->icon('heroicon-m-key')
                    ->form([
                        Forms\Components\Section::make('Access Rights')
                            ->schema([
                                Forms\Components\Toggle::make('is_system_admin')
                                    ->label('Super Admin')
                                    ->required(),
                                Forms\Components\Toggle::make('console_access')
                                    ->label('Console Access')
                                    ->required(),
                                Forms\Components\Toggle::make('firearm_access')
                                    ->label('Firearm Access')
                                    ->required(),
                                Forms\Components\Toggle::make('responder_access')
                                    ->label('Responder Access')
                                    ->required(),
                                Forms\Components\Toggle::make('reporter_access')
                                    ->label('Reporter Access')
                                    ->required(),
                                Forms\Components\Toggle::make('security_access')
                                    ->label('Security Access')
                                    ->required(),
                                Forms\Components\Toggle::make('survey_access')
                                    ->label('Survey Access')
                                    ->required(),
                                Forms\Components\Toggle::make('time_and_attendance_access')
                                    ->label('Time and Attendance Access')
                                    ->required(),
                                Forms\Components\Toggle::make('stock_access')
                                    ->label('Stock Access')
                                    ->required(),
                            ])->columns(2)
                    ])
                    ->fillForm(fn (CustomerUser $record): array => [
                        'console_access' => $record->console_access,
                        'firearm_access' => $record->firearm_access,
                        'responder_access' => $record->responder_access,
                        'reporter_access' => $record->reporter_access,
                        'security_access' => $record->security_access,
                        'survey_access' => $record->survey_access,
                        'time_and_attendance_access' => $record->time_and_attendance_access,
                        'stock_access' => $record->stock_access,
                        'is_system_admin' => $record->is_system_admin,
                    ])
                    ->action(function (CustomerUser $record, array $data) {
                        $record->update($data);

                        Notification::make()
                            ->title('Access rights updated successfully')
                            ->success()
                            ->send();
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
