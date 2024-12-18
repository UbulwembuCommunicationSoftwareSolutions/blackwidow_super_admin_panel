<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerSubscription extends CreateRecord
{
    protected static string $resource = CustomerSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [

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
                        ->live()
                        ->reactive()
                        ->label('Subscription Type')
                        ->relationship('subscriptionType', 'name') // Specify the relationship and the display column
                        ->required()
                        ->afterStateUpdated(function($get,$set){
                            $type = $get('subscription_type_id');
                            if((int)$type == 1){
                                $set('postfix', '.console.'.$get('vertical'));
                            }elseif((int)$type == 2){
                                $set('postfix', '.firearm.'.$get('vertical'));
                            }elseif((int)$type == 3){
                                $set('postfix', '.responder.'.$get('vertical'));
                            }elseif((int)$type == 4){
                                $set('postfix', '.reporter.'.$get('vertical'));
                            }elseif((int)$type == 5){
                                $set('postfix', '.security.'.$get('vertical'));
                            }elseif((int)$type == 6){
                                $set('postfix', '.driver.'.$get('vertical'));
                            }elseif((int)$type == 7){
                                $set('postfix', '.survey.'.$get('vertical'));
                            }elseif((int)$type == 8){
                                $set('postfix', 'DO NOT USE');
                            }elseif((int)$type == 9){
                                $set('postfix', '.time.'.$get('vertical'));
                            }elseif((int)$type == 10){
                                $set('postfix', '.stock.'.$get('vertical'));
                            }
                        }),
                    Select::make('vertical')
                        ->live()
                        ->reactive()
                        ->options([
                            'blackwidow.org.za' => 'blackwidow.org.za',
                            'aims.work'=>'aims.work',
                            'bvigilant.co.za'=>'bvigilant.co.za',
                            'siyaleader.org.za'=>'siyaleader.org.za'
                        ])->afterStateUpdated(function($get,$set){
                            $type = $get('subscription_type_id');
                            if((int)$type == 1){
                                $set('postfix', '.console.'.$get('vertical'));
                            }elseif((int)$type == 2){
                                $set('postfix', '.firearm.'.$get('vertical'));
                            }elseif((int)$type == 3){
                                $set('postfix', '.responder.'.$get('vertical'));
                            }elseif((int)$type == 4){
                                $set('postfix', '.reporter.'.$get('vertical'));
                            }elseif((int)$type == 5){
                                $set('postfix', '.security.'.$get('vertical'));
                            }elseif((int)$type == 6){
                                $set('postfix', '.driver.'.$get('vertical'));
                            }elseif((int)$type == 7){
                                $set('postfix', '.survey.'.$get('vertical'));
                            }elseif((int)$type == 8){
                                $set('postfix', 'DO NOT USE');
                            }elseif((int)$type == 9){
                                $set('postfix', '.time.'.$get('vertical'));
                            }elseif((int)$type == 10){
                                $set('postfix', '.stock.'.$get('vertical'));
                            }
                        }),
                    TextInput::make('url')
                        ->live()
                        ->reactive()
                        ->suffix(fn($get) => $get('postfix'))
                        ->extraAttributes(['class' => 'with-suffix'])
                        ->required()
                        ->afterStateUpdated(function($get,$set){
                            $url = $get('url');
                            $ip = $this->domainResolvesToIp($url);
                            if($ip){
                               Notification::make()
                                   ->title('Domain Resolves to IP')
                                   ->success();
                            }else{
                                Notification::make()
                                    ->title('Domain Does Not Resolve to IP')
                                    ->danger();
                            }
                        })
                        ->url(),
                    TextInput::make('app_name')
                        ->required(),
                    TextInput::make('database_name')
                        ->required()
                ]),
                Section::make('ENV File')->schema([
                    Placeholder::make('forge_site_id')
                        ->label('Forge Site ID')
                        ->disabled(),
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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        dd($data);
    }

    public  function afterCreate():void
    {
        dd($this->record);
    }

    function domainResolvesToIp($domain) {
        $dnsRecords = dns_get_record($domain, DNS_A); // Check for A records (IPv4)
        if (!empty($dnsRecords)) {
            foreach ($dnsRecords as $record) {
                if (isset($record['ip'])) {
                    return $record['ip']; // Return the resolved IP address
                }
            }
        }
        return false; // No A records found
    }

}
