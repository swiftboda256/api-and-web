<div class="space-y-8">
    <div>
        <h3 class="text-sm font-medium text-gray-950 dark:text-white">Account status</h3>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            @include('filament.pages.riders.partials.status-badge', ['status' => $rider->status])

            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    @click="open = ! open"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                >
                    Actions
                    <x-heroicon-o-chevron-down class="h-3.5 w-3.5" />
                </button>

                <div
                    x-show="open"
                    x-cloak
                    x-transition
                    @click.outside="open = false"
                    class="absolute left-0 z-10 mt-2 w-56 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-gray-800"
                >
                    @if ($rider->status === 'active')
                        <button
                            type="button"
                            wire:click="suspendAccount"
                            wire:confirm="Suspend this account? The rider will be unable to go online or accept trips."
                            @click="open = false"
                            class="block w-full px-3 py-2 text-left text-sm text-amber-700 hover:bg-amber-50 dark:text-amber-400 dark:hover:bg-amber-500/10"
                        >
                            Suspend
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="reactivateAccount"
                            wire:confirm="Reactivate this account?"
                            @click="open = false"
                            class="block w-full px-3 py-2 text-left text-sm text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10"
                        >
                            Reactivate
                        </button>
                    @endif

                    @if ($rider->status !== 'banned')
                        <button
                            type="button"
                            wire:click="banAccount"
                            wire:confirm="Ban this account? This is a hard block — the rider will no longer be able to log in at all. Use this only for confirmed policy violations."
                            @click="open = false"
                            class="block w-full px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                        >
                            Ban account
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div>
        <h3 class="text-sm font-medium text-gray-950 dark:text-white">Login access</h3>

        <div class="mt-3 flex items-center gap-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ $rider->allow_login ? 'This rider can currently log in.' : 'This rider is currently blocked from logging in.' }}
            </p>

            <button
                type="button"
                wire:click="toggleAllowLogin"
                wire:confirm="{{ $rider->allow_login ? 'Block this rider from logging in?' : 'Allow this rider to log in again?' }}"
                class="shrink-0 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
            >
                {{ $rider->allow_login ? 'Disable login' : 'Enable login' }}
            </button>
        </div>
    </div>

    <dl class="grid grid-cols-2 gap-x-6 gap-y-4 border-t border-gray-100 pt-6 text-sm sm:grid-cols-3 dark:border-white/10">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Referral code</dt>
            <dd class="mt-0.5 font-mono text-gray-950 dark:text-white">{{ $rider->referral_code ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Referred by</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $rider->referredBy?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Phone verified</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $rider->phone_verified_at?->format('d M Y') ?? 'Not verified' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Email verified</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $rider->email_verified_at?->format('d M Y') ?? 'Not verified' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Last login</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $rider->last_login_at?->diffForHumans() ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Profile completed</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $rider->profile_completed ? 'Yes' : 'No' }}</dd>
        </div>
    </dl>
</div>
