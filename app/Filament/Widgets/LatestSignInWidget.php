<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestSignInWidget extends TableWidget
{
    protected static ?int $sort = -4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Latest sign-in')
            ->description('When they last signed in, how long that visit lasted, and how many cards they studied.')
            ->query(User::query()->orderBy('name'))
            ->columns([
                TextColumn::make('name')
                    ->label('User'),
                TextColumn::make('latestLoginSession.started_at')
                    ->label('Signed in')
                    ->dateTime('M j, Y g:i A', 'America/Mexico_City')
                    ->placeholder('Never'),
                TextColumn::make('lasted')
                    ->label('Lasted')
                    ->getStateUsing(fn (User $record): ?string => $record->latestLoginSession?->lastedLabel())
                    ->placeholder('—'),
                TextColumn::make('latestLoginSession.cards_studied')
                    ->label('Cards studied')
                    ->placeholder('—'),
            ])
            ->paginated(false);
    }
}
