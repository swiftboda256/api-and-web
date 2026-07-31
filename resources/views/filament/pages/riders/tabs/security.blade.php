@php
    $devices = $this->devices;
    $tokens = $this->tokens;
@endphp

<div class="space-y-8">
    <div>
        <h3 class="text-sm font-medium text-gray-950 dark:text-white">Two-factor authentication</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
            {{ $rider->two_factor_confirmed_at ? 'Enabled since ' . $rider->two_factor_confirmed_at->format('d M Y') : 'Not enabled' }}
        </p>
    </div>

    <div class="border-t border-gray-100 pt-8 dark:border-white/10">
        <h3 class="text-sm font-medium text-gray-950 dark:text-white">Connected devices</h3>

        @if ($devices->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No devices registered.</p>
        @else
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($devices as $device)
                    <li wire:key="device-{{ $device->id }}" class="flex items-center justify-between gap-3 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-950 dark:text-white">
                                {{ str($device->device_type)->headline() }}
                                @if ($device->active)
                                    <span class="ml-1 text-xs font-normal text-emerald-600 dark:text-emerald-400">active</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                App v{{ $device->app_version ?? '—' }} · Last seen {{ $device->last_seen_at?->diffForHumans() ?? 'never' }}
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click="revokeDevice({{ $device->id }})"
                            wire:confirm="Revoke this device? It will stop receiving push notifications."
                            class="shrink-0 rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-500/20 dark:text-rose-400 dark:hover:bg-rose-500/10"
                        >
                            Revoke
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="border-t border-gray-100 pt-8 dark:border-white/10">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-gray-950 dark:text-white">Active sessions (API tokens)</h3>

            @if ($tokens->isNotEmpty())
                <button
                    type="button"
                    wire:click="forceLogout"
                    wire:confirm="Force logout on every device? All of this rider's active sessions will be revoked immediately."
                    class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-rose-500"
                >
                    Force logout everywhere
                </button>
            @endif
        </div>

        @if ($tokens->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No active sessions.</p>
        @else
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($tokens as $token)
                    <li wire:key="token-{{ $token->id }}" class="flex items-center justify-between gap-3 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $token->name }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Last used {{ $token->last_used_at?->diffForHumans() ?? 'never' }} · Issued {{ $token->created_at?->diffForHumans() }}
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click="revokeToken({{ $token->id }})"
                            wire:confirm="Revoke this session?"
                            class="shrink-0 rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:border-rose-500/20 dark:text-rose-400 dark:hover:bg-rose-500/10"
                        >
                            Revoke
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
