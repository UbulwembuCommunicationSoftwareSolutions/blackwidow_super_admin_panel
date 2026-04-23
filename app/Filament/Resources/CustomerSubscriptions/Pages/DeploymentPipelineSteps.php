<?php

namespace App\Filament\Resources\CustomerSubscriptions\Pages;

use App\Filament\Resources\CustomerSubscriptions\CustomerSubscriptionResource;
use App\Services\SiteDeploymentJobName;
use App\Services\SiteDeploymentScheduler;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class DeploymentPipelineSteps extends Page
{
    use InteractsWithRecord;

    protected static string $resource = CustomerSubscriptionResource::class;

    protected static bool $shouldRegisterNavigation = false;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function getTitle(): string
    {
        return 'Deployment pipeline steps';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Steps from app:complete-creation')
                    ->description('Each action queues that job alone in a new batch (same job classes as the full `app:complete-creation` pipeline). Check the Site deployment jobs tab on the subscription edit page for status.')
                    ->schema($this->resolvePipelineActions()),
            ]);
    }

    /**
     * @return list<Action>
     */
    protected function resolvePipelineActions(): array
    {
        $template = app(SiteDeploymentScheduler::class)->getCompleteCreationPipelineTemplate($this->getRecord());
        $actions = [];
        foreach ($template as $index => $item) {
            [$jobName, $params] = $item;
            $label = $this->labelForPipelineStep($jobName, $params, $index);
            $actions[] = Action::make('queue_pipeline_step_'.$index)
                ->label($label)
                ->icon(Heroicon::OutlinedPlay)
                ->requiresConfirmation()
                ->modalHeading('Queue this step?')
                ->modalDescription('Queues a new batch with only: '.Str::limit($label, 120))
                ->action(function () use ($index) {
                    $this->queueStep($index);
                });
        }

        return $actions;
    }

    public function queueStep(int $index): void
    {
        try {
            $batchId = app(SiteDeploymentScheduler::class)->queueSingleTemplateStep($this->getRecord(), $index);
            Notification::make()
                ->title('Step queued')
                ->body('Batch: '.$batchId)
                ->success()
                ->send();
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title('Invalid step')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not queue step')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function labelForPipelineStep(string $jobName, array $params, int $index): string
    {
        $prefix = $index.'. ';

        if ($jobName === SiteDeploymentJobName::SEND_FORGE_COMMAND && isset($params['command'])) {
            return $prefix.'Forge command: '.$params['command'];
        }

        if ($jobName === SiteDeploymentJobName::SEND_SYSTEM_CONFIG) {
            return $prefix.'Send system config';
        }

        return $prefix.Str::headline($jobName);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToEdit')
                ->label('Back to edit')
                ->url(fn (): string => CustomerSubscriptionResource::getUrl('edit', ['record' => $this->getRecord()]))
                ->icon(Heroicon::OutlinedArrowLeft),
        ];
    }
}
