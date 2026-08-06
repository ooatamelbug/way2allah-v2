<?php

use Illuminate\Support\Facades\Cache;

it('round-trips a value through Cache::remember() on the configured default store', function () {
    Cache::forget('wave-0-smoke-test');

    $value = Cache::remember('wave-0-smoke-test', now()->addMinute(), fn () => 'baseline-cache-value');

    expect($value)->toBe('baseline-cache-value')
        ->and(Cache::get('wave-0-smoke-test'))->toBe('baseline-cache-value');
});
