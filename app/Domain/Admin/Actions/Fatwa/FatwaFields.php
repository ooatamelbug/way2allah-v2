<?php

namespace App\Domain\Admin\Actions\Fatwa;

/**
 * The fatwa form shared by Q1 «اضافة فتوى للسؤال» and A4 «تعديل» — OLD
 * `fatwa_question_add.htm` / `fatwa_question_edit.htm`, under OLD's field
 * names — mapped to `nuke_fatwa_questions` columns.
 *
 * Owner-approved corrections to OLD:
 * - **media_size keeps its precision.** The column is DOUBLE, in MB (the
 *   form's unit, and what the public page prints). OLD cast it to `int`,
 *   truncating 2.25 to 2; Laravel stores the decimal. An unchanged prefill
 *   keeps the stored value as it is (no display round-trip);
 * - **a cleared date is NULL.** `date_of_fatwa` is a nullable DATE; OLD's
 *   edit wrote '' (rejected under strict SQL mode). An unchanged empty date
 *   is not rewritten at all;
 * - **place_of_fatwa is not part of the form**, so it is never written
 *   (OLD's edit compared it with a field the form does not send and could
 *   clear it).
 */
final class FatwaFields
{
    public const NO_CHANNEL = 0;

    /** The size field's prefill for a stored DOUBLE (MB). */
    public static function sizeField(mixed $stored): string
    {
        if ($stored === null || $stored === '' || ! is_numeric($stored)) {
            return '';
        }

        return (string) (float) $stored;
    }

    /** The date field's prefill: Y-m-d, or '' for NULL / a zero date. */
    public static function dateField(mixed $stored): string
    {
        $value = trim((string) $stored);

        return preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1 && ! str_starts_with($value, '0000-00-00') ? substr($value, 0, 10) : '';
    }

    /**
     * @param  array<string, mixed>  $data  the validated form
     * @param  object|null  $stored  the current row, for A4
     * @return array<string, mixed> column => value
     */
    public static function columns(array $data, ?object $stored = null): array
    {
        $columns = [
            'auther_id' => (int) $data['authers'],
            'question_text' => (string) $data['fatwa_text'],
            'answer_text' => (string) ($data['fatwa_answer_text'] ?? ''),
            'media_type' => (string) $data['audio_or_video'],
            'media_link' => trim((string) ($data['media_link'] ?? '')),
            'media_size' => self::mediaSize($data['media_size'] ?? null, $stored?->media_size),
            'channel_id' => (int) ($data['channel'] ?? self::NO_CHANNEL),
        ];

        $date = trim((string) ($data['fatwa_date'] ?? ''));
        if ($stored === null) {
            // Q1: OLD left the column out when no date was given — NULL.
            $columns['date_of_fatwa'] = $date === '' ? null : $date;
        } elseif ($date !== self::dateField($stored->date_of_fatwa)) {
            $columns['date_of_fatwa'] = $date === '' ? null : $date;
        }

        return $columns;
    }

    /** An unchanged prefill keeps the stored value; anything else is the MB value as given. */
    private static function mediaSize(mixed $input, mixed $stored): float|int|string
    {
        $value = trim((string) ($input ?? ''));

        if ($stored !== null && $value === self::sizeField($stored)) {
            return $stored;
        }

        return $value === '' ? 0 : (float) $value;
    }
}
