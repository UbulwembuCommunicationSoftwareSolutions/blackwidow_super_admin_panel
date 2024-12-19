<?php

namespace App\Filament\Resources\CustomerSubscriptionResource\Pages;

use App\Filament\Resources\CustomerSubscriptionResource;
use App\Jobs\SiteDeployment\CreateSiteOnForgeJob;
use App\Models\CustomerSubscription;
use App\Models\ForgeServer;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

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
                    Hidden::make('urlConfirmed')
                        ->default(false),
                    Hidden::make('postfix')
                        ->default(''),
                    Select::make('customer_id')
                        ->label('Customer')
                        ->searchable()
                        ->preload()

                        ->relationship('customer', 'company_name') // Specify the relationship and the display column
                        ->required(),
                    Select::make('subscription_type_id')
                        ->live()

                        ->label('Subscription Type')
                        ->relationship('subscriptionType', 'name') // Specify the relationship and the display column
                        ->required()
                        ->afterStateUpdated(function($get,$set){
                            $type = $get('subscription_type_id');
                            $theType = 'console';
                            if((int)$type == 1){
                                $theType = 'console';
                            }elseif((int)$type == 2){
                                $theType = 'firearm';
                            }elseif((int)$type == 3){
                                $theType = 'responder';
                            }elseif((int)$type == 4){
                                $theType = 'reporter';
                            }elseif((int)$type == 5){
                                $theType = 'security';
                            }elseif((int)$type == 6){
                                $theType = 'driver';
                            }elseif((int)$type == 7){
                                $theType = 'survey';
                            }elseif((int)$type == 8){
                                $theType = 'DONOTUSE';
                            }elseif((int)$type == 9){
                                $theType = 'time';
                            }elseif((int)$type == 10){
                                $theType = 'stock';
                            }
                            $set('theType',$theType);
                            $set('postfix', '.'.$theType.'.'.$get('vertical'));
                        }),
                    Select::make('vertical')
                        ->live()

                        ->options([
                            'blackwidow.org.za' => 'blackwidow.org.za',
                            'aims.work'=>'aims.work',
                            'bvigilant.co.za'=>'bvigilant.co.za',
                            'siyaleader.org.za'=>'siyaleader.org.za'
                        ])->afterStateUpdated(function($get,$set){
                            $type = $get('subscription_type_id');
                            $verticalMap = [
                                'blackwidow.org.za' => 'blackwidow',
                                'aims.work' => 'aims_work',
                                'bvigilant.co.za' => 'bvigilant',
                                'siyaleader.org.za' => 'siyaleader',
                            ];
                            $theType = match ((int) $type) {
                                1 => 'console',
                                2 => 'firearm',
                                3 => 'responder',
                                4 => 'reporter',
                                5 => 'security',
                                6 => 'driver',
                                7 => 'survey',
                                8 => 'DONOTUSE',
                                9 => 'time',
                                10 => 'stock',
                                default => 'unknown',
                            };
                            $set('theVertical', $verticalMap[$get('vertical')] ?? 'unknown');
                            $set('theType', $theType);
                            $set('postfix', '.' . $theType . '.' . $get('vertical'));
                            $set('theType',$theType);
                            $set('postfix', '.'.$theType.'.'.$get('vertical'));
                        }),
                    TextInput::make('url')
                        ->live(debounce: 1000) // Debounce updates for 500ms
                        ->suffix(fn($get) => $get('postfix'))
                        ->extraAttributes(['class' => 'with-suffix'])
                        ->required()
                        ->afterStateUpdated(function($get,$set){
                            $set('urlConfirmed', false);
                            $set('database_name',$get('url').'_'.$get('theType').'_'.$get('theVertical'));
                        })
                        ->hintAction(
                            Action::make('verifyUrl')
                                ->icon(function($get){
                                   if($get('urlConfirmed')){
                                       return 'heroicon-o-check-circle';
                                   }else{
                                       return 'heroicon-o-exclamation-circle';
                                   }
                                })
                                ->action(function ($get,$set) {
                                   $this->domainResolvesToIp($get('url').$get('postfix'),$set,$get);
                                })
                        ),
                    Select::make('server_id')
                        ->required()
                        ->options(fn()=>ForgeServer::pluck('name','forge_server_id')),
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

   protected function handleRecordCreation(array $data): Model
   {
       $domain = $data['url'].$data['postfix'];
       if (!$this->domainResolvesToIp($domain)) {
           throw ValidationException::withMessages([
               'url' => ['The domain does not resolve to a valid IP.'], // Pass an array of error messages
           ]);
       }else{
           $customerSubscription = CustomerSubscription::create([
               'customer_id' => $data['customer_id'],
               'subscription_type_id' => $data['subscription_type_id'],
               'domain' => $domain,
               'url' => 'https://'.$domain,
               'app_name' => $data['app_name'],
               'database_name' => $data['database_name'],
           ]);
           return $customerSubscription;
       }

   }

    public  function afterCreate():void
    {
        CreateSiteOnForgeJob::dispatch($this->record);
    }

    function domainResolvesToIp($domain,$set =null,$get =null) {
        try{
            $dnsRecords = dns_get_record($domain, DNS_A); // Check for AAAA records (IPv6)
            if (!empty($dnsRecords)) {
                foreach ($dnsRecords as $record) {
                    if (isset($record['ip'])) {
                        Notification::make()
                            ->title('Domain Resolves to IP '.$domain)
                            ->success()
                            ->send();
                        if($set){
                            $set('urlConfirmed',true);
                            $set('database_name',$get('url').'_'.$get('theType').'_'.$get('theVertical'));
                        }
                        return true;
                    }
                }
            }else{
                Notification::make()
                    ->title('Domain does not resolve to IP '.$domain)
                    ->danger()
                    ->send();
                return false;
            }
        }catch (\Exception $e){
            Notification::make()
                ->title('Domain does not resolve to IP '.$domain)
                ->danger()
                ->send();
            return false;
        }
        return false;
    }

}
