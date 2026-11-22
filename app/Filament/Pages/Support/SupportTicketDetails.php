<?php

namespace App\Filament\Pages\Support;

use App\Filament\Pages\Riders\RiderDetails;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Trips\TripResource;
use App\Models\ChatMessage;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportTicket\SupportAgentService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\WithFileUploads;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * One support ticket: the conversation (with internal notes), a reply/note composer with attachments
 * and saved replies, and the controls an agent needs (status, priority, category, assignee), next to
 * the requester's details, linked trip and their other tickets.
 *
 * Access follows SupportTickets (View:SupportTickets). Replying, notes and changing the ticket need
 * Respond:SupportTicket; assigning to someone else needs Assign:SupportTicket.
 */
class SupportTicketDetails extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.support.support-ticket-details';

    protected static ?string $slug = 'support-tickets/{record}';

    protected static bool $shouldRegisterNavigation = false;

    /** Saved replies offered in the composer. */
    public const array SAVED_REPLIES = [
        'Greeting' => "Hi, thanks for reaching out to Swift Boda support. I'm looking into this for you now.",
        'More details' => 'Could you share a few more details (the trip number, the time it happened, and a screenshot if you have one) so we can look into this?',
        'Refund issued' => "We've issued a refund for this. It should reflect in your wallet shortly.",
        'Escalated' => "Thanks for your patience. We've escalated this to the relevant team and will update you as soon as we hear back.",
        'Resolved' => "We've resolved this on our side. If anything else comes up, just open a new ticket and we'll be happy to help.",
    ];

    #[Locked]
    public int $recordId;

    /** 'reply' (to the requester) or 'note' (internal, staff only). */
    public string $composerMode = 'reply';

    public string $message = '';

    /** @var array<int, UploadedFile> */
    public array $uploads = [];

    public static function canAccess(): bool
    {
        return SupportTickets::canAccess();
    }

    public function mount(int $record): void
    {
        $this->recordId = $record;

        // 404 now for an unknown ticket rather than on the first action.
        $ticket = $this->ticket();

        if ($this->canRespond()) {
            app(SupportAgentService::class)->markRead($ticket, $this->agent());
        }
    }

    public function getTitle(): string
    {
        return isset($this->recordId) ? $this->ticket()->ticket_number : 'Support ticket';
    }

    #[Computed]
    public function ticket(): SupportTicket
    {
        return SupportTicket::query()
            ->with(['user.riderProfile', 'category', 'assignedTo', 'trip', 'chatRoom'])
            ->findOrFail($this->recordId);
    }

    /**
     * The whole conversation, oldest first, internal notes included (staff only).
     *
     * @return Collection<int, ChatMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        $room = $this->ticket()->chatRoom;

        if (! $room) {
            return new Collection;
        }

        return $room->messages()
            ->with(['sender.riderProfile', 'attachments'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * The requester's other tickets, newest first.
     *
     * @return Collection<int, SupportTicket>
     */
    #[Computed]
    public function otherTickets(): Collection
    {
        $ticket = $this->ticket();

        return SupportTicket::query()
            ->where('user_id', $ticket->user_id)
            ->whereKeyNot($ticket->id)
            ->latest()
            ->limit(5)
            ->get();
    }

    /**
     * Staff who can be assigned tickets: anyone holding Respond:SupportTicket (directly or by role).
     *
     * @return array<int, string>
     */
    public static function agentOptions(): array
    {
        try {
            return User::permission('Respond:SupportTicket')
                ->orderBy('first_name')
                ->get()
                ->mapWithKeys(fn (User $user): array => [$user->id => $user->name !== '' ? $user->name : "User #{$user->id}"])
                ->all();
        } catch (PermissionDoesNotExist) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function categoryOptions(): array
    {
        return SupportCategory::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function canRespond(): bool
    {
        return Filament::auth()->user()?->can('Respond:SupportTicket') ?? false;
    }

    public function canAssign(): bool
    {
        return Filament::auth()->user()?->can('Assign:SupportTicket') ?? false;
    }

    public function isClosedForReplies(): bool
    {
        return in_array($this->ticket()->status, ['resolved', 'closed'], true);
    }

    public function setComposerMode(string $mode): void
    {
        $this->composerMode = $mode === 'note' ? 'note' : 'reply';
        $this->resetErrorBag();
    }

    public function useSavedReply(string $name): void
    {
        if (isset(self::SAVED_REPLIES[$name])) {
            $this->composerMode = 'reply';
            $this->message = trim($this->message."\n\n".self::SAVED_REPLIES[$name]);
        }
    }

    public function removeUpload(int $index): void
    {
        unset($this->uploads[$index]);
        $this->uploads = array_values($this->uploads);
    }

    /**
     * Sends the composer: a reply to the requester, or an internal note.
     *
     * @param  bool  $resolve  "Reply & resolve"
     */
    public function send(SupportAgentService $agentService, bool $resolve = false): void
    {
        abort_unless($this->canRespond(), 403);

        $this->validate([
            'message' => [Rule::requiredIf($this->uploads === []), 'nullable', 'string', 'max:2000'],
            'uploads' => ['array', 'max:5'],
            'uploads.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,txt'],
        ], [
            'message.required' => 'Write a message or attach a file.',
        ]);

        $ticket = $this->ticket();

        // Same rule as the app: no replies on a resolved/closed ticket (reopen first). Notes are fine.
        if ($this->composerMode === 'reply' && $this->isClosedForReplies()) {
            $this->addError('message', 'This ticket is '.$ticket->status.'. Reopen it to reply, or add an internal note.');

            return;
        }

        $attachments = $this->storeUploads($ticket);

        if ($this->composerMode === 'note') {
            $agentService->addInternalNote($ticket, $this->agent(), $this->message, $attachments);

            Notification::make()->title('Internal note added')->success()->send();
        } else {
            $agentService->reply($ticket, $this->agent(), $this->message, $attachments, $resolve ? 'resolved' : null);

            Notification::make()->title($resolve ? 'Reply sent and ticket resolved' : 'Reply sent')->success()->send();
        }

        $this->reset('message', 'uploads');
        $this->refreshTicket();
        $this->dispatch('support-thread-updated');
    }

    public function setStatus(SupportAgentService $agentService, string $status): void
    {
        abort_unless($this->canRespond() && in_array($status, SupportAgentService::STATUSES, true), 403);

        $agentService->changeStatus($this->ticket(), $status);
        $this->refreshTicket();

        Notification::make()->title('Status changed to '.str($status)->headline())->success()->send();
    }

    public function setPriority(SupportAgentService $agentService, string $priority): void
    {
        abort_unless($this->canRespond() && in_array($priority, SupportAgentService::PRIORITIES, true), 403);

        $agentService->changePriority($this->ticket(), $priority);
        $this->refreshTicket();

        Notification::make()->title('Priority changed to '.str($priority)->headline())->success()->send();
    }

    public function setCategory(SupportAgentService $agentService, int $categoryId): void
    {
        abort_unless($this->canRespond() && array_key_exists($categoryId, $this->categoryOptions()), 403);

        $agentService->changeCategory($this->ticket(), $categoryId);
        $this->refreshTicket();

        Notification::make()->title('Category updated')->success()->send();
    }

    public function assignToMe(SupportAgentService $agentService): void
    {
        abort_unless($this->canRespond(), 403);

        $agentService->assign($this->ticket(), $this->agent());
        $this->refreshTicket();

        Notification::make()->title('Assigned to you')->success()->send();
    }

    /**
     * Assign to another agent, or unassign (empty value). Takes the raw <select> value.
     */
    public function assignTo(SupportAgentService $agentService, string $agentId = ''): void
    {
        abort_unless($this->canAssign(), 403);

        $agentId = (int) $agentId;
        $agent = $agentId ? User::query()->find($agentId) : null;
        abort_if($agentId && (! $agent || ! array_key_exists($agentId, self::agentOptions())), 422);

        $agentService->assign($this->ticket(), $agent);
        $this->refreshTicket();

        Notification::make()->title($agent ? "Assigned to {$agent->name}" : 'Unassigned')->success()->send();
    }

    public function requesterUrl(): ?string
    {
        $user = $this->ticket()->user;

        if (! $user) {
            return null;
        }

        if ($user->riderProfile) {
            return RiderDetails::canAccess() ? RiderDetails::getUrl(['record' => $user->id]) : null;
        }

        return CustomerResource::canViewAny() ? CustomerResource::getUrl('edit', ['record' => $user->id]) : null;
    }

    public function tripUrl(): ?string
    {
        $trip = $this->ticket()->trip;

        return $trip && TripResource::canViewAny() ? TripResource::getUrl('view', ['record' => $trip]) : null;
    }

    /**
     * Re-read on poll so new messages from the app show up without a reload.
     */
    public function refreshThread(): void
    {
        unset($this->messages);
    }

    /**
     * @return list<array{url: string, mime_type: string|null, size: int|null}>
     */
    private function storeUploads(SupportTicket $ticket): array
    {
        return array_values(array_map(fn (UploadedFile $file): array => [
            'url' => Storage::disk('public')->url((string) $file->store("support-tickets/{$ticket->id}", 'public')),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ?: null,
        ], $this->uploads));
    }

    private function agent(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }

    private function refreshTicket(): void
    {
        unset($this->ticket, $this->messages, $this->otherTickets);
    }
}
