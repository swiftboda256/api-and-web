<x-filament-panels::page>
    @php
        $ticket = $this->ticket;
        $requester = $ticket->user;
        $isRider = (bool) $requester?->riderProfile;
        $canRespond = $this->canRespond();
        $canAssign = $this->canAssign();
        $closed = $this->isClosedForReplies();
        $requesterUrl = $this->requesterUrl();
        $tripUrl = $this->tripUrl();
        $label = 'text-xs text-gray-500 dark:text-gray-400';
        $value = 'mt-0.5 text-sm text-gray-950 dark:text-white';
        $statusColors = [
            'open' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20',
            'in_progress' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-500/20',
            'resolved' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/20',
            'closed' => 'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20',
        ];
        $priorityColors = [
            'urgent' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-500/20',
            'high' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20',
            'medium' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-500/20',
            'low' => 'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20',
        ];
        $badge = 'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset';
    @endphp

    <div class="space-y-6">
        <a
            href="{{ \App\Filament\Pages\Support\SupportTickets::getUrl() }}"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            Back to support tickets
        </a>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Conversation --}}
            <div class="space-y-6 lg:col-span-2">
                <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="{{ $badge }} {{ $statusColors[$ticket->status] ?? $statusColors['closed'] }}">{{ str($ticket->status)->headline() }}</span>
                            <span class="{{ $badge }} {{ $priorityColors[$ticket->priority] ?? $priorityColors['low'] }}">{{ str($ticket->priority)->headline() }} priority</span>
                            @if ($ticket->category)
                                <span class="{{ $badge }} {{ $priorityColors['low'] }}">{{ $ticket->category->name }}</span>
                            @endif
                        </div>
                        <h2 class="mt-2 text-lg font-semibold text-gray-950 dark:text-white">{{ $ticket->subject }}</h2>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-mono">{{ $ticket->ticket_number }}</span>
                            · opened {{ $ticket->created_at?->format('d M Y, H:i') }} ({{ $ticket->created_at?->diffForHumans() }})
                            @if ($ticket->resolved_at)
                                · {{ $ticket->status === 'closed' ? 'closed' : 'resolved' }} {{ $ticket->resolved_at->format('d M Y, H:i') }}
                            @endif
                        </p>
                    </div>

                    <div
                        wire:poll.15s="refreshThread"
                        x-data
                        x-init="$el.scrollTop = $el.scrollHeight"
                        x-on:support-thread-updated.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                        class="max-h-[60vh] space-y-4 overflow-y-auto px-5 py-5"
                    >
                        @forelse ($this->messages as $chat)
                            @php
                                $fromRequester = $chat->sender_id === $ticket->user_id;
                                $images = $chat->attachments->where('type', 'image');
                                $files = $chat->attachments->where('type', '!=', 'image');
                            @endphp

                            <div wire:key="message-{{ $chat->id }}" @class([
                                'flex',
                                'justify-start' => $fromRequester,
                                'justify-end' => ! $fromRequester && ! $chat->is_internal_note,
                            ])>
                                <div @class([
                                    'rounded-xl px-4 py-3',
                                    'max-w-[85%] bg-gray-100 dark:bg-white/5' => $fromRequester,
                                    'max-w-[85%] bg-amber-50 ring-1 ring-amber-600/10 dark:bg-amber-500/10 dark:ring-amber-400/20' => ! $fromRequester && ! $chat->is_internal_note,
                                    'w-full border border-dashed border-yellow-400 bg-yellow-50 dark:border-yellow-500/40 dark:bg-yellow-500/5' => $chat->is_internal_note,
                                ])>
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                                        @if ($chat->is_internal_note)
                                            <span class="inline-flex items-center gap-1 font-semibold text-yellow-800 dark:text-yellow-300">
                                                <x-heroicon-m-lock-closed class="h-3.5 w-3.5" />
                                                Internal note
                                            </span>
                                        @endif
                                        <span class="font-medium text-gray-950 dark:text-white">
                                            {{ $chat->sender?->name ?: 'Unknown' }}
                                        </span>
                                        <span class="text-gray-500 dark:text-gray-400">
                                            {{ $fromRequester ? ($isRider ? 'Rider' : 'Customer') : 'Support' }}
                                            · {{ $chat->created_at?->format('d M, H:i') }}
                                        </span>
                                        @if ($chat->is_internal_note)
                                            <span class="text-yellow-700/80 dark:text-yellow-300/70">· only staff can see this</span>
                                        @endif
                                    </div>

                                    @if ($chat->message)
                                        <p class="mt-1.5 whitespace-pre-line text-sm text-gray-800 dark:text-gray-200">{{ $chat->message }}</p>
                                    @endif

                                    @if ($images->isNotEmpty())
                                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                            @foreach ($images as $attachment)
                                                <div wire:key="attachment-{{ $attachment->id }}">
                                                    @include('filament.partials.image-viewer', ['src' => $attachment->url, 'alt' => 'Attachment', 'heightClass' => 'h-44'])
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if ($files->isNotEmpty())
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @foreach ($files as $attachment)
                                                <a
                                                    wire:key="attachment-{{ $attachment->id }}"
                                                    href="{{ $attachment->url }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10"
                                                >
                                                    <x-heroicon-o-paper-clip class="h-3.5 w-3.5" />
                                                    {{ \Illuminate\Support\Str::limit(basename(parse_url($attachment->url, PHP_URL_PATH) ?: $attachment->url), 32) }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">No messages on this ticket.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Composer --}}
                @if ($canRespond)
                    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-2 dark:border-white/10">
                            <nav class="-mb-px flex gap-1">
                                @foreach (['reply' => 'Reply to '.($isRider ? 'rider' : 'customer'), 'note' => 'Internal note'] as $mode => $modeLabel)
                                    <button
                                        type="button"
                                        wire:click="setComposerMode('{{ $mode }}')"
                                        @class([
                                            'border-b-2 px-4 py-3 text-sm font-medium transition-colors',
                                            'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' => $composerMode === $mode,
                                            'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' => $composerMode !== $mode,
                                        ])
                                    >
                                        {{ $modeLabel }}
                                    </button>
                                @endforeach
                            </nav>

                            @if ($composerMode === 'reply' && ! $closed)
                                <div x-data="{ open: false }" class="relative pr-2">
                                    <button
                                        type="button"
                                        @click="open = ! open"
                                        class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5"
                                    >
                                        <x-heroicon-o-bookmark class="h-4 w-4" />
                                        Saved replies
                                        <x-heroicon-m-chevron-down class="h-3.5 w-3.5" />
                                    </button>
                                    <div
                                        x-show="open"
                                        x-cloak
                                        x-transition
                                        @click.outside="open = false"
                                        class="absolute right-2 z-20 mt-1 w-72 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-gray-800"
                                    >
                                        @foreach (\App\Filament\Pages\Support\SupportTicketDetails::SAVED_REPLIES as $name => $text)
                                            <button
                                                type="button"
                                                wire:click="useSavedReply(@js($name))"
                                                @click="open = false"
                                                class="block w-full px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-white/5"
                                            >
                                                <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $name }}</span>
                                                <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $text }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="space-y-3 p-4">
                            @if ($composerMode === 'reply' && $closed)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-gray-50 p-3 text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                                    This ticket is {{ $ticket->status }}, so neither side can reply. Reopen it to continue the conversation.
                                    <x-filament::button size="sm" color="gray" icon="heroicon-m-arrow-uturn-left" wire:click="setStatus('open')">
                                        Reopen ticket
                                    </x-filament::button>
                                </div>
                            @else
                                <textarea
                                    wire:model.live.debounce.400ms="message"
                                    rows="4"
                                    @class([
                                        'block w-full rounded-lg px-3.5 py-2.5 text-sm text-gray-950 shadow-sm focus:ring-primary-500 dark:text-white',
                                        'border-gray-300 focus:border-primary-500 dark:border-gray-600 dark:bg-white/5' => $composerMode === 'reply',
                                        'border-yellow-300 bg-yellow-50/50 focus:border-yellow-500 dark:border-yellow-500/40 dark:bg-yellow-500/5' => $composerMode === 'note',
                                    ])
                                    placeholder="{{ $composerMode === 'note' ? 'Add a note for the support team. The requester will not see this.' : 'Write your reply…' }}"
                                ></textarea>
                                @error('message')
                                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                                @enderror

                                @if (count($uploads))
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($uploads as $index => $upload)
                                            <span wire:key="upload-{{ $index }}" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-2.5 py-1 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                                <x-heroicon-o-paper-clip class="h-3.5 w-3.5" />
                                                {{ \Illuminate\Support\Str::limit($upload->getClientOriginalName(), 28) }}
                                                <button type="button" wire:click="removeUpload({{ $index }})" class="text-gray-400 hover:text-rose-600" title="Remove">
                                                    <x-heroicon-m-x-mark class="h-3.5 w-3.5" />
                                                </button>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                @error('uploads') <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                @error('uploads.*') <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <label class="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white">
                                        <x-heroicon-o-paper-clip class="h-4 w-4" />
                                        Attach files
                                        <input type="file" wire:model="uploads" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.txt" class="sr-only" />
                                        <span wire:loading wire:target="uploads" class="text-xs text-gray-400">Uploading…</span>
                                    </label>

                                    <div class="flex flex-wrap items-center gap-2">
                                        @if ($composerMode === 'note')
                                            <x-filament::button color="warning" icon="heroicon-m-lock-closed" wire:click="send" wire:loading.attr="disabled" wire:target="send,uploads">
                                                Add internal note
                                            </x-filament::button>
                                        @else
                                            <x-filament::button color="gray" icon="heroicon-m-check-circle" wire:click="send(true)" wire:loading.attr="disabled" wire:target="send,uploads">
                                                Reply & resolve
                                            </x-filament::button>
                                            <x-filament::button icon="heroicon-m-paper-airplane" wire:click="send" wire:loading.attr="disabled" wire:target="send,uploads">
                                                Send reply
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                        You can read this ticket, but replying and changing it needs the <span class="font-medium">Respond to support tickets</span> permission.
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <aside class="space-y-6">
                {{-- Ticket controls --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Ticket</h3>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="{{ $label }}" for="ticket-status">Status</label>
                            <x-filament::input.wrapper class="mt-1" :disabled="! $canRespond">
                                <x-filament::input.select id="ticket-status" wire:change="setStatus($event.target.value)" :disabled="! $canRespond">
                                    @foreach (\App\Services\SupportTicket\SupportAgentService::STATUSES as $status)
                                        <option value="{{ $status }}" @selected($ticket->status === $status)>{{ str($status)->headline() }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>

                        <div>
                            <label class="{{ $label }}" for="ticket-priority">Priority</label>
                            <x-filament::input.wrapper class="mt-1" :disabled="! $canRespond">
                                <x-filament::input.select id="ticket-priority" wire:change="setPriority($event.target.value)" :disabled="! $canRespond">
                                    @foreach (\App\Services\SupportTicket\SupportAgentService::PRIORITIES as $priority)
                                        <option value="{{ $priority }}" @selected($ticket->priority === $priority)>{{ str($priority)->headline() }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>

                        <div>
                            <label class="{{ $label }}" for="ticket-category">Category</label>
                            <x-filament::input.wrapper class="mt-1" :disabled="! $canRespond">
                                <x-filament::input.select id="ticket-category" wire:change="setCategory($event.target.value)" :disabled="! $canRespond">
                                    @foreach ($this->categoryOptions as $categoryId => $categoryName)
                                        <option value="{{ $categoryId }}" @selected($ticket->category_id === $categoryId)>{{ $categoryName }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </div>

                        <div>
                            <label class="{{ $label }}" for="ticket-assignee">Assignee</label>
                            @if ($canAssign)
                                <x-filament::input.wrapper class="mt-1">
                                    <x-filament::input.select id="ticket-assignee" wire:change="assignTo($event.target.value)">
                                        <option value="" @selected($ticket->assigned_to === null)>Unassigned</option>
                                        @foreach (\App\Filament\Pages\Support\SupportTicketDetails::agentOptions() as $agentId => $agentName)
                                            <option value="{{ $agentId }}" @selected($ticket->assigned_to === $agentId)>{{ $agentName }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            @else
                                <p class="{{ $value }}">{{ $ticket->assignedTo?->name ?? 'Unassigned' }}</p>
                            @endif

                            @if ($canRespond && $ticket->assigned_to !== \Filament\Facades\Filament::auth()->id())
                                <button type="button" wire:click="assignToMe" class="mt-1.5 text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">
                                    Assign to me
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Requester --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Requester</h3>

                    @if ($requester)
                        <div class="mt-4 flex items-center gap-3">
                            @include('filament.pages.riders.partials.avatar', ['user' => $requester])
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $requester->name !== '' ? $requester->name : 'Unnamed user' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $isRider ? 'Rider · '.$requester->riderProfile->rider_ref : 'Customer' }}</p>
                            </div>
                        </div>

                        <dl class="mt-4 space-y-3">
                            <div>
                                <dt class="{{ $label }}">Phone</dt>
                                <dd class="{{ $value }}">{{ $requester->phone ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="{{ $label }}">Email</dt>
                                <dd class="{{ $value }} break-all">{{ $requester->email ?? '—' }}</dd>
                            </div>
                            <div class="flex gap-6">
                                <div>
                                    <dt class="{{ $label }}">Account</dt>
                                    <dd class="{{ $value }}">{{ str($requester->status ?? 'unknown')->headline() }}</dd>
                                </div>
                                <div>
                                    <dt class="{{ $label }}">Joined</dt>
                                    <dd class="{{ $value }}">{{ $requester->created_at?->format('d M Y') ?? '—' }}</dd>
                                </div>
                            </div>
                        </dl>

                        @if ($requesterUrl)
                            <x-filament::button tag="a" :href="$requesterUrl" color="gray" outlined size="sm" class="mt-4 w-full" icon="heroicon-o-user-circle">
                                {{ $isRider ? 'Open rider profile' : 'Open customer' }}
                            </x-filament::button>
                        @endif
                    @else
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This account no longer exists.</p>
                    @endif
                </div>

                {{-- Linked trip --}}
                @if ($ticket->trip)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Linked trip</h3>
                        <dl class="mt-4 grid grid-cols-2 gap-3">
                            <div class="col-span-2">
                                <dt class="{{ $label }}">Trip</dt>
                                <dd class="mt-0.5 font-mono text-sm text-gray-950 dark:text-white">{{ $ticket->trip->trip_number }}</dd>
                            </div>
                            <div>
                                <dt class="{{ $label }}">Type</dt>
                                <dd class="{{ $value }}">{{ str($ticket->trip->type)->headline() }}</dd>
                            </div>
                            <div>
                                <dt class="{{ $label }}">Status</dt>
                                <dd class="{{ $value }}">{{ str($ticket->trip->status)->headline() }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="{{ $label }}">Requested</dt>
                                <dd class="{{ $value }}">{{ $ticket->trip->created_at?->format('d M Y, H:i') }}</dd>
                            </div>
                        </dl>
                        @if ($tripUrl)
                            <x-filament::button tag="a" :href="$tripUrl" color="gray" outlined size="sm" class="mt-4 w-full" icon="heroicon-o-map">
                                Open trip
                            </x-filament::button>
                        @endif
                    </div>
                @endif

                {{-- Other tickets --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Other tickets from this {{ $isRider ? 'rider' : 'customer' }}</h3>
                    @forelse ($this->otherTickets as $other)
                        <a
                            wire:key="other-ticket-{{ $other->id }}"
                            href="{{ \App\Filament\Pages\Support\SupportTicketDetails::getUrl(['record' => $other->id]) }}"
                            class="-mx-2 mt-2 flex items-center justify-between gap-2 rounded-lg px-2 py-2 hover:bg-gray-50 dark:hover:bg-white/5"
                        >
                            <span class="min-w-0">
                                <span class="block truncate text-sm text-gray-950 dark:text-white">{{ $other->subject }}</span>
                                <span class="block font-mono text-xs text-gray-500 dark:text-gray-400">{{ $other->ticket_number }} · {{ $other->created_at?->format('d M Y') }}</span>
                            </span>
                            <span class="{{ $badge }} shrink-0 {{ $statusColors[$other->status] ?? $statusColors['closed'] }}">{{ str($other->status)->headline() }}</span>
                        </a>
                    @empty
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">None.</p>
                    @endforelse
                </div>
            </aside>
        </div>
    </div>
</x-filament-panels::page>
