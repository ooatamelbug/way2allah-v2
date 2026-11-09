<?php

namespace App\Domain\Admin\Actions\Fatwa;

/**
 * The legacy Fatwa pipe relationship — `nuke_fatwa_general_questions.topic_id`
 * (`|t1|t2|`) and `nuke_fatwa_questions.general_question_id` (`|q1|q2|`) —
 * as an ordered list of positive ids.
 *
 * Reading follows OLD: `question_many_topics_arr()` / `fatwa_many_questions_arr()`
 * split on `|` and keep the inner elements in stored order;
 * `get_the_first_id()` / `get_topic_id_for_general_question()` treat the
 * FIRST element as the primary one, and a value without pipes as a single id.
 * Empty, zero, non-numeric and repeated elements are dropped, so the list is
 * always ordered, positive and duplicate-free.
 *
 * Writing produces OLD's format exactly: `|a|b|`. An empty list serializes to
 * '' — callers refuse to store one (owner rule: at least one remains).
 */
final readonly class PipeIdList
{
    /** @param list<int> $ids ordered, positive, unique */
    private function __construct(private array $ids) {}

    public static function parse(?string $value): self
    {
        return self::of(explode('|', (string) $value));
    }

    /** @param iterable<mixed> $values */
    public static function of(iterable $values): self
    {
        $ids = [];

        foreach ($values as $value) {
            $value = is_int($value) ? (string) $value : trim((string) $value);
            if ($value === '' || ! ctype_digit($value)) {
                continue;
            }
            $id = (int) $value;
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return new self($ids);
    }

    /** @return list<int> */
    public function ids(): array
    {
        return $this->ids;
    }

    public function first(): ?int
    {
        return $this->ids[0] ?? null;
    }

    public function count(): int
    {
        return count($this->ids);
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    public function contains(int $id): bool
    {
        return in_array($id, $this->ids, true);
    }

    /** Existing ids keep their stored order; new ones are appended in the given order, without duplicates. */
    public function append(self $other): self
    {
        return self::of([...$this->ids, ...$other->ids]);
    }

    /** The remaining ids, in stored order. */
    public function without(self $other): self
    {
        return new self(array_values(array_filter($this->ids, fn (int $id) => ! $other->contains($id))));
    }

    public function toString(): string
    {
        return $this->ids === [] ? '' : '|'.implode('|', $this->ids).'|';
    }
}
