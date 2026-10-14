<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /**
     * Credentials are verified by the parent first so the suspension message is
     * only revealed to someone who knows the account's password.
     */
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        $user = Filament::auth()->user();

        if ($user instanceof User && $user->status === 'suspended') {
            Filament::auth()->logout();

            throw ValidationException::withMessages([
                'data.email' => 'Your account has been suspended. Please contact support.',
            ]);
        }

        return $response;
    }
}
