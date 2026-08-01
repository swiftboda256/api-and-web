<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\UserCredentialsNotification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private bool $shouldNotify = false;

    private string $generatedPassword = '';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['login_type'] = 'password';
        $data['allow_login'] = true;

        $this->shouldNotify = (bool) ($data['notify'] ?? false);
        unset($data['notify']);

        $this->generatedPassword = Str::password(12);
        $data['password'] = $this->generatedPassword;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;

        if (! $this->shouldNotify || blank($user->email)) {
            return;
        }

        $user->notify(new UserCredentialsNotification($this->generatedPassword));
    }
}
