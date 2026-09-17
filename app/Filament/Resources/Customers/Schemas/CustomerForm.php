<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    /**
     * Mailers Laravel can drive from MAIL_MAILER / MAIL_TRANSPORT. See config/mail.php.
     *
     * @return array<string, string>
     */
    private static function mailerOptions(): array
    {
        return array_combine(
            $mailers = ['smtp', 'sendmail', 'ses', 'mailgun', 'postmark', 'resend', 'log', 'array', 'failover', 'roundrobin'],
            $mailers
        );
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('company_name')
                    ->required(),
                TextInput::make('google_api_key')
                    ->label('Google API key')
                    ->helperText('Written to GOOGLE_MAPS_API_KEY in every subscription this customer owns.')
                    ->password()
                    ->revealable()
                    ->nullable(),
                Section::make('Mail (SMTP)')
                    ->description('Written to the MAIL_* variables of every subscription this customer owns. Leave a field empty to keep the subscription type\'s template default.')
                    ->schema([
                        Select::make('mail_mailer')
                            ->label('Mailer')
                            ->options(self::mailerOptions())
                            ->helperText('MAIL_MAILER. Also used for MAIL_TRANSPORT unless overridden below.')
                            ->nullable(),
                        TextInput::make('mail_host')
                            ->label('Host')
                            ->placeholder('mail.blackwidow.org.za')
                            ->helperText('MAIL_HOST. Also used for MAIL_URL unless overridden below.')
                            ->nullable(),
                        TextInput::make('mail_port')
                            ->label('Port')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->placeholder('465')
                            ->nullable(),
                        TextInput::make('mail_username')
                            ->label('Username')
                            ->placeholder('demo@blackwidow.org.za')
                            ->nullable(),
                        TextInput::make('mail_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->nullable(),
                        TextInput::make('mail_from_address')
                            ->label('From address')
                            ->email()
                            ->placeholder('demo@blackwidow.org.za')
                            ->nullable(),
                        TextInput::make('mail_from_name')
                            ->label('From name')
                            ->placeholder('${APP_NAME}')
                            ->helperText('Leave empty to use the subscription\'s app name.')
                            ->nullable(),
                        TextInput::make('mail_ehlo_domain')
                            ->label('EHLO domain')
                            ->placeholder('blackwidow.org.za')
                            ->nullable(),
                        TextInput::make('mail_encryption')
                            ->label('Encryption')
                            ->placeholder('null')
                            ->helperText('MAIL_ENCRYPTION, e.g. tls, ssl, or null.')
                            ->nullable(),
                        TextInput::make('mail_scheme')
                            ->label('Scheme')
                            ->placeholder('null')
                            ->helperText('MAIL_SCHEME, Laravel 11\'s replacement for encryption, e.g. smtps.')
                            ->nullable(),
                        Select::make('mail_transport')
                            ->label('Transport override')
                            ->options(self::mailerOptions())
                            ->helperText('MAIL_TRANSPORT. Defaults to the mailer above.')
                            ->nullable(),
                        TextInput::make('mail_url')
                            ->label('URL override')
                            ->helperText('MAIL_URL. Defaults to the host above.')
                            ->nullable(),
                    ])
                    ->collapsible(),
                Section::make('S3 / MinIO storage')
                    ->description('S3-compatible object storage (e.g. MinIO). Use the same values as Laravel\'s s3 disk: endpoint, access key, secret, region, bucket, and path-style endpoint for MinIO.')
                    ->schema([
                        TextInput::make('s3_endpoint')
                            ->label('Endpoint URL')
                            ->url()
                            ->placeholder('http://127.0.0.1:9005')
                            ->nullable(),
                        TextInput::make('s3_key')
                            ->label('Access key')
                            ->nullable(),
                        TextInput::make('s3_secret')
                            ->label('Secret key')
                            ->password()
                            ->revealable()
                            ->nullable(),
                        TextInput::make('s3_region')
                            ->label('Region')
                            ->placeholder('us-east-1')
                            ->nullable(),
                        TextInput::make('s3_bucket')
                            ->label('Bucket')
                            ->nullable(),
                        Toggle::make('s3_use_path_style_endpoint')
                            ->label('Path-style endpoint')
                            ->helperText('Required for most MinIO setups (see Laravel filesystems s3 disk).')
                            ->default(true),
                    ])
                    ->collapsible(),
                TextInput::make('token'),
                TextInput::make('docket_description')
                    ->required()
                    ->default('Docket'),
                TextInput::make('task_description')
                    ->required()
                    ->default('Task'),
                TextInput::make('level_one_description')
                    ->required()
                    ->default('Level 1'),
                TextInput::make('level_two_description')
                    ->required()
                    ->default('Level 2'),
                TextInput::make('level_three_description')
                    ->required()
                    ->default('Level 3'),
                TextInput::make('level_four_description')
                    ->required()
                    ->default('Level 4'),
                TextInput::make('level_five_description')
                    ->required()
                    ->default('Level 5'),
                Toggle::make('level_one_in_use')
                    ->required(),
                Toggle::make('level_two_in_use')
                    ->required(),
                Toggle::make('level_three_in_use')
                    ->required(),
                TextInput::make('max_users')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('uuid')
                    ->label('UUID'),
            ]);
    }
}
