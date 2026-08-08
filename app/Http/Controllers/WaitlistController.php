<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\JoinWaitlistRequest;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WaitlistController extends Controller
{
    public function index(): View
    {
        return view('waitlist.coming-soon');
    }

    public function store(JoinWaitlistRequest $request): RedirectResponse
    {
        WaitlistEntry::query()->create($request->validated());

        return back()->with('waitlist_joined', true);
    }
}
