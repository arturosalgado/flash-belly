<?php

namespace App\Filament\Resources\Cards\Pages;

use App\Filament\Resources\Cards\CardResource;
use App\Models\Subject;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateCard extends CreateRecord
{
    protected static string $resource = CardResource::class;

    protected function afterFill(): void
    {
        $subjectId = session('flashcards.last_subject_id');

        if (blank($subjectId) || ! Subject::query()->whereKey($subjectId)->exists()) {
            return;
        }

        $this->form->fill([
            'subject_id' => $subjectId,
        ]);
    }

    protected function afterCreate(): void
    {
        session(['flashcards.last_subject_id' => $this->getRecord()->subject_id]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function preserveFormDataWhenCreatingAnother(array $data): array
    {
        return Arr::only($data, ['subject_id']);
    }
}
