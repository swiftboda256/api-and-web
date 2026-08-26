<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Auth\SetPasswordLinkService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private bool $shouldNotify = false;

    private string $channel = 'email';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['login_type'] = 'password';

        $this->shouldNotify = (bool) ($data['notify'] ?? false);
        $this->channel = $data['channel'] ?? 'email';
        unset($data['notify'], $data['channel']);

        if ($this->shouldNotify) {
            $data['password'] = null;
            $data['allow_login'] = false;
        } else {
            $data['password'] = Str::password(12);
            $data['allow_login'] = true;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->shouldNotify) {
            return;
        }

        /** @var User $user */
        $user = $this->record;

        app(SetPasswordLinkService::class)->send($user, $this->channel);
    }
}
