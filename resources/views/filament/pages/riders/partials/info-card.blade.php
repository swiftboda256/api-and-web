@php
    $profile = $rider->riderProfile;
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex items-start gap-4">
            @include('filament.pages.riders.partials.avatar', ['user' => $rider, 'size' => 'lg'])

            <div>
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $rider->name !== '' ? $rider->name : 'Unnamed rider' }}
                </h2>
                <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $profile->rider_ref }}</p>

                <div class="mt-2 flex flex-wrap gap-1.5">
                    @include('filament.pages.riders.partials.status-badge', ['status' => $profile->kyc_status])
                    @include('filament.pages.riders.partials.status-badge', ['status' => $profile->availability_status])
                    @include('filament.pages.riders.partials.status-badge', ['status' => $rider->status])
                </div>
            </div>
        </div>

        <div class="flex gap-6 text-center">
            <div>
                <p class="text-xl font-semibold text-gray-950 dark:text-white">⭐ {{ number_format((float) $rider->rating_avg, 2) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $rider->rating_count }} ratings</p>
            </div>
            <div>
                <p class="text-xl font-semibold text-gray-950 dark:text-white">{{ number_format($profile->total_trips) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Total trips</p>
            </div>
            <div>
                <p class="text-xl font-semibold text-gray-950 dark:text-white">UGX {{ number_format((float) $profile->total_earnings, 0) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Total earnings</p>
            </div>
        </div>
    </div>

    <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-gray-100 pt-6 text-sm sm:grid-cols-3 lg:grid-cols-4 dark:border-white/10">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $rider->phone }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Email</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $rider->email ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Gender</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $profile->gender ? str($profile->gender)->headline() : '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Date of birth</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $profile->date_of_birth?->format('d M Y') ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">National ID number</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $profile->national_id_number ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">License number</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $profile->license_number ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">License expiry</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $profile->license_expiry_at?->format('d M Y') ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Home zone</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $profile->homeZone?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Vehicle</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">
                {{ $profile->vehicle ? "{$profile->vehicle->vehicleType?->name} · {$profile->vehicle->plate_number}" : '—' }}
            </dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Login method</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ str($rider->login_type)->upper() }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Allow login</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $rider->allow_login ? 'Yes' : 'No' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Joined</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $rider->created_at?->format('d M Y') ?? '—' }}</dd>
        </div>
    </dl>
</div>
