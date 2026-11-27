<?php

namespace App\Filament\Resources\Communications\Pages;

use App\Filament\Resources\Communications\CommunicationResource;
use App\Models\User;
use App\Services\Communication\CommunicationService;
use App\Services\Communication\SmsCostNotAcknowledged;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class CreateCommunication extends CreateRecord
{
    protected static string $resource = CommunicationResource::class;

    protected static ?string $title = 'New communication';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var array{title: string, body: string, channels: list<string>, target_roles?: list<string>|null, target_user_ids?: list<int|string>|null, sms_cost_acknowledged_amount?: int|string|null} $data */
        try {
            /** @var User $sender */
            $sender = auth()->user();

            return app(CommunicationService::class)->send($data, $sender);
        } catch (SmsCostNotAcknowledged $e) {
            // The audience, numbers or text changed after the box was ticked: confirm again.
            $this->data['confirm_sms_cost'] = false;
            $this->data['sms_cost_acknowledged_amount'] = null;

            throw ValidationException::withMessages([
                'data.confirm_sms_cost' => 'The SMS cost is now UGX '.Number::format($e->currentCost).'. Confirm the new amount before sending.',
            ]);
        }
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Send');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Communication queued for sending';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
