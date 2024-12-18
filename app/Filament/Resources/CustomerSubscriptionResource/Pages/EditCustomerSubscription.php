<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use App\Jobs\DeploySite;
use App\Models\ForgeServer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;

class EditCustomerSubscription extends EditRecord
{
    protected static string $resource = CustomerSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('deploySite')
                ->label('Deploy Site')
                ->action(fn ($record) => DeploySite::dispatch($record->id)),
            Action::make('EditServerDetails')
                ->form([
                    Select::make('forge_server_id')
                        ->options(fn()=>ForgeServer::pluck('name','forge_server_id')),
                ])
                ->action(function (array $data,  $record): void {
                    $record->forge_server_id = $data('forge_server_id');
                    $record->save();
                })
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Customer Info')->schema([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->searchable()
                        ->preload()
                        ->reactive()
                        ->relationship('customer', 'company_name') // Specify the relationship and the display column
                        ->required(),
                    Select::make('subscription_type_id')
                        ->label('Subscription Type')
                        ->relationship('subscriptionType', 'name') // Specify the relationship and the display column
                        ->required(),
                    TextInput::make('url')
                        ->required()
                        ->url(),
                    TextInput::make('app_name')
                        ->required(),
                    TextInput::make('database_name')
                        ->required()
                ]),
                Section::make('ENV File')->schema([
                    Placeholder::make('forge_site_id')
                        ->label('Forge Site ID')
                        ->content(fn($record) => $record->forge_site_id),
                    Placeholder::make('forge_server_id')
                        ->label('Forge Server ID')
                        ->content(fn($record) => $record->forge_server_id),
                ]),
                Section::make('Logos')->schema([
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
                ]),
            ]);
    }
}
