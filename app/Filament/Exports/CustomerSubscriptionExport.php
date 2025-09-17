<?php

namespace App\Filament\Exports;

use App\Models\CustomerSubscription;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CustomerSubscriptionExport extends Exporter
{
    protected static ?string $model = CustomerSubscription::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('url')
                ->label('Website'),
            ExportColumn::make('domain'),
            ExportColumn::make('app_name')
                ->label('App Name'),
            ExportColumn::make('customer.company_name')
                ->label('Customer'),
            ExportColumn::make('subscriptionType.name'),
            ExportColumn::make('deployed_at')
                ->label('Deployed Date'),
            ExportColumn::make('deployed_version')
                ->label('Deployed Version'),
            ExportColumn::make('subscriptionType.master_version')
                ->label('Newest Version'),
            ExportColumn::make('panic_button_enabled')
                ->label('Panic Button'),
            ExportColumn::make('forge_site_id'),
            ExportColumn::make('env_variables_count')
                ->label('Variable Count'),
            ExportColumn::make('null_variable_count')
                ->label('Null Count'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your customer subscription export has been completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
