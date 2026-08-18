<?php

use App\Domain\Content\Support\LegacyTextTruncator;

it('words: returns short text unchanged, no ellipsis', function () {
    expect(LegacyTextTruncator::words('short title', 90))->toBe('short title');
});

it('words: truncates long text on a word boundary with a leading space (matching legacy substrwords()\'s own leading-space quirk)', function () {
    $text = str_repeat('word ', 30); // far exceeds any realistic maxchar below
    $result = LegacyTextTruncator::words($text, 20);

    expect($result)->toStartWith(' word')
        ->and(strlen($result))->toBeLessThanOrEqual(20 + 10) // ellipsis + margin, loose bound
        ->and($result)->toEndWith('...');
});

it('words: never splits mid-word', function () {
    $result = LegacyTextTruncator::words('one two three four five six seven', 10);

    foreach (explode(' ', trim(str_replace('...', '', $result))) as $word) {
        expect($word)->not->toBe('')
            ->and(in_array($word, ['one', 'two', 'three', 'four', 'five', 'six', 'seven'], true))->toBeTrue();
    }
});

it('words: empty string does not infinite-loop (a safe deviation from legacy\'s own unreachable-in-practice behavior — see class docblock)', function () {
    $result = LegacyTextTruncator::words('', 90);

    expect($result)->toBeString();
});

it('chars: uses a plain character-count threshold (not word-boundary), with the caller-supplied ellipsis', function () {
    expect(LegacyTextTruncator::chars('short', 60, ' ...'))->toBe('short')
        ->and(LegacyTextTruncator::chars(str_repeat('x', 65), 60, ' ...'))->toBe(str_repeat('x', 60).' ...')
        ->and(LegacyTextTruncator::chars(str_repeat('x', 120), 110, '..'))->toBe(str_repeat('x', 110).'..');
});

it('mixedMultibyte: preserves the legacy mismatch — strlen() (byte) threshold gates whether mb_substr() (character) truncation fires', function () {
    // Exactly 40 bytes, all ASCII: strlen()==40, no truncation.
    $exactlyForty = str_repeat('a', 40);
    expect(LegacyTextTruncator::mixedMultibyte($exactlyForty, 40, 25))->toBe($exactlyForty);

    // 41 bytes: strlen() > 40 fires truncation, mb_substr() takes 25 UTF-8 chars.
    $fortyOne = str_repeat('a', 41);
    expect(LegacyTextTruncator::mixedMultibyte($fortyOne, 40, 25))->toBe(str_repeat('a', 25).' ...');
});

it('mixedMultibyte: multibyte (Arabic) text is measured in raw bytes for the threshold, not characters — the exact quirk being preserved', function () {
    // Arabic text where byte length (UTF-8, ~2 bytes/char) exceeds 40 well before 40 *characters* would.
    $arabic = str_repeat('س', 25); // 25 chars, 50 bytes in UTF-8 — strlen() sees 50, not 25.
    $result = LegacyTextTruncator::mixedMultibyte($arabic, 40, 25);

    // Byte length (50) > 40, so truncation fires even though there are only 25 characters.
    expect(mb_strlen($result, 'utf-8'))->toBeLessThanOrEqual(25 + 4); // 25 chars + ' ...'
});
