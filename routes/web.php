<?php

use App\Livewire\Study;
use Illuminate\Support\Facades\Route;

Route::get('/', Study::class)
    ->middleware('auth')
    ->name('study');
