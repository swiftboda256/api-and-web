<?php

namespace App\Filament\Resources\Communications\Schemas;

use App\Models\Communication;
use App\Models\User;
use App\Services\Communication\CommunicationService;
use App\Services\Communication\SmsSegments;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Spatie\Permission\Models\Role;

/**
 * The compose screen. Audience + channels + text feed a live reach/cost summary
 * (CommunicationService::preview); picking SMS shows the cost and asks the sender to
 * acknowledge it before Send works.
 */
class CommunicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Audience')
                    ->description('Everyone with a selected role (active accounts), plus anyone picked below.')
                    ->columns(2)
                    ->schema([
                        Select::make('target_roles')
                            ->label('Roles')
                            ->multiple()
                            ->options(fn (): array => Role::query()
                                ->where('name', '!=', 'system')
                                ->orderBy('name')
                                ->pluck('name', 'name')
                                ->map(fn (string $name): string => str($name)->headline()->toString())
                                ->all())
                            ->requiredWithout('target_user_ids')
                            ->validationMessages(['required_without' => 'Pick at least one role or user.'])
                            ->live(),
                        Select::make('target_user_ids')
                            ->label('Specific users')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => self::searchUsers($search))
                            ->getOptionLabelsUsing(fn (array $values): array => User::query()
                                ->whereIn('id', $values)
                                ->get()
                                ->mapWithKeys(fn (User $user): array => [$user->id => self::userLabel($user)])
                                ->all())
                            ->helperText('Search by name, phone or email.')
                            ->requiredWithout('target_roles')
                            ->validationMessages(['required_without' => 'Pick at least one role or user.'])
                            ->live(),
                    ]),

                Section::make('Message')
                    ->schema([
                        CheckboxList::make('channels')
                            ->options(Communication::CHANNELS)
                            ->descriptions([
                                Communication::CHANNEL_PUSH => 'Users with the app signed in on a device (FCM token)',
                                Communication::CHANNEL_EMAIL => 'Users with an email address',
                                Communication::CHANNEL_SMS => 'Users with a phone number — charged per SMS',
                            ])
                            ->columns(3)
                            ->required()
                            ->live(),
                        TextInput::make('title')
                            ->label('Title')
                            ->helperText('Push notification title and email subject. Not included in the SMS.')
                            ->required()
                            ->maxLength(120),
                        Textarea::make('body')
                            ->label('Message')
                            ->rows(5)
                            ->required()
                            ->maxLength(1000)
                            ->live(debounce: 600)
                            ->helperText(fn (Get $get): string => self::bodyHint((string) $get('body'), (array) $get('channels'))),
                    ]),

                Section::make('Summary')
                    ->schema([
                        Text::make(fn (Get $get): HtmlString => self::summary($get)),

                        Callout::make('SMS costs money')
                            ->warning()
                            ->description(fn (Get $get): string => self::smsCostWarning($get))
                            ->visible(fn (Get $get): bool => self::hasSms($get)),

                        // Ticking it records the amount on the label at that moment; the send is
                        // refused (and the box cleared) if the cost has changed since.
                        Checkbox::make('confirm_sms_cost')
                            ->label(fn (Get $get): string => 'I understand this SMS will cost about UGX '.Number::format(self::preview($get)['sms_cost']))
                            ->accepted()
                            ->validationMessages(['accepted' => 'Confirm the SMS cost before sending.'])
                            ->live()
                            ->afterStateUpdated(fn (bool $state, Get $get, Set $set) => $set(
                                'sms_cost_acknowledged_amount',
                                $state ? self::preview($get)['sms_cost'] : null,
                            ))
                            ->dehydrated(false)
                            ->visible(fn (Get $get): bool => self::hasSms($get)),
                        Hidden::make('sms_cost_acknowledged_amount')
                            ->dehydrated(fn (Get $get): bool => self::hasSms($get)),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function searchUsers(string $search): array
    {
        $term = '%'.trim($search).'%';

        return User::query()
            ->where('status', '!=', 'deleted')
            ->where(fn (Builder $query) => $query
                ->where('first_name', 'ilike', $term)
                ->orWhere('last_name', 'ilike', $term)
                ->orWhere('phone', 'ilike', $term)
                ->orWhere('email', 'ilike', $term))
            ->with('roles')
            ->orderBy('first_name')
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => self::userLabel($user)])
            ->all();
    }

    private static function userLabel(User $user): string
    {
        $role = $user->roles->pluck('name')->map(fn (string $name): string => str($name)->headline()->toString())->implode(', ');

        return trim(($user->name ?: 'Unnamed').' · '.($user->phone ?: $user->email ?: '#'.$user->id).($role !== '' ? " ({$role})" : ''));
    }

    private static function hasSms(Get $get): bool
    {
        return in_array(Communication::CHANNEL_SMS, (array) $get('channels'), true);
    }

    /**
     * @param  array<int, string>  $channels
     */
    private static function bodyHint(string $body, array $channels): string
    {
        $hint = mb_strlen($body).' / 1000 characters';

        if (! in_array(Communication::CHANNEL_SMS, $channels, true) || $body === '') {
            return $hint;
        }

        $segments = SmsSegments::count($body);
        $unicode = SmsSegments::isUnicode($body);

        return $hint.' · '.$segments.' SMS '.str('part')->plural($segments)
            .($unicode ? ' (emoji / special characters cut each SMS to 70 characters)' : ' (160 characters each)');
    }

    /**
     * @return array{recipients: int, reachable: array<string, int>, sms_segments: int, sms_unit_cost: int, sms_cost: int, sms_unicode: bool}
     */
    private static function preview(Get $get): array
    {
        $roles = array_values((array) $get('target_roles'));
        $userIds = array_values((array) $get('target_user_ids'));
        $channels = array_values((array) $get('channels'));
        $body = (string) $get('body');

        // Always live, never cached: the cost the sender confirms must be the current one.
        return app(CommunicationService::class)->preview($roles, $userIds, $channels, $body);
    }

    private static function summary(Get $get): HtmlString
    {
        $preview = self::preview($get);

        if ($preview['recipients'] === 0) {
            return new HtmlString('<span class="text-gray-500">Pick roles or users to see who this reaches.</span>');
        }

        $lines = ['<strong>'.Number::format($preview['recipients']).'</strong> '.str('recipient')->plural($preview['recipients'])];

        foreach ($preview['reachable'] as $channel => $reachable) {
            $missing = $preview['recipients'] - $reachable;
            $lines[] = e(Communication::CHANNELS[$channel] ?? $channel).': <strong>'.Number::format($reachable).'</strong> reachable'
                .($missing > 0 ? ' · <span class="text-danger-600">'.Number::format($missing).' will be logged as failed</span>' : '');
        }

        return new HtmlString(implode('<br>', $lines));
    }

    private static function smsCostWarning(Get $get): string
    {
        $preview = self::preview($get);
        $recipients = $preview['reachable'][Communication::CHANNEL_SMS] ?? 0;
        $segments = max($preview['sms_segments'], 1);

        return sprintf(
            'Each SMS costs UGX %s. This message is %d SMS %s per person × %s %s with a phone number ≈ UGX %s. SMS can\'t be recalled once sent.',
            Number::format($preview['sms_unit_cost']),
            $segments,
            str('part')->plural($segments),
            Number::format($recipients),
            str('recipient')->plural($recipients),
            Number::format($recipients * $segments * $preview['sms_unit_cost']),
        );
    }
}
