<?php

namespace App\Filament\Resources\CustomerSubscriptions\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use App\Filament\Resources\CustomerSubscriptions\CustomerSubscriptionResource;
use App\Jobs\SiteDeployment\DeploySite;
use App\Models\CustomerSubscription;
use App\Models\ForgeServer;
use App\Services\CustomerSubscriptionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Support\Carbon;

class EditCustomerSubscription extends EditRecord
{
    protected static string $resource = CustomerSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateAppLogos')
                ->action(fn($record)=> CustomerSubscriptionService::generatePWALogos($record->id)),
            DeleteAction::make(),
            Action::make('deploySite')
                ->label('Deploy Site')
                ->action(fn ($record) => DeploySite::dispatch($record->id)),
            Action::make('backToCustomer')
                ->label('Back to Customer')
                ->action(function ($record) {
                    // Redirect to the custom create page
                    return redirect()->route('filament.admin.resources.customers.edit', [
                        'record' => $record->customer_id,
                    ]);
                }),
            Action::make('EditServerDetails')
                ->schema([
                    Select::make('forge_server_id')
                        ->options(fn()=>ForgeServer::pluck('name','forge_server_id')),
                ])
                ->action(function (array $data,  CustomerSubscription $record): void {
                    $record->server_id = $data['forge_server_id'];
                    $record->save();
                })
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                    TextInput::make('domain')
                        ->required(),
                    Select::make('server_id')
                        ->default(fn() => $this->record->server_id)
                        ->options(fn()=>ForgeServer::pluck('name','forge_server_id')),
                    TextInput::make('app_name')
                        ->required(),
                    Toggle::make('panic_button_enabled')
                        ->label('Panic Button')
                        ->disabled(fn ($record) => !$record || !$this->isAppTypeSubscription($record->subscription_type_id)),

                    TextInput::make('database_name')
                        ->required()
                ]),
                Section::make('Deployment Info')->schema([
                    Placeholder::make('deployed_at')
                        ->content(fn($record) => $record->deployed_at ?? Carbon::parse($record->deployed_at)->format('m/d/Y h:i:s A')),
                    Placeholder::make('deployed_version')
                        ->content(fn($record) => $record->deployed_version),
                    Placeholder::make('master_version')
                        ->content(fn($record) => $record->subscriptionType->master_version)
                ]),
                Section::make('ENV File')->schema([
                    Placeholder::make('forge_site_id')
                        ->label('Forge Site ID')
                        ->content(fn($record) => $record->forge_site_id),
                    Placeholder::make('uuid')
                        ->label( 'Site ID')
                        ->content(fn($record) => $record->uuid),
                    Placeholder::make('forge_server_id')
                        ->label('Forge Server ID')
                        ->content(fn($record) => $record->server_id),
                ]),
                Section::make('Logos')->schema([
                    FileUpload::make('logo_1')
                        ->live()
                        ->downloadable()
                        ->reactive()
                        ->label(function($get){
                            $types = CustomerSubscriptionService::getLogoDescriptions($get('subscription_type_id'));
                            if($types){
                                $result = $types[0];
                            }
                            else{
                                $result = 'Logo 1';
                            }
                            return $result;
                        })

                        ->disk('public')
                        ->visibility('public') // Or 'private' based on your requirements
                        ->disk('public') // The disk defined in your `config/filesystems.php`
                        ->nullable()
                        ->rules(['nullable', 'file', 'max:10240']),

                    FileUpload::make('logo_2')
                        ->live()
                        ->reactive()
                        ->downloadable()

                        ->label(function($get){
                            $types = CustomerSubscriptionService::getLogoDescriptions($get('subscription_type_id'));
                            if($types){
                                $result = $types[1];
                            }
                            else{
                                $result = 'Logo 1';
                            }
                            return $result;
                        })
                        ->disk('public')
                        ->visibility('public') // Or 'private' based on your requirements
                        ->disk('public') // The disk defined in your `config/filesystems.php`
                        ->nullable()
                        ->rules(['nullable', 'file', 'max:10240']),

                    FileUpload::make('logo_3')
                        ->live()
                        ->reactive()
                        ->downloadable()

                        ->label(function($get){
                            $types = CustomerSubscriptionService::getLogoDescriptions($get('subscription_type_id'));
                            if($types){
                                $result = $types[2];
                            }
                            else{
                                $result = 'Logo 1';
                            }
                            return $result;
                        })
                        ->disk('public')
                        ->visibility('public') // Or 'private' based on your requirements
                        ->disk('public') // The disk defined in your `config/filesystems.php`
                        ->nullable()
                        ->rules(['nullable', 'file', 'max:10240']),

                    FileUpload::make('logo_4')
                        ->live()
                        ->reactive()
                        ->downloadable()

                        ->label(function($get){
                            $types = CustomerSubscriptionService::getLogoDescriptions($get('subscription_type_id'));
                            if($types){
                                $result = $types[3];
                            }
                            else{
                                $result = 'Logo 1';
                            }
                            return $result;
                        })
                        ->disk('public')
                        ->visibility('public') // Or 'private' based on your requirements
                        ->disk('public') // The disk defined in your `config/filesystems.php`
                        ->nullable()
                        ->rules(['nullable', 'file', 'max:10240']),

                    FileUpload::make('logo_5')
                        ->live()
                        ->reactive()
                        ->downloadable()

                        ->label(function($get){
                            $types = CustomerSubscriptionService::getLogoDescriptions($get('subscription_type_id'));
                            if($types){
                                $result = $types[4];
                            }
                            else{
                                $result = 'Logo 1';
                            }
                            return $result;
                        })
                        ->disk('public')
                        ->visibility('public') // Or 'private' based on your requirements
                        ->disk('public') // The disk defined in your `config/filesystems.php`
                        ->nullable()
                        ->rules(['nullable', 'file', 'max:10240']),
                ]),
            ]);
    }
    private function isAppTypeSubscription($subscriptionTypeId): bool
    {
        // App type subscription IDs: 3 (responder), 4 (reporter), 5 (security), 6 (driver), 7 (survey)
        return in_array($subscriptionTypeId, [3, 4, 5, 6, 7]);
    }
}
