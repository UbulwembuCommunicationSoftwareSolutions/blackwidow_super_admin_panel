<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use App\Filament\Resources\Customers\CustomerResource;
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
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

class CustomerUserRelationManager extends RelationManager
{
    protected static string $relationship = 'customerUsers';

    protected static string $resource = CustomerResource::class;

    public function canCreate(): bool
    {
        return true; // Allow creation even on view pages
    }

    public function canEdit($record): bool
    {
        return true; // Allow editing even on view pages
    }

    public function canDelete($record): bool
    {
        return true; // Allow deletion even on view pages
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->searchable()
                            ->relationship('customer', 'company_name')
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
                Section::make('Access Rights')
                    ->schema([
                        Toggle::make('is_system_admin')
                            ->label('Is Super Admin'),
                        Toggle::make('console_access')
                            ->required(),
                        Toggle::make('firearm_access')
                            ->required(),
                        Toggle::make('responder_access')
                            ->required(),
                        Toggle::make('reporter_access')
                            ->required(),
                        Toggle::make('security_access')
                            ->required(),
                        Toggle::make('survey_access')
                            ->required(),
                        Toggle::make('time_and_attendance_access')
                            ->required(),
                        Toggle::make('stock_access')
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
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                Action::make('updatePassword')
                    ->label('Update Password')
                    ->icon('heroicon-m-key')
                    ->form([
                        TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(6),
                        TextInput::make('confirm_password')
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
                Action::make('Send Welcome Email')
                    ->label('Send Welcome Email')
                    ->action(function ($record) {
                        $user = CustomerUser::find($record->id);
                        SendWelcomeEmailJob::dispatch($user);
                    }),
                Action::make('Send Login Email')
                    ->label('Send Login Email')
                    ->form([
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
                Action::make('manageAccessRights')
                    ->label('Manage Access Rights')
                    ->icon('heroicon-m-key')
                    ->form([
                        Section::make('Access Rights')
                            ->schema([
                                Toggle::make('is_system_admin')
                                    ->label('Super Admin')
                                    ->required(),
                                Toggle::make('console_access')
                                    ->label('Console Access')
                                    ->required(),
                                Toggle::make('firearm_access')
                                    ->label('Firearm Access')
                                    ->required(),
                                Toggle::make('responder_access')
                                    ->label('Responder Access')
                                    ->required(),
                                Toggle::make('reporter_access')
                                    ->label('Reporter Access')
                                    ->required(),
                                Toggle::make('security_access')
                                    ->label('Security Access')
                                    ->required(),
                                Toggle::make('survey_access')
                                    ->label('Survey Access')
                                    ->required(),
                                Toggle::make('time_and_attendance_access')
                                    ->label('Time and Attendance Access')
                                    ->required(),
                                Toggle::make('stock_access')
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('Send Login Email')
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
