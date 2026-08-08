<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\User\UserCredentialService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('Avatar')
                    ->circular()
                    ->defaultImageUrl(fn (User $record): string => self::initialsAvatarUrl($record)),
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('roles.name')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                TextColumn::make('login_type')
                    ->searchable(),
                IconColumn::make('allow_login')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'banned' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('creator.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updater.name')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleter.name')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('sendCredentials')
                        ->label('Send Credentials')
                        ->icon(Heroicon::OutlinedKey)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('This generates a new password, revokes all of this user\'s active sessions and API tokens, and emails them their new credentials.')
                        ->visible(fn (User $record): bool => $record->login_type === 'password' && filled($record->email))
                        ->action(function (User $record, UserCredentialService $userCredentialService): void {
                            $userCredentialService->sendNewCredentials($record);

                            Notification::make()
                                ->title('Credentials sent')
                                ->success()
                                ->send();
                        }),
                    Action::make('suspend')
                        ->label('Suspend')
                        ->icon(Heroicon::OutlinedPauseCircle)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('The user will be unable to log in or use the app while suspended.')
                        ->visible(fn (User $record): bool => $record->status === 'active')
                        ->action(fn (User $record) => $record->update(['status' => 'suspended'])),
                    Action::make('ban')
                        ->label('Ban')
                        ->icon(Heroicon::OutlinedNoSymbol)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalDescription('This is a hard block — the user will no longer be able to log in at all. Use this only for confirmed policy violations.')
                        ->visible(fn (User $record): bool => $record->status !== 'banned')
                        ->action(fn (User $record) => $record->update(['status' => 'banned', 'allow_login' => false])),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function initialsAvatarUrl(User $record): string
    {
        $initials = $record->initials() !== '' ? $record->initials() : '?';

        $palette = ['f59e0b', '10b981', '0ea5e9', '8b5cf6', 'f43f5e', '14b8a6'];
        $color = $palette[$record->id % count($palette)];

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40">
                <rect width="40" height="40" rx="20" fill="#{$color}" />
                <text x="50%" y="50%" dy=".35em" text-anchor="middle" font-family="sans-serif" font-size="16" fill="#ffffff">{$initials}</text>
            </svg>
            SVG;

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
