<?php

use App\Domain\Admin\Actions\Fatwa\PipeIdList;

/**
 * Fatwa OLD public admin parity (2026-10-30) — the legacy `|a|b|` relationship
 * lists (`general_questions.topic_id`, `questions.general_question_id`):
 * ordered, positive, duplicate-free; first = primary (OLD get_the_first_id).
 */
it('parses OLD\'s pipe format in stored order', function () {
    expect(PipeIdList::parse('|12|3|45|')->ids())->toBe([12, 3, 45])
        ->and(PipeIdList::parse('|7|')->ids())->toBe([7]);
});

it('reads a value without pipes as one id, as OLD get_topic_id_for_general_question did', function () {
    expect(PipeIdList::parse('9')->ids())->toBe([9])
        ->and(PipeIdList::parse('9|4|')->ids())->toBe([9, 4]);
});

it('drops empty, zero, negative, non-numeric and repeated elements', function () {
    expect(PipeIdList::parse('||0|5|abc|5|-3|1.5| 8 |')->ids())->toBe([5, 8])
        ->and(PipeIdList::parse(null)->ids())->toBe([])
        ->and(PipeIdList::parse('')->isEmpty())->toBeTrue()
        ->and(PipeIdList::parse('||')->isEmpty())->toBeTrue()
        ->and(PipeIdList::parse('0')->isEmpty())->toBeTrue();
});

it('identifies the first id', function () {
    expect(PipeIdList::parse('|30|20|10|')->first())->toBe(30)
        ->and(PipeIdList::parse('')->first())->toBeNull();
});

it('appends new ids after the stored ones, keeping stored order and skipping duplicates', function () {
    $list = PipeIdList::parse('|30|20|')->append(PipeIdList::of([5, 20, 40, 5]));

    expect($list->ids())->toBe([30, 20, 5, 40])
        ->and($list->first())->toBe(30)
        ->and($list->toString())->toBe('|30|20|5|40|');
});

it('removes selected ids and keeps the remaining stored order', function () {
    $list = PipeIdList::parse('|30|20|10|40|')->without(PipeIdList::of(['30', '10', '99']));

    expect($list->ids())->toBe([20, 40])->and($list->first())->toBe(20)->and($list->toString())->toBe('|20|40|');
});

it('serializes back to OLD\'s format, and an empty list to an empty string', function () {
    expect(PipeIdList::of([4])->toString())->toBe('|4|')
        ->and(PipeIdList::of(['4', 6, '6'])->toString())->toBe('|4|6|')
        ->and(PipeIdList::of([])->toString())->toBe('')
        ->and(PipeIdList::parse('|1|2|')->without(PipeIdList::of([1, 2]))->toString())->toBe('');
});

it('answers contains and count', function () {
    $list = PipeIdList::parse('|3|12|');

    expect($list->contains(12))->toBeTrue()->and($list->contains(1))->toBeFalse()
        ->and($list->count())->toBe(2);
});
