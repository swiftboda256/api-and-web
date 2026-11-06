<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\JoinWaitlistRequest;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WebController extends Controller
{
    public function index(): View
    {
        return view('home');
    }

    public function store(JoinWaitlistRequest $request): RedirectResponse
    {
        WaitlistEntry::query()->create($request->validated());

        return back()->with('waitlist_joined', true);
    }
}
