<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\Customer;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('company_name'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Customer $record): bool => $record->trashed()),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('google_api_key')
                    ->label('Google API key')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? '********' : '—'),
                TextEntry::make('s3_endpoint')
                    ->label('S3 / MinIO endpoint')
                    ->placeholder('—'),
                TextEntry::make('s3_key')
                    ->label('S3 access key')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? '********' : '—'),
                TextEntry::make('s3_secret')
                    ->label('S3 secret key')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? '********' : '—'),
                TextEntry::make('s3_region')
                    ->label('S3 region')
                    ->placeholder('—'),
                TextEntry::make('s3_bucket')
                    ->label('S3 bucket')
                    ->placeholder('—'),
                IconEntry::make('s3_use_path_style_endpoint')
                    ->label('S3 path-style endpoint')
                    ->boolean(),
                TextEntry::make('token')
                    ->placeholder('-'),
                TextEntry::make('docket_description'),
                TextEntry::make('task_description'),
                TextEntry::make('level_one_description'),
                TextEntry::make('level_two_description'),
                TextEntry::make('level_three_description'),
                TextEntry::make('level_four_description'),
                TextEntry::make('level_five_description'),
                IconEntry::make('level_one_in_use')
                    ->boolean(),
                IconEntry::make('level_two_in_use')
                    ->boolean(),
                IconEntry::make('level_three_in_use')
                    ->boolean(),
                TextEntry::make('max_users')
                    ->numeric(),
                TextEntry::make('uuid')
                    ->label('UUID')
                    ->placeholder('-'),
            ]);
    }
}
