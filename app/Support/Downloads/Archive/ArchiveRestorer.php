<?php

namespace App\Support\Downloads\Archive;

use Illuminate\Database\Connection;

/**
 * Controlled restore of ONE verified archive (Phase 3D) — never automatic, never into the live table.
 *
 * Rows go into `download_rejections_restored` (created on first real restore with the live table's structure), so a
 * restored row can never be re-archived as a duplicate, mixed with new refusals, or collide with a reused id. Steps:
 * integrity check (file + content checksums, record count) → per bounded chunk: duplicate detection by `id` (identical
 * → skipped, different → conflict, never overwritten) → bounded INSERT. `dry_run` reports what would happen and
 * changes nothing (not even the table).
 */
final class ArchiveRestorer
{
    public const TARGET = 'download_rejections_restored';

    public function __construct(private readonly ArchiveConfig $config, private readonly Connection $db) {}

    /** @return array{archive_id: string, ok: bool, error: ?string, dry_run: bool, rows: int, inserted: int, duplicates: int, conflicts: int, target: string} */
    public function restore(string $archiveId, bool $dryRun): array
    {
        $report = ['archive_id' => $archiveId, 'ok' => false, 'error' => null, 'dry_run' => $dryRun, 'rows' => 0, 'inserted' => 0, 'duplicates' => 0, 'conflicts' => 0, 'target' => self::TARGET];
        $manifest = ArchiveFiles::manifest($this->config, $archiveId);
        if ($manifest === null) {
            return ['error' => 'no published archive with this id'] + $report;
        }
        $path = ArchiveFiles::dataPath($this->config, $archiveId);
        $problems = ArchiveFiles::check($path, (int) $manifest['rows'], (string) $manifest['content_sha256'], (string) $manifest['file_sha256']);
        if ($problems !== []) {
            return ['error' => 'integrity check failed: '.implode('; ', $problems)] + $report;
        }

        $schema = $this->db->getSchemaBuilder();
        $exists = $schema->hasTable(self::TARGET);
        if (! $dryRun && ! $exists) {
            $this->createTarget();
            $exists = true;
        }
        if ($exists) {
            $missing = array_diff((array) $manifest['columns'], $schema->getColumnListing(self::TARGET));
            if ($missing !== []) {
                return ['error' => 'restore table lacks columns: '.implode(', ', $missing)] + $report;
            }
        }

        $columns = (array) $manifest['columns'];
        $batch = [];
        $process = function (array $batch) use (&$report, $exists, $dryRun, $columns) {
            $existing = $exists ? $this->db->table(self::TARGET)->whereIn('id', array_keys($batch))->get($columns)->keyBy('id') : collect();
            $insert = [];
            foreach ($batch as $id => [$line, $row]) {
                $present = $existing->get($id) ?? $existing->get((string) $id);
                if ($present !== null) {
                    ArchiveFiles::encode((array) $present, $columns) === $line ? $report['duplicates']++ : $report['conflicts']++;

                    continue;
                }
                $insert[] = $row;
            }
            if (! $dryRun && $insert !== []) {
                $this->db->table(self::TARGET)->insert($insert);
            }
            $report['inserted'] += count($insert);
        };
        foreach (ArchiveFiles::lines($path) as $line) {
            $row = ArchiveFiles::decode($line);
            $report['rows']++;
            $batch[(int) $row['id']] = [$line, $row];
            if (count($batch) >= min(1000, $this->config->chunkRows)) {
                $process($batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $process($batch);
        }
        if (! $dryRun) {
            ArchiveFiles::audit($this->config, ['action' => 'restored', 'archive_id' => $archiveId, 'target' => self::TARGET,
                'inserted' => $report['inserted'], 'duplicates' => $report['duplicates'], 'conflicts' => $report['conflicts']]);
        }

        return ['ok' => true] + $report;
    }

    private function createTarget(): void
    {
        $source = RejectionArchiver::TABLE;
        if (in_array($this->db->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->db->statement('CREATE TABLE IF NOT EXISTS `'.self::TARGET.'` LIKE `'.$source.'`');   // same columns, keys and indexes
        } else {
            $this->db->statement('CREATE TABLE IF NOT EXISTS "'.self::TARGET.'" AS SELECT * FROM "'.$source.'" WHERE 0');
        }
    }
}
