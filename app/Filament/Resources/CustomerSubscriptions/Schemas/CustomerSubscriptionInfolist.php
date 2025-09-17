<?php

namespace App\Filament\Resources\CustomerSubscriptions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CustomerSubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('url'),
                TextEntry::make('subscriptionType.name')
                    ->label('Subscription type')
                    ->placeholder('-'),
                TextEntry::make('logo_1')
                    ->placeholder('-'),
                TextEntry::make('logo_2')
                    ->placeholder('-'),
                TextEntry::make('logo_3')
                    ->placeholder('-'),
                TextEntry::make('logo_4')
                    ->placeholder('-'),
                TextEntry::make('logo_5')
                    ->placeholder('-'),
                TextEntry::make('customer.id')
                    ->label('Customer')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('forge_site_id')
                    ->placeholder('-'),
                TextEntry::make('env')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('server_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('app_name')
                    ->placeholder('-'),
                TextEntry::make('database_name'),
                TextEntry::make('site_created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('github_sent_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('env_sent_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deployment_script_sent_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('ssl_deployed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deployed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('domain')
                    ->placeholder('-'),
                IconEntry::make('panic_button_enabled')
                    ->boolean(),
            ]);
    }
}
