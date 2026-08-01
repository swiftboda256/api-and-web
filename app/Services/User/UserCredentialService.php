<?php

namespace App\Services\User;

use App\Models\User;
use App\Notifications\UserCredentialsNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

readonly class UserCredentialService
{
    /**
     * Generates a new password for the user, revokes every active session/token,
     * and emails them their new credentials. The password change is attributed
     * to the system account rather than whichever admin triggered it.
     */
    public function sendNewCredentials(User $user): void
    {
        if (blank($user->email)) {
            throw ValidationException::withMessages([
                'email' => 'This user has no email address to send credentials to.',
            ]);
        }

        $password = Str::password(12);

        $this->updateAsSystem($user, ['password' => $password]);

        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $user->notify(new UserCredentialsNotification($password));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateAsSystem(User $user, array $attributes): void
    {
        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            $user->update($attributes);
        } finally {
            if ($actingUser instanceof User) {
                Auth::setUser($actingUser);
            }
        }
    }
}
