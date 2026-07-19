<?php

namespace App\Filament\Resources\Cards\Pages;

use App\Filament\Resources\Cards\CardResource;
use App\Models\Card;
use App\Models\Subject;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCards extends ListRecords
{
    protected static string $resource = CardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            $this->importAction(),
        ];
    }

    protected function importAction(): Action
    {
        return Action::make('import')
            ->label('Import cards')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading('Import cards')
            ->modalDescription('Paste one card per line. Separate the question and answer with a Tab (or two or more spaces).')
            ->modalSubmitActionLabel('Import')
            ->schema([
                Select::make('subject_id')
                    ->label('Subject')
                    ->options(fn () => Subject::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->createOptionForm([
                        TextInput::make('name')->required(),
                    ])
                    ->createOptionUsing(fn (array $data) => Subject::create($data)->getKey())
                    ->required(),
                Textarea::make('cards')
                    ->label('Cards')
                    ->rows(12)
                    ->required()
                    ->placeholder("¿Qué es la membrana plasmática?\tEs la estructura que rodea a la célula...")
                    ->helperText('Format per line: question<Tab>answer'),
            ])
            ->action(function (array $data): void {
                $created = 0;
                $skipped = 0;

                foreach (preg_split('/\r\n|\r|\n/', $data['cards']) as $line) {
                    $line = trim($line);

                    if ($line === '') {
                        continue;
                    }

                    $parts = preg_split('/\t+| {2,}/', $line, 2);

                    if (count($parts) < 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                        $skipped++;
                        continue;
                    }

                    Card::create([
                        'subject_id' => $data['subject_id'],
                        'question' => trim($parts[0]),
                        'answer' => trim($parts[1]),
                    ]);

                    $created++;
                }

                Notification::make()
                    ->title("Imported {$created} card(s)".($skipped ? ", skipped {$skipped} line(s)" : ''))
                    ->success()
                    ->send();
            });
    }
}
