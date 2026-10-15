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
                    <span class="absolute -left-7.25 top-1 h-3 w-3 rounded-full border-2 border-white bg-gray-300 dark:border-gray-900 dark:bg-gray-600"></span>

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
                            @if ($document->status !== 'approved')
                                <button
                                    type="button"
                                    wire:click="approveDocument({{ $document->id }})"
                                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500"
                                >
                                    Approve
                                </button>
                            @endif

                            <div
                                x-data="{
                                    open: false,
                                    top: 0,
                                    left: 0,
                                    toggle() {
                                        this.open = ! this.open;

                                        if (this.open) {
                                            this.$nextTick(() => {
                                                const buttonRect = $refs.actionsButton.getBoundingClientRect();
                                                const panelHeight = $refs.panel.offsetHeight;
                                                const fitsBelow = (window.innerHeight - buttonRect.bottom) >= (panelHeight + 8);

                                                this.top = fitsBelow
                                                    ? buttonRect.bottom + window.scrollY + 4
                                                    : buttonRect.top + window.scrollY - panelHeight - 4;
                                                this.left = buttonRect.right + window.scrollX - 160;
                                            });
                                        }
                                    },
                                }"
                                class="relative"
                            >
                                <button
                                    x-ref="actionsButton"
                                    type="button"
                                    @click="toggle()"
                                    class="inline-flex items-center justify-center rounded-lg p-1.5 text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-white/5"
                                >
                                    <span class="sr-only">Document actions</span>
                                    <x-heroicon-o-ellipsis-vertical class="h-4 w-4" style="width: 1.1rem; height: 1.1rem;" />
                                </button>

                                <template x-teleport="body">
                                    <div
                                        x-ref="panel"
                                        x-show="open"
                                        x-cloak
                                        x-transition
                                        @click.outside="open = false"
                                        :style="`top: ${top}px; left: ${left}px;`"
                                        class="fixed z-50 w-40 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-gray-800"
                                    >
                                        <button
                                            type="button"
                                            wire:click="previewDocument({{ $document->id }})"
                                            @click="open = false"
                                            class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                                        >
                                            Preview
                                        </button>

                                        @if ($document->status !== 'rejected')
                                            <button
                                                type="button"
                                                wire:click="rejectDocument({{ $document->id }})"
                                                @click="open = false"
                                                class="block w-full px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                            >
                                                Reject
                                            </button>
                                        @endif

                                        <div class="my-1 border-t border-gray-100 dark:border-white/10"></div>

                                        <button
                                            type="button"
                                            wire:click="mountAction('deleteDocument', { documentId: {{ $document->id }} })"
                                            @click="open = false"
                                            class="block w-full px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
