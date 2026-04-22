<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('company_name')
                    ->required(),
                TextInput::make('google_api_key')
                    ->label('Google API key')
                    ->password()
                    ->revealable()
                    ->nullable(),
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
