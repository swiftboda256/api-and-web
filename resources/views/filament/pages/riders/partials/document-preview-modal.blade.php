@php
    $document = $this->previewingDocument();
@endphp

@if ($document)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-950/70" wire:click="closePreview"></div>

        <div class="relative flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl bg-white shadow-xl dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <h3 class="text-sm font-medium text-gray-950 dark:text-white">
                    {{ str($document->document_type)->headline() }}
                </h3>
                <button type="button" wire:click="closePreview" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <div class="flex-1 overflow-auto bg-gray-100 p-4 dark:bg-gray-950">
                @php($extension = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)))

                @if ($extension === 'pdf')
                    <iframe src="{{ $this->documentUrl($document->file_path) }}" class="h-[70vh] w-full rounded-lg bg-white"></iframe>
                @else
                    <img src="{{ $this->documentUrl($document->file_path) }}" alt="{{ str($document->document_type)->headline() }}" class="mx-auto max-h-[70vh] rounded-lg" />
                @endif
            </div>
        </div>
    </div>
@endif
