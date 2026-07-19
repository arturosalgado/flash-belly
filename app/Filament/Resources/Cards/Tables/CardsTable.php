<?php

namespace App\Filament\Resources\Cards\Tables;

use App\Models\Card;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->searchable()
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('subject.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('confidence')
                    ->label('Confidence')
                    ->sortable()
                    ->badge()
                    ->color(fn (Card $record): string => match ($record->strength()) {
                        'weak' => 'danger',
                        'learning' => 'warning',
                        'strong' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "+{$state}" : (string) $state),
                TextColumn::make('reviews')
                    ->label('Reviews')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('last_reviewed_at')
                    ->label('Last studied')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('subject')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
