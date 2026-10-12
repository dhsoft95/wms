<?php

arch('no debug calls in app/')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'ray', 'ddd', 'var_dump']);

arch('Livewire components do not call DB directly')
    ->expect('App\Livewire')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('Models do not depend on Livewire')
    ->expect('App\Models')
    ->not->toUse('Livewire\Component');

arch('only App\\Services\\Inventory may write stock tables')
    ->expect('App')
    ->not->toUse([
        'App\Models\Inventory\StockMovement',
        'App\Models\Inventory\StockTransaction',
        'App\Models\Inventory\StockBalance',
        'App\Models\Inventory\StockReservation',
    ])
    ->ignoring([
        'App\Services\Inventory',
        'App\Models',
        'App\Events',
        'App\Policies',
        'App\Console',
    ]);
