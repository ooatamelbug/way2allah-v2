<?php

namespace App\Support\Downloads\Archive;

use JsonException;

/**
 * The durable state of one archive run (Phase 3D): `runs/{archive_id}.json`, replaced atomically (temp file + fsync +
 * rename) on every transition and after every chunk. A crash can therefore only ever leave the previous complete
 * version of the journal behind.
 *
 *   PENDING → COPYING → VERIFIED → DELETING → COMPLETED
 *                   ↘ VERIFY_FAILED (stops; operator action: --abandon)      COMPLETED → (expired_at set) on expiry
 *
 * ABANDONED is set only by an operator (--abandon) for a VERIFY_FAILED run.
 */
final class ArchiveJournal
{
    public const PENDING = 'PENDING';

    public const COPYING = 'COPYING';

    public const VERIFIED = 'VERIFIED';

    public const DELETING = 'DELETING';

    public const COMPLETED = 'COMPLETED';

    public const VERIFY_FAILED = 'VERIFY_FAILED';

    public const ABANDONED = 'ABANDONED';

    public const INCOMPLETE = [self::PENDING, self::COPYING, self::VERIFIED, self::DELETING];

    /** @param array<string, mixed> $data */
    private function __construct(private readonly ArchiveConfig $config, public array $data) {}

    public static function start(ArchiveConfig $config, int $now, int $cutoff, int $highId, array $columns): self
    {
        $id = 'rej-'.gmdate('Ymd\THis\Z', $now).'-'.bin2hex(random_bytes(4));

        $journal = new self($config, [
            'archive_id' => $id, 'state' => self::PENDING, 'started_at' => $now, 'updated_at' => $now, 'cutoff' => $cutoff,
            'high_id' => $highId, 'columns' => $columns, 'rows' => 0, 'last_copied_id' => 0, 'content_sha256' => null,
            'file_sha256' => null, 'file_bytes' => 0, 'min_id' => null, 'max_id' => null, 'min_created_at' => null,
            'max_created_at' => null, 'backlog' => false, 'deleted_rows' => 0, 'delete_chunk' => 0, 'attempts' => 0,
            'error' => null, 'verified_at' => null, 'completed_at' => null,
            // Phase 3D.1: day segments, the run digest over their manifests, the backup confirmation bound to it.
            'segments' => [], 'digest' => null, 'backup_confirmation' => null, 'delete_segment' => 0, 'expired_segments' => [],
        ]);
        $journal->save($now);

        return $journal;
    }

    public static function load(ArchiveConfig $config, string $path): ?self
    {
        try {
            $data = json_decode((string) @file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) && isset($data['archive_id'], $data['state']) ? new self($config, $data) : null;
    }

    public static function find(ArchiveConfig $config, string $archiveId): ?self
    {
        return preg_match('/^rej-[0-9TZ]+-[0-9a-f]{8}$/', $archiveId) === 1 ? self::load($config, $config->runsDir().'/'.$archiveId.'.json') : null;
    }

    /** Every journal, oldest first (bounded by the directory listing). @return list<self> */
    public static function all(ArchiveConfig $config): array
    {
        $all = [];
        foreach (@scandir($config->runsDir()) ?: [] as $name) {
            if (str_ends_with($name, '.json') && str_starts_with($name, 'rej-') && ($j = self::load($config, $config->runsDir().'/'.$name)) !== null) {
                $all[] = $j;
            }
        }
        usort($all, fn (self $a, self $b) => [$a->data['started_at'], $a->id()] <=> [$b->data['started_at'], $b->id()]);

        return $all;
    }

    public function id(): string
    {
        return (string) $this->data['archive_id'];
    }

    public function state(): string
    {
        return (string) $this->data['state'];
    }

    public function set(array $changes, int $now): void
    {
        $this->data = $changes + $this->data;
        $this->save($now);
    }

    public function save(int $now): void
    {
        $this->data['updated_at'] = $now;
        ArchiveFiles::atomicWrite($this->config, $this->config->runsDir().'/'.$this->id().'.json',
            (string) json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
