<?php

namespace App\Filament\Resources\CustomerSubscriptions\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\Action;
use Exception;
use App\Console\Commands\ForgeGetters\SyncForge;
use App\Filament\Resources\CustomerSubscriptions\CustomerSubscriptionResource;
use App\Jobs\SendCommandToForge;
use App\Jobs\SendCommandToForgeJob;
use App\Jobs\SiteDeployment\AddDeploymentScriptOnForgeJob;
use App\Jobs\SiteDeployment\AddEnvVariablesOnForgeJob;
use App\Jobs\SiteDeployment\AddGitRepoOnForgeJob;
use App\Jobs\SiteDeployment\AddSSLOnSiteJob;
use App\Jobs\SiteDeployment\CreateSiteOnForgeJob;
use App\Jobs\SiteDeployment\DeploySite;
use App\Jobs\SiteDeployment\SendSystemConfigJob;
use App\Jobs\SyncForgeJob;
use App\Models\CustomerSubscription;
use App\Models\ForgeServer;
use App\Services\CustomerSubscriptionService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer Info')->schema([
                    Hidden::make('urlConfirmed')
                        ->default(false),
                    Hidden::make('postfix')
                        ->default(''),
                    Select::make('customer_id')
                        ->label('Customer')
                        ->searchable()
                        ->preload()
                        ->default(request('customer'))  // Prefill using the query parameter
                        ->relationship('customer', 'company_name') // Specify the relationship and the display column
                        ->required(),
                    Select::make('subscription_type_id')
                        ->live()
                        ->reactive()
                        ->default(1)
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
                                11 => 'information',
                                default => 'unknown',
                            };
                            $set('theVertical', $verticalMap[$get('vertical')] ?? 'unknown');
                            $set('theType', $theType);
                            $set('postfix', '.' . $theType . '.' . $get('vertical'));
                            $set('theType',$theType);
                            $set('postfix', '.'.$theType.'.'.$get('vertical'));
                        }),
                    TextInput::make('url')
                        ->live(debounce: 1000)
                        ->suffix(fn($get) => $get('postfix'))
                        ->extraAttributes(['class' => 'with-suffix'])
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->rules([
                            'required',
                            'unique:customer_subscriptions,url',
                            'regex:/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$/',
                            'min:3',
                            'max:63'
                        ])
                        ->validationAttribute('URL')
                        ->afterStateUpdated(function($get, $set) {
                            $url = strtolower(trim($get('url')));
                            $set('url', $url);
                            $set('urlConfirmed', false);
                            $set('database_name', $url . '_' . $get('theType') . '_' . $get('theVertical'));

                            // Check if URL is unique
                            $domain = $url . $get('postfix');
                            $exists = CustomerSubscription::where('url', 'https://' . $domain)->exists();
                            if ($exists) {
                                Notification::make()
                                    ->title('This URL is already taken')
                                    ->danger()
                                    ->send();
                                $set('urlConfirmed', false);
                            } else {
                                $this->domainResolvesToIp($domain, $set, $get);
                            }
                        })
                        ->hintAction(
                            Action::make('verifyUrl')
                                ->icon(function($get) {
                                    if($get('urlConfirmed')) {
                                        return 'heroicon-o-check-circle';
                                    } else {
                                        return 'heroicon-o-exclamation-circle';
                                    }
                                })
                                ->action(function ($get, $set) {
                                    $this->domainResolvesToIp($get('url').$get('postfix'), $set, $get);
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
                        ->live()
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
               'server_id' => $data['server_id'],
               'url' => 'https://'.$domain,
               'app_name' => $data['app_name'],
               'database_name' => $this->cleanDatabaseName($data['database_name']),
           ]);
           return $customerSubscription;
       }

   }

    public function cleanDatabaseName($databaseName){
        $databaseName = str_replace(' ', '_', $databaseName);
        $databaseName = str_replace('-', '_', $databaseName);
        $databaseName = str_replace('.', '_', $databaseName);
        $databaseName = str_replace('/', '_', $databaseName);
        $databaseName = str_replace('\\', '_', $databaseName);
        $databaseName = str_replace('|', '_', $databaseName);
        $databaseName = str_replace(';', '_', $databaseName);
        $databaseName = str_replace(':', '_', $databaseName);
        $databaseName = str_replace('"', '_', $databaseName);
        $databaseName = str_replace('\'', '_', $databaseName);
        $databaseName = str_replace('`', '_', $databaseName);
        $databaseName = str_replace('~', '_', $databaseName);
        $databaseName = str_replace('!', '_', $databaseName);
        $databaseName = str_replace('@', '_', $databaseName);
        $databaseName = str_replace('#', '_', $databaseName);
        $databaseName = str_replace('$', '_', $databaseName);
        $databaseName = str_replace('%', '_', $databaseName);
        $databaseName = str_replace('^', '_', $databaseName);
        $databaseName = str_replace('&', '_', $databaseName);
        $databaseName = str_replace('*', '_', $databaseName);
        $databaseName = str_replace('(', '_', $databaseName);
        $databaseName = str_replace(')', '_', $databaseName);
        $databaseName = str_replace('-', '_', $databaseName);
        $databaseName = str_replace('=', '_', $databaseName);
        $databaseName = str_replace('+', '_', $databaseName);
        $databaseName = str_replace('[', '_', $databaseName);
        $databaseName = str_replace(']', '_', $databaseName);
        $databaseName = str_replace('{', '_', $databaseName);
        $databaseName = str_replace('}', '_', $databaseName);
        $databaseName = str_replace('<', '_', $databaseName);
        $databaseName = str_replace('>', '_', $databaseName);
        $databaseName = str_replace(',', '_', $databaseName);
        $databaseName = str_replace('.', '_', $databaseName);
        $databaseName = str_replace('?', '_', $databaseName);
        $databaseName = str_replace('/', '_', $databaseName);
        $databaseName = str_replace('\\', '_', $databaseName);
        $databaseName = str_replace('|', '_', $databaseName);
        $databaseName = str_replace(';', '_', $databaseName);
        $databaseName = str_replace(':', '_', $databaseName);
        $databaseName = str_replace('"', '_', $databaseName);
        $databaseName = str_replace('\'', '_', $databaseName);
        $databaseName = str_replace('`', '_', $databaseName);
        $databaseName = str_replace('~', '_', $databaseName);
        $databaseName = str_replace('!', '_', $databaseName);
        $databaseName = str_replace('@', '_', $databaseName);
        $databaseName = str_replace('#', '_', $databaseName);
        $databaseName = str_replace('$', '_', $databaseName);
        $databaseName = str_replace('%', '_', $databaseName);
        $databaseName = str_replace('^', '_', $databaseName);
        $databaseName = str_replace('&', '_', $databaseName);
        $databaseName = str_replace('*', '_', $databaseName);
        $databaseName = str_replace('(', '_', $databaseName);
        $databaseName = str_replace(')', '_', $databaseName);
        $databaseName = str_replace('-', '_', $databaseName);
        $databaseName = str_replace('=', '_', $databaseName);
        $databaseName = str_replace('+', '_', $databaseName);
        $databaseName = str_replace('[', '_', $databaseName);
        $databaseName = str_replace(']', '_', $databaseName);
        $databaseName = str_replace('{', '_', $databaseName);
        $databaseName = str_replace('}', '_', $databaseName);
        $databaseName = str_replace('<', '_', $databaseName);
        $databaseName = str_replace('>', '_', $databaseName);
        $databaseName = str_replace(',', '_', $databaseName);
        $databaseName = str_replace('.', '_', $databaseName);
        $databaseName = str_replace('?', '_', $databaseName);
        return $databaseName;
    }

    public  function afterCreate():void
    {
        $jobs = [];
        $seconds = 30;
        $jobs[] = array(
            'id' => CreateSiteOnForgeJob::dispatch($this->record->id),
            'progress' => 0
        );

        $jobs[] = array(
            'id' => SyncForgeJob::dispatch($this->record->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        );

        $seconds += 30;

        $jobs[] = array(
            'id' => AddGitRepoOnForgeJob::dispatch($this->record->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        );

        $seconds += 30;

        $jobs[] = array(
            'id' => AddEnvVariablesOnForgeJob::dispatch($this->record->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        );
        $seconds += 30;


        $jobs[] = array(
            'id' => AddDeploymentScriptOnForgeJob::dispatch($this->record->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        );
        $seconds += 30;


        $jobs[] = array(
            'id' => AddSSLOnSiteJob::dispatch($this->record->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        );
        $seconds += 30;


        $jobs[] = array(
            'id' => DeploySite::dispatch($this->record->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        );
        $seconds += 30;


        if (in_array($this->record->subscription_type_id, [1, 2, 9, 10,11])) {
            $jobs[] = array(
                'id' => SendCommandToForgeJob::dispatch($this->record->id,'php artisan key:generate --force')->delay(now()->addSeconds($seconds)),
                'progress' => 0
            );
            $seconds += 30;


            $jobs[] = array(
                'id' => SendCommandToForgeJob::dispatch($this->record->id,'php artisan migrate --force')->delay(now()->addSeconds($seconds)),
                'progress' => 0
            );
            $seconds += 30;


            $jobs[] = array(
                'id' => SendCommandToForgeJob::dispatch($this->record->id,'php artisan db:seed BaseLineSeeder --force')->delay(now()->addSeconds($seconds)),
                'progress' => 0
            );
            $seconds += 30;

            $jobs[] = array(
                'id' => DeploySite::dispatch($this->record->id)->delay(now()->addSeconds($seconds)),
                'progress' => 0
            );
            $seconds += 30;

            $jobs[] = array(
                'id' => SendSystemConfigJob::dispatch($this->record->customer_id)->delay(now()->addSeconds($seconds)),
                'progress' => 0
            );
            $seconds += 30;
            $jobs[] = array(
                'id' => SendCommandToForgeJob::dispatch($this->record->id,'php artisan storage:link')->delay(now()->addSeconds($seconds)),
                'progress' => 0
            );
            $seconds += 30;

        }


        Notification::make()
            ->title('The customer subscription has been created and the deployment process has started.')
            ->success()
            ->send();
        $this->record->jobs = json_encode($jobs);
        $this->record->save();
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
        }catch (Exception $e){
            Notification::make()
                ->title('Domain does not resolve to IP '.$domain)
                ->danger()
                ->send();
            return false;
        }
        return false;
    }

}
