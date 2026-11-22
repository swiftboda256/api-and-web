@if ($rejecting !== null)
    <div
        x-data
        x-init="$nextTick(() => $refs.reason?.focus())"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
    >
        <div class="fixed inset-0 bg-gray-950/50" wire:click="cancelReject"></div>

        <div class="relative w-full max-w-[33.6rem] rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                {{ $rejecting === 'vehicle' ? 'Reject vehicle' : 'Reject document' }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Please provide a reason. This will be visible to the rider.
            </p>

            <div class="mt-4 flex flex-wrap gap-1.5">
                @foreach ($this->rejectionReasonSuggestions() as $suggestion)
                    <button
                        type="button"
                        wire:click="$set('rejectionReason', @js($suggestion))"
                        class="rounded-full border border-gray-300 px-2.5 py-1 text-xs text-gray-700 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 dark:border-white/10 dark:text-gray-300 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                    >
                        {{ $suggestion }}
                    </button>
                @endforeach
            </div>

            <textarea
                x-ref="reason"
                wire:model="rejectionReason"
                rows="4"
                class="mt-3 block w-full rounded-lg border-gray-300 px-3.5 py-2.5 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-white/5 dark:text-white"
                placeholder="e.g. The uploaded ID number does not match the details submitted."
            ></textarea>
            @error('rejectionReason')
                <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="cancelReject"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    wire:click="confirmReject"
                    class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-500"
                >
                    Confirm rejection
                </button>
            </div>
        </div>
    </div>
@endif
