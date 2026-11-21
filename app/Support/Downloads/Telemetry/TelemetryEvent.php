<?php

namespace App\Support\Downloads\Telemetry;

use JsonException;

/**
 * One download-telemetry event, schema version 1 — the unit the spool stores,
 * one per NDJSON line.
 *
 * Every event is validated before it is encoded: only the fields below, each
 * with its type; `ev` must be a known event type. Free-text fields are cut to
 * their byte limit at a UTF-8 boundary; invalid UTF-8 is replaced. The encoded
 * line always begins with `{"v":1,` (so a reader can classify the version
 * without decoding) and never exceeds MAX_LINE_BYTES including its newline.
 *
 * Meaning of the identity fields (owner correction B): `ip` is ONE IP address
 * as Laravel resolved it; `uah` is a keyed hash of the User-Agent. An ip+uah
 * pair is a distinct IP/User-Agent combination — never a person or a "unique
 * user".
 */
final class TelemetryEvent
{
    public const VERSION = 1;

    public const MAX_LINE_BYTES = 2048;

    public const TYPES = [
        'capacity_rejected', 'source_rejected', 'source_open_failed', 'transfer_started', 'transfer_finished',
        'redirect_issued', 'head_answered', 'zip_published', 'zip_build_failed', 'zip_busy', 'zip_refused',
        'player_opened', 'download_accepted',
    ];

    /** Required besides `v`. */
    private const REQUIRED = ['t', 'ev', 'mod'];

    /**
     * Field => type. `str:N` is a string cut to N bytes; `int` is a non-negative integer.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        't' => 'time',
        'ev' => 'type',
        'id' => 'str:64',      // event id (unique per event)
        'rq' => 'str:64',      // request id
        'mod' => 'str:32',     // module
        'rt' => 'str:64',      // resource type
        'rid' => 'int',        // resource id
        'var' => 'str:32',     // variant
        'vid' => 'int',        // variant id
        'm' => 'str:10',       // HTTP method
        'head' => 'bool',
        'rng' => 'bool',       // a Range header was sent
        'ip' => 'str:45',      // one IP address (Laravel-resolved)
        'xff' => 'bool',       // an X-Forwarded-For header was present
        'ua' => 'str:256',     // User-Agent (spool only; cut)
        'uah' => 'str:32',     // keyed User-Agent hash
        'uaf' => 'str:32',     // User-Agent family
        'st' => 'str:32',      // outcome
        'b' => 'int',          // bytes sent
        'dl' => 'int',         // declared length
        'd' => 'int',          // duration (ms)
        'ttfb' => 'int',       // time to first byte / open (ms)
        'sh' => 'str:128',     // source host
        'ss' => 'int',         // source status
        'pa' => 'int',         // pool active
        'pl' => 'int',         // pool limit
        'k' => 'str:32',       // kind / reason
    ];

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR;

    /** @param array<string, bool|int|float|string> $fields normalized, in schema order */
    private function __construct(private readonly array $fields) {}

    /**
     * @param  array<array-key, mixed>  $data  decoded JSON may carry integer keys (`{"0": …}`), which are rejected
     *
     * @throws InvalidTelemetryEvent
     */
    public static function fromArray(array $data): self
    {
        unset($data['v']);

        foreach (array_keys($data) as $key) {
            if (! is_string($key) || ! array_key_exists($key, self::FIELDS)) {
                throw new InvalidTelemetryEvent('Unknown telemetry field: '.(string) $key);
            }
        }
        foreach (self::REQUIRED as $key) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                throw new InvalidTelemetryEvent("Missing telemetry field: {$key}");
            }
        }

        $fields = [];
        foreach (self::FIELDS as $key => $type) {
            if (! array_key_exists($key, $data) || $data[$key] === null) {
                continue;
            }
            $fields[$key] = self::normalize($key, $type, $data[$key]);
        }

        return new self($fields);
    }

    /** @return array<string, bool|int|float|string> including `v` */
    public function toArray(): array
    {
        return ['v' => self::VERSION] + $this->fields;
    }

    /**
     * The NDJSON line, WITHOUT its trailing newline.
     *
     * @throws InvalidTelemetryEvent when the encoded line would exceed MAX_LINE_BYTES
     */
    public function encode(): string
    {
        try {
            $json = json_encode($this->toArray(), self::JSON_FLAGS);
        } catch (JsonException $e) {
            throw new InvalidTelemetryEvent('Telemetry event cannot be encoded: '.$e->getMessage());
        }

        if (strlen($json) + 1 > self::MAX_LINE_BYTES) {
            throw new InvalidTelemetryEvent('Telemetry event exceeds '.self::MAX_LINE_BYTES.' bytes.');
        }

        return $json;
    }

    /**
     * Classify one spool line (without its newline).
     *
     * @return array{status: 'ok'|'malformed'|'unsupported', event: ?self, version: ?int}
     */
    public static function decode(string $line): array
    {
        try {
            $data = json_decode($line, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['status' => 'malformed', 'event' => null, 'version' => null];
        }

        if (! is_array($data) || ! isset($data['v']) || ! is_int($data['v']) || $data['v'] < 1) {
            return ['status' => 'malformed', 'event' => null, 'version' => null];
        }
        if ($data['v'] > self::VERSION) {
            return ['status' => 'unsupported', 'event' => null, 'version' => $data['v']];
        }

        try {
            return ['status' => 'ok', 'event' => self::fromArray($data), 'version' => $data['v']];
        } catch (InvalidTelemetryEvent) {
            return ['status' => 'malformed', 'event' => null, 'version' => $data['v']];
        }
    }

    private static function normalize(string $key, string $type, mixed $value): bool|int|float|string
    {
        switch (true) {
            case $type === 'time':
                if ((! is_int($value) && ! is_float($value)) || $value < 0 || ! is_finite((float) $value)) {
                    throw new InvalidTelemetryEvent("Telemetry field {$key} must be a non-negative timestamp.");
                }

                return round((float) $value, 3);

            case $type === 'type':
                if (! is_string($value) || ! in_array($value, self::TYPES, true)) {
                    throw new InvalidTelemetryEvent('Unknown telemetry event type.');
                }

                return $value;

            case $type === 'int':
                if (! is_int($value) || $value < 0) {
                    throw new InvalidTelemetryEvent("Telemetry field {$key} must be a non-negative integer.");
                }

                return $value;

            case $type === 'bool':
                if (! is_bool($value)) {
                    throw new InvalidTelemetryEvent("Telemetry field {$key} must be a boolean.");
                }

                return $value;

            default: // str:N
                if (! is_string($value)) {
                    throw new InvalidTelemetryEvent("Telemetry field {$key} must be a string.");
                }

                return mb_strcut($value, 0, (int) substr($type, 4), 'UTF-8');
        }
    }
}
