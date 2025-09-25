<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Jobs\SiteDeployment\AddDeploymentScriptOnForgeJob;
use App\Jobs\SiteDeployment\AddEnvVariablesOnForgeJob;
use App\Jobs\SiteDeployment\AddGitRepoOnForgeJob;
use App\Jobs\SiteDeployment\AddSSLOnSiteJob;
use App\Jobs\SiteDeployment\CreateSiteOnForgeJob;
use App\Jobs\SiteDeployment\DeploySite;
use App\Jobs\SiteDeployment\SendSystemConfigJob;
use App\Jobs\SendCommandToForgeJob;
use App\Jobs\SyncForgeJob;
use App\Models\CustomerSubscription;
use App\Models\ForgeServer;
use App\Models\SubscriptionType;
use App\Services\CustomerSubscriptionService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

class CustomerSubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'customerSubscriptions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('url')
            ->columns([
                TextColumn::make('subscriptionType.name')
                    ->label('Subscription Type')
                    ->sortable(),

                TextColumn::make('url')
                    ->label('URL')
                    ->searchable(),

                TextColumn::make('deployed_version')
                    ->label('Deployed Version')
                    ->searchable()
                    ->sortable(),

                ToggleColumn::make('panic_button_enabled')
                    ->label('Panic Button')
                    ->disabled(fn ($record) => !$record || !$this->isAppTypeSubscription($record->subscription_type_id)),
            ])
            ->filters([
                SelectFilter::make('subscription_type_id')
                    ->label('Subscription Type')
                    ->relationship('subscriptionType', 'name')
                    ->options(SubscriptionType::pluck('name', 'id')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Create Subscription')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Section::make('Customer Info')->schema([
                            Hidden::make('urlConfirmed')
                                ->default(false),
                            Hidden::make('postfix')
                                ->default(''),
                            Select::make('subscription_type_id')
                                ->live()
                                ->default(1)
                                ->label('Subscription Type')
                                ->relationship('subscriptionType', 'name')
                                ->required()
                                ->afterStateUpdated(function($get, $set) {
                                    $type = $get('subscription_type_id');
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
                                    $set('theType', $theType);
                                    $set('postfix', '.' . $theType . '.' . $get('vertical'));
                                }),
                            Select::make('vertical')
                                ->live()
                                ->required()
                                ->options([
                                    'blackwidow.org.za' => 'blackwidow.org.za',
                                    'aims.work' => 'aims.work',
                                    'bvigilant.co.za' => 'bvigilant.co.za',
                                    'siyaleader.org.za' => 'siyaleader.org.za'
                                ])
                                ->afterStateUpdated(function($get, $set) {
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
                                ->options(fn() => ForgeServer::pluck('name','forge_server_id')),
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
                                ->label(function($get) {
                                    $types = CustomerSubscriptionService::getLogoDescriptions($get('subscription_type_id'));
                                    return $types ? $types[0] : 'Logo 1';
                                })
                                ->disk('public')
                                ->visibility('public')
                                ->nullable()
                                ->rules(['nullable', 'file', 'max:10240']),
                            FileUpload::make('logo_2')
                                ->live()
                                ->label(function($get) {
                                    $types = CustomerSubscriptionService::getLogoDescriptions($get('subscription_type_id'));
                                    return $types ? $types[1] : 'Logo 2';
                                })
                                ->disk('public')
                                ->visibility('public')
                                ->nullable()
                                ->rules(['nullable', 'file', 'max:10240']),
                            FileUpload::make('logo_3')
                                ->live()
                                ->label(function($get) {
                                    $types = CustomerSubscriptionService::getLogoDescriptions($get('subscription_type_id'));
                                    return $types ? $types[2] : 'Logo 3';
                                })
                                ->disk('public')
                                ->visibility('public')
                                ->nullable()
                                ->rules(['nullable', 'file', 'max:10240']),
                            FileUpload::make('logo_4')
                                ->live()
                                ->label(function($get) {
                                    $types = CustomerSubscriptionService::getLogoDescriptions($get('subscription_type_id'));
                                    return $types ? $types[3] : 'Logo 4';
                                })
                                ->disk('public')
                                ->visibility('public')
                                ->nullable()
                                ->rules(['nullable', 'file', 'max:10240']),
                            FileUpload::make('logo_5')
                                ->live()
                                ->label(function($get) {
                                    $types = CustomerSubscriptionService::getLogoDescriptions($get('subscription_type_id'));
                                    return $types ? $types[4] : 'Logo 5';
                                })
                                ->disk('public')
                                ->visibility('public')
                                ->nullable()
                                ->rules(['nullable', 'file', 'max:10240']),
                        ]),
                    ])
                    ->using(function (array $data): Model {
                        $domain = $data['url'] . $data['postfix'];
                        if (!$this->domainResolvesToIp($domain)) {
                            throw ValidationException::withMessages([
                                'url' => ['The domain does not resolve to a valid IP.'],
                            ]);
                        }

                        $customerSubscription = CustomerSubscription::create([
                            'customer_id' => $this->ownerRecord->id,
                            'subscription_type_id' => $data['subscription_type_id'],
                            'domain' => $domain,
                            'server_id' => $data['server_id'],
                            'url' => 'https://' . $domain,
                            'app_name' => $data['app_name'],
                            'database_name' => $this->cleanDatabaseName($data['database_name']),
                        ]);

                        $this->scheduleDeploymentJobs($customerSubscription);

                        return $customerSubscription;
                    })
                    ->after(function () {
                        Notification::make()
                            ->title('The customer subscription has been created and the deployment process has started.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('navigate')
                    ->label('Navigate')
                    ->icon('heroicon-o-arrow-right')
                    ->action(function ($record) {
                        return redirect()->route('filament.admin.resources.customer-subscriptions.edit', $record);
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private function isAppTypeSubscription($subscriptionTypeId): bool
    {
        // App type subscription IDs: 3 (responder), 4 (reporter), 5 (security), 6 (driver), 7 (survey)
        return in_array($subscriptionTypeId, [3, 4, 5, 6, 7]);
    }

    private function cleanDatabaseName($databaseName): string
    {
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
        $databaseName = str_replace('=', '_', $databaseName);
        $databaseName = str_replace('+', '_', $databaseName);
        $databaseName = str_replace('[', '_', $databaseName);
        $databaseName = str_replace(']', '_', $databaseName);
        $databaseName = str_replace('{', '_', $databaseName);
        $databaseName = str_replace('}', '_', $databaseName);
        $databaseName = str_replace('<', '_', $databaseName);
        $databaseName = str_replace('>', '_', $databaseName);
        $databaseName = str_replace(',', '_', $databaseName);
        $databaseName = str_replace('?', '_', $databaseName);
        
        return $databaseName;
    }

    private function domainResolvesToIp($domain, $set = null, $get = null): bool
    {
        try {
            $dnsRecords = dns_get_record($domain, DNS_A);
            if (!empty($dnsRecords)) {
                foreach ($dnsRecords as $record) {
                    if (isset($record['ip'])) {
                        Notification::make()
                            ->title('Domain Resolves to IP ' . $domain)
                            ->success()
                            ->send();
                        if ($set) {
                            $set('urlConfirmed', true);
                            $set('database_name', $get('url') . '_' . $get('theType') . '_' . $get('theVertical'));
                        }
                        return true;
                    }
                }
            } else {
                Notification::make()
                    ->title('Domain does not resolve to IP ' . $domain)
                    ->danger()
                    ->send();
                return false;
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('Domain does not resolve to IP ' . $domain)
                ->danger()
                ->send();
            return false;
        }
        return false;
    }

    private function scheduleDeploymentJobs(CustomerSubscription $customerSubscription): void
    {
        $jobs = [];
        $seconds = 30;

        $jobs[] = [
            'id' => CreateSiteOnForgeJob::dispatch($customerSubscription->id),
            'progress' => 0
        ];

        $jobs[] = [
            'id' => SyncForgeJob::dispatch($customerSubscription->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        ];

        $seconds += 30;

        $jobs[] = [
            'id' => AddGitRepoOnForgeJob::dispatch($customerSubscription->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        ];

        $seconds += 30;

        $jobs[] = [
            'id' => AddEnvVariablesOnForgeJob::dispatch($customerSubscription->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        ];

        $seconds += 30;

        $jobs[] = [
            'id' => AddDeploymentScriptOnForgeJob::dispatch($customerSubscription->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        ];

        $seconds += 30;

        $jobs[] = [
            'id' => AddSSLOnSiteJob::dispatch($customerSubscription->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        ];

        $seconds += 30;

        $jobs[] = [
            'id' => DeploySite::dispatch($customerSubscription->id)->delay(now()->addSeconds($seconds)),
            'progress' => 0
        ];

        $seconds += 30;

        if (in_array($customerSubscription->subscription_type_id, [1, 2, 9, 10, 11])) {
            $jobs[] = [
                'id' => SendCommandToForgeJob::dispatch($customerSubscription->id, 'php artisan key:generate --force')->delay(now()->addSeconds($seconds)),
                'progress' => 0
            ];
            $seconds += 30;

            $jobs[] = [
                'id' => SendCommandToForgeJob::dispatch($customerSubscription->id, 'php artisan migrate --force')->delay(now()->addSeconds($seconds)),
                'progress' => 0
            ];
            $seconds += 30;

            $jobs[] = [
                'id' => SendCommandToForgeJob::dispatch($customerSubscription->id, 'php artisan db:seed BaseLineSeeder --force')->delay(now()->addSeconds($seconds)),
                'progress' => 0
            ];
            $seconds += 30;

            $jobs[] = [
                'id' => DeploySite::dispatch($customerSubscription->id)->delay(now()->addSeconds($seconds)),
                'progress' => 0
            ];
            $seconds += 30;

            $jobs[] = [
                'id' => SendSystemConfigJob::dispatch($customerSubscription->customer_id)->delay(now()->addSeconds($seconds)),
                'progress' => 0
            ];
            $seconds += 30;

            $jobs[] = [
                'id' => SendCommandToForgeJob::dispatch($customerSubscription->id, 'php artisan storage:link')->delay(now()->addSeconds($seconds)),
                'progress' => 0
            ];
            $seconds += 30;
        }

        $customerSubscription->jobs = json_encode($jobs);
        $customerSubscription->save();
    }
}
