<?php

use App\Domain\Admin\Actions\Categories\CategoryList;

/**
 * OLD Admin Migration Phase 3 — the pure list parser/formatter behind every
 * category assignment. Owner decision (plan §20): read both stored forms,
 * write only the canonical `|a|b|c|` form.
 */
it('parses the no-leading-pipe form the deployed OLD writer produces', function () {
    expect(CategoryList::parse('3|17|5|'))->toBe(['3', '17', '5']);
});

it('parses the canonical leading-and-trailing-pipe form', function () {
    expect(CategoryList::parse('|3|17|5|'))->toBe(['3', '17', '5']);
});

it('treats null, empty and pipe-only values as an empty list', function (?string $raw) {
    expect(CategoryList::parse($raw))->toBe([]);
})->with([null, '', '|', '||']);

it('formats canonically, and an empty list as the empty string', function () {
    expect(CategoryList::format(['3', '17', '5']))->toBe('|3|17|5|')
        ->and(CategoryList::format([]))->toBe('');
});

it('reproduces the owner examples', function () {
    $legacy = CategoryList::parse('3|17|5|');

    expect(CategoryList::format(CategoryList::withAdded($legacy, 9)))->toBe('|3|17|5|9|')
        ->and(CategoryList::format(CategoryList::withRemoved($legacy, 3)))->toBe('|17|5|')
        ->and(CategoryList::contains($legacy, 17))->toBeTrue()
        ->and(CategoryList::contains($legacy, 99))->toBeFalse();
});

it('matches whole tokens only — 1 is not contained in |17|', function () {
    $tokens = CategoryList::parse('|17|171|');

    expect(CategoryList::contains($tokens, 1))->toBeFalse()
        ->and(CategoryList::contains($tokens, 7))->toBeFalse()
        ->and(CategoryList::format(CategoryList::withRemoved($tokens, 17)))->toBe('|171|');
});

it('does not duplicate on add', function () {
    expect(CategoryList::withAdded(['3', '17'], 17))->toBe(['3', '17']);
});

it('removes every occurrence of a duplicated id', function () {
    expect(CategoryList::withRemoved(['3', '17', '3'], 3))->toBe(['17']);
});

it('unions lists in first-seen order across both stored forms', function () {
    expect(CategoryList::union(['3|17|', '|17|5|', null, '']))->toBe(['3', '17', '5']);
});

it('compares as sets, ignoring order and format', function () {
    expect(CategoryList::sameSet(CategoryList::parse('5|3|'), CategoryList::parse('|3|5|')))->toBeTrue()
        ->and(CategoryList::sameSet(['3'], ['3', '5']))->toBeFalse();
});
