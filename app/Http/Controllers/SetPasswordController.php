<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\SetPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SetPasswordController extends Controller
{
    public function show(User $user): View|RedirectResponse
    {
        if (filled($user->password)) {
            return $this->alreadyUsed();
        }

        return view('auth.set-password', ['user' => $user]);
    }

    public function store(SetPasswordRequest $request, User $user): RedirectResponse
    {
        if (filled($user->password)) {
            return $this->alreadyUsed();
        }

        $user->forceFill([
            'password' => $request->validated('password'),
            'allow_login' => true,
        ])->save();

        return redirect()->route('filament.admin.auth.login')
            ->with('status', 'Your password has been set. You can now log in.');
    }

    private function alreadyUsed(): RedirectResponse
    {
        return redirect()->route('filament.admin.auth.login')
            ->with('status', 'This link has already been used. Please log in.');
    }
}
