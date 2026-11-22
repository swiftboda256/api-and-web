<x-filament-panels::page>
    @php
        $rider = $this->rider;
        $profile = $rider->riderProfile;
        $vehicle = $this->reviewedVehicle();
        $canReview = $this->canReviewKyc();
        $canDelete = $this->canDeleteKycDocuments();
        $queue = $this->queueNavigation;
        $riderProfileUrl = $this->riderProfileUrl();
        $label = 'text-xs text-gray-500 dark:text-gray-400';
        $value = 'mt-0.5 text-sm text-gray-950 dark:text-white';
    @endphp

    <div class="space-y-6">
        {{-- Top bar --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a
                href="{{ \App\Filament\Pages\Kyc\KycReviews::getUrl() }}"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                Back to KYC reviews
            </a>

            <div class="flex flex-wrap items-center gap-2">
                @if ($queue['position'])
                    <span class="text-sm tabular-nums text-gray-500 dark:text-gray-400">
                        {{ $queue['position'] }} of {{ $queue['total'] }} in queue
                    </span>
                @elseif ($queue['total'] > 0)
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $queue['total'] }} still waiting
                    </span>
                @endif

                @if ($queue['previous'])
                    <x-filament::button tag="a" :href="$queue['previous']" icon="heroicon-m-arrow-left" color="gray">
                        Previous
                    </x-filament::button>
                @endif

                @if ($queue['next'])
                    <x-filament::button tag="a" :href="$queue['next']" icon="heroicon-m-arrow-right" icon-position="after" color="gray">
                        Next in queue
                    </x-filament::button>
                @elseif ($queue['total'] === 0 || ($queue['position'] && $queue['total'] === 1))
                    <span class="inline-flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400">
                        <x-heroicon-o-check-circle class="h-4 w-4" />
                        No one else is waiting
                    </span>
                @else
                    <span class="text-sm text-gray-500 dark:text-gray-400">End of queue</span>
                @endif
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Who's applying --}}
            <aside class="space-y-6 lg:col-span-1">
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900 lg:sticky lg:top-20">
                    <div class="flex items-start gap-4">
                        @include('filament.pages.riders.partials.avatar', ['user' => $rider, 'size' => 'lg'])

                        <div class="min-w-0">
                            <h2 class="truncate text-lg font-semibold text-gray-950 dark:text-white">
                                {{ $rider->name !== '' ? $rider->name : 'Unnamed rider' }}
                            </h2>
                            <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $profile->rider_ref }}</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @include('filament.pages.riders.partials.status-badge', ['status' => $profile->kyc_status])
                                @include('filament.pages.riders.partials.status-badge', ['status' => $rider->status])
                            </div>
                        </div>
                    </div>

                    @if ($profile->kyc_status === 'rejected' && $profile->kyc_rejection_reason)
                        <div class="mt-4 rounded-lg bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
                            <span class="font-medium">KYC rejected:</span> {{ $profile->kyc_rejection_reason }}
                        </div>
                    @elseif ($profile->kyc_status === 'approved' && $profile->approved_at)
                        <div class="mt-4 rounded-lg bg-emerald-50 p-3 text-xs text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
                            Approved {{ $profile->approved_at->format('d M Y, H:i') }}{{ $profile->approvedBy ? ' by '.$profile->approvedBy->name : '' }}
                        </div>
                    @endif

                    <dl class="mt-6 grid grid-cols-2 gap-x-4 gap-y-4 border-t border-gray-100 pt-6 dark:border-white/10">
                        <div class="col-span-2">
                            <dt class="{{ $label }}">Phone</dt>
                            <dd class="{{ $value }}">{{ $rider->phone ?? '—' }}</dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="{{ $label }}">Email</dt>
                            <dd class="{{ $value }} break-all">{{ $rider->email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $label }}">Gender</dt>
                            <dd class="{{ $value }}">{{ $profile->gender ? str($profile->gender)->headline() : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $label }}">Date of birth</dt>
                            <dd class="{{ $value }}">{{ $profile->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $label }}">Home zone</dt>
                            <dd class="{{ $value }}">{{ $profile->homeZone?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $label }}">Joined</dt>
                            <dd class="{{ $value }}">{{ $rider->created_at?->format('d M Y') ?? '—' }}</dd>
                        </div>
                    </dl>

                    @if ($riderProfileUrl)
                        <div class="mt-6 flex flex-col gap-2 border-t border-gray-100 pt-6 dark:border-white/10">
                            <x-filament::button tag="a" :href="$riderProfileUrl" icon="heroicon-o-truck" color="gray" outlined>
                                Open rider profile
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            </aside>

            {{-- Documents + vehicle --}}
            <div class="space-y-6 lg:col-span-2">
                @if (! $canReview)
                    <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                        You can view this review, but approving and rejecting needs the <span class="font-medium">Review KYC</span> permission.
                    </div>
                @endif

                @foreach ($this->requiredDocuments() as $type => $document)
                    @php
                        $entered = $type === 'national_id'
                            ? [['National ID number', $profile->national_id_number ?? '—', false]]
                            : [
                                ['License number', $profile->license_number ?? '—', false],
                                ['License expiry', $profile->license_expiry_at?->format('d M Y') ?? '—', $profile->license_expiry_at?->isPast() ?? false],
                            ];
                    @endphp

                    <section wire:key="kyc-document-{{ $type }}" class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-white/10">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ \App\Filament\Pages\Kyc\KycReview::DOCUMENT_LABELS[$type] ?? str($type)->headline() }}</h3>
                                @include('filament.pages.riders.partials.status-badge', ['status' => $document->status ?? 'missing'])
                            </div>

                            @if ($document)
                                <div class="flex items-center gap-2">
                                    @if ($canReview && $document->status !== 'approved')
                                        <x-filament::button size="sm" color="success" icon="heroicon-m-check" wire:click="approveDocument({{ $document->id }})">
                                            Approve
                                        </x-filament::button>
                                    @endif
                                    @if ($canReview && $document->status !== 'rejected')
                                        <x-filament::button size="sm" color="danger" outlined icon="heroicon-m-x-mark" wire:click="rejectDocument({{ $document->id }})">
                                            Reject
                                        </x-filament::button>
                                    @endif
                                    @if ($canDelete)
                                        <x-filament::icon-button
                                            icon="heroicon-o-trash"
                                            color="gray"
                                            label="Delete document"
                                            tooltip="Delete document"
                                            wire:click="mountAction('deleteDocument', { documentId: {{ $document->id }} })"
                                        />
                                    @endif
                                </div>
                            @endif
                        </header>

                        @if (! $document)
                            <div class="flex flex-col items-center justify-center gap-2 px-5 py-12 text-center">
                                <x-heroicon-o-document-arrow-up class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                                <p class="text-sm text-gray-500 dark:text-gray-400">The rider hasn't uploaded this document yet.</p>
                            </div>
                        @else
                            <div class="grid gap-5 p-5 md:grid-cols-5">
                                <div class="md:col-span-3">
                                    @if ($this->isPdf($document->file_path))
                                        <iframe src="{{ $this->documentUrl($document->file_path) }}" class="h-80 w-full rounded-lg bg-white ring-1 ring-gray-200 dark:ring-white/10"></iframe>
                                    @else
                                        @include('filament.partials.image-viewer', [
                                            'src' => $this->documentUrl($document->file_path),
                                            'alt' => \App\Filament\Pages\Kyc\KycReview::DOCUMENT_LABELS[$type] ?? $type,
                                        ])
                                    @endif
                                </div>

                                <div class="space-y-4 md:col-span-2">
                                    <div class="rounded-lg bg-amber-50 p-3 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:ring-amber-400/20">
                                        <p class="text-xs font-medium text-amber-800 dark:text-amber-300">Check if documents match</p>
                                        <dl class="mt-2 space-y-2">
                                            @foreach ($entered as [$fieldLabel, $fieldValue, $flag])
                                                <div>
                                                    <dt class="{{ $label }}">{{ $fieldLabel }}</dt>
                                                    <dd class="mt-0.5 font-mono text-sm {{ $flag ? 'text-rose-600 dark:text-rose-400' : 'text-gray-950 dark:text-white' }}">
                                                        {{ $fieldValue }}{{ $flag ? ' · expired' : '' }}
                                                    </dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </div>

                                    <dl class="space-y-2">
                                        <div>
                                            <dt class="{{ $label }}">Submitted</dt>
                                            <dd class="{{ $value }}">{{ $document->updated_at?->format('d M Y, H:i') }} <span class="text-gray-500 dark:text-gray-400">({{ $document->updated_at?->diffForHumans() }})</span></dd>
                                        </div>
                                        @if ($document->reviewed_at && $document->status !== 'pending')
                                            <div>
                                                <dt class="{{ $label }}">Reviewed</dt>
                                                <dd class="{{ $value }}">{{ $document->reviewed_at->format('d M Y, H:i') }} by {{ $document->reviewedBy?->name ?: 'an admin' }}</dd>
                                            </div>
                                        @endif
                                        @if ($document->status === 'rejected' && $document->rejection_reason)
                                            <div>
                                                <dt class="{{ $label }}">Rejection reason</dt>
                                                <dd class="mt-0.5 text-sm text-rose-600 dark:text-rose-400">{{ $document->rejection_reason }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>
                            </div>
                        @endif
                    </section>
                @endforeach

                {{-- Vehicle --}}
                <section class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-white/10">
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Vehicle</h3>
                            @include('filament.pages.riders.partials.status-badge', ['status' => $vehicle->status ?? 'missing'])
                        </div>

                        @if ($vehicle && $canReview)
                            <div class="flex items-center gap-2">
                                @if ($vehicle->status !== 'approved')
                                    <x-filament::button size="sm" color="success" icon="heroicon-m-check" wire:click="approveVehicle">
                                        Approve
                                    </x-filament::button>
                                @endif
                                @if ($vehicle->status !== 'rejected')
                                    <x-filament::button size="sm" color="danger" outlined icon="heroicon-m-x-mark" wire:click="rejectVehicle">
                                        Reject
                                    </x-filament::button>
                                @endif
                                <x-filament::icon-button icon="heroicon-o-pencil-square" color="gray" label="Edit vehicle" tooltip="Edit vehicle" wire:click="editVehicle" />
                            </div>
                        @endif
                    </header>

                    @if (! $vehicle)
                        <div class="flex flex-col items-center justify-center gap-2 px-5 py-12 text-center">
                            <x-heroicon-o-truck class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                            <p class="text-sm text-gray-500 dark:text-gray-400">The rider hasn't added a vehicle yet.</p>
                        </div>
                    @else
                        <div class="space-y-5 p-5">
                            @if ($vehicle->status === 'rejected' && $vehicle->rejection_reason)
                                <div class="rounded-lg bg-rose-50 p-3 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
                                    <span class="font-medium">Rejected:</span> {{ $vehicle->rejection_reason }}
                                </div>
                            @endif

                            <dl class="grid grid-cols-2 gap-x-4 gap-y-4 sm:grid-cols-3">
                                <div>
                                    <dt class="{{ $label }}">Plate number</dt>
                                    <dd class="mt-0.5 font-mono text-sm text-gray-950 dark:text-white">{{ $vehicle->plate_number }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $label }}">Type</dt>
                                    <dd class="{{ $value }}">{{ $vehicle->vehicleType?->name ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $label }}">Make & model</dt>
                                    <dd class="{{ $value }}">{{ trim(($vehicle->vehicleModel?->make ?? '').' '.($vehicle->vehicleModel?->name ?? '')) ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $label }}">Colour</dt>
                                    <dd class="{{ $value }}">{{ $vehicle->color ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $label }}">Year</dt>
                                    <dd class="{{ $value }}">{{ $vehicle->year ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $label }}">Registration no.</dt>
                                    <dd class="{{ $value }}">{{ $vehicle->registration_number ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $label }}">Insurance expiry</dt>
                                    @php($insuranceExpired = $vehicle->insurance_expiry_at?->isPast() ?? false)
                                    <dd class="mt-0.5 text-sm {{ $insuranceExpired ? 'text-rose-600 dark:text-rose-400' : 'text-gray-950 dark:text-white' }}">
                                        {{ $vehicle->insurance_expiry_at?->format('d M Y') ?? '—' }}{{ $insuranceExpired ? ' · expired' : '' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="{{ $label }}">Last updated</dt>
                                    <dd class="{{ $value }}">{{ $vehicle->updated_at?->diffForHumans() }}</dd>
                                </div>
                            </dl>

                            <div>
                                <h4 class="text-xs font-medium text-gray-500 dark:text-gray-400">Photos</h4>
                                @if ($vehicle->images->isEmpty())
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No photos uploaded.</p>
                                @else
                                    <div class="mt-2 grid gap-4 sm:grid-cols-2">
                                        @foreach ($vehicle->images as $image)
                                            <div wire:key="kyc-vehicle-image-{{ $image->id }}">
                                                @include('filament.partials.image-viewer', [
                                                    'src' => $this->documentUrl($image->file_path),
                                                    'alt' => 'Vehicle photo',
                                                    'heightClass' => 'h-56',
                                                ])
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>

    @include('filament.pages.riders.partials.reject-modal')
    @include('filament.pages.riders.partials.edit-vehicle-modal')
</x-filament-panels::page>
