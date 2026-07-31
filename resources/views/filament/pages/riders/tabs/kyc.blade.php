@php
    $documents = $this->documents;
    $labels = ['national_id' => 'National ID', 'driving_license' => 'Driving License'];
@endphp

<div class="space-y-6">
    @if ($rider->riderProfile->kyc_status === 'rejected' && $rider->riderProfile->kyc_rejection_reason)
        <div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
            <span class="font-medium">KYC rejected:</span> {{ $rider->riderProfile->kyc_rejection_reason }}
        </div>
    @endif

    <div>
        <h3 class="text-sm font-medium text-gray-950 dark:text-white">Submitted documents</h3>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Compare the number on each document against the National ID / License number shown in the card above before approving.
        </p>
    </div>

    @if ($documents->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No documents have been submitted yet.</p>
    @else
        <ol class="relative space-y-6 border-l border-gray-200 pl-6 dark:border-white/10">
            @foreach ($documents as $document)
                <li wire:key="document-{{ $document->id }}" class="relative">
                    <span class="absolute -left-[29px] top-1 h-3 w-3 rounded-full border-2 border-white bg-gray-300 dark:border-gray-900 dark:bg-gray-600"></span>

                    <div class="flex flex-wrap items-start justify-between gap-3 rounded-lg border border-gray-200 p-4 dark:border-white/10">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $labels[$document->document_type] ?? str($document->document_type)->headline() }}
                                </p>
                                @include('filament.pages.riders.partials.status-badge', ['status' => $document->status])
                            </div>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Submitted {{ $document->created_at?->diffForHumans() }}
                            </p>

                            @if ($document->reviewed_at)
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Reviewed by {{ $document->reviewedBy?->name ?: 'an admin' }} {{ $document->reviewed_at->diffForHumans() }}
                                </p>
                            @endif

                            @if ($document->status === 'rejected' && $document->rejection_reason)
                                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                                    Reason: {{ $document->rejection_reason }}
                                </p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                type="button"
                                wire:click="previewDocument({{ $document->id }})"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                            >
                                Preview
                            </button>

                            @if ($document->status !== 'approved')
                                <button
                                    type="button"
                                    wire:click="approveDocument({{ $document->id }})"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500"
                                >
                                    Approve
                                </button>
                            @endif

                            @if ($document->status !== 'rejected')
                                <button
                                    type="button"
                                    wire:click="rejectDocument({{ $document->id }})"
                                    class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-rose-500"
                                >
                                    Reject
                                </button>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
