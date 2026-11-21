<?php

namespace App\Support\Downloads\Telemetry;

use InvalidArgumentException;

/**
 * HyperLogLog distinct-count sketch (Flajolet et al., 2007), precision p = 12: 4,096 one-byte registers, standard
 * error ≈ 1.04 / √4096 ≈ 1.6 %. Hash: 64-bit xxh64. Small cardinalities use linear counting (exact-ish below a few
 * thousand); 64-bit hashing needs no large-range correction at these sizes.
 *
 * Merging is the register-wise maximum: commutative, associative and idempotent — merging the same events twice, or
 * in any order, gives the same sketch. Estimates are APPROXIMATE and must always be labelled so.
 */
final class HyperLogLog
{
    public const PRECISION = 12;

    public const REGISTERS = 1 << self::PRECISION;

    private string $registers;

    public function __construct(?string $registers = null)
    {
        if ($registers !== null && strlen($registers) !== self::REGISTERS) {
            throw new InvalidArgumentException('HyperLogLog registers must be exactly '.self::REGISTERS.' bytes.');
        }
        $this->registers = $registers ?? str_repeat("\0", self::REGISTERS);
    }

    public function add(string $value): void
    {
        $hash = hash('xxh64', $value, true);                          // 8 bytes, big-endian
        $words = unpack('N2', $hash);
        $high = (int) $words[1];
        $low = (int) $words[2];

        $index = $high >> (32 - self::PRECISION);                    // top 12 bits
        // The remaining 52 bits: 20 from the high word, 32 from the low word.
        $rank = self::leadingZeros(($high << self::PRECISION) & 0xFFFFFFFF, 20);
        if ($rank === 20) {
            $rank += self::leadingZeros($low, 32);
        }
        $rank++;                                                      // position of the first 1-bit

        if ($rank > ord($this->registers[$index])) {
            $this->registers[$index] = chr($rank);
        }
    }

    public function merge(self $other): void
    {
        $mine = $this->registers;
        $theirs = $other->registers;
        for ($i = 0; $i < self::REGISTERS; $i++) {
            if ($theirs[$i] > $mine[$i]) {   // single bytes compare by value
                $mine[$i] = $theirs[$i];
            }
        }
        $this->registers = $mine;
    }

    public function estimate(): int
    {
        $m = self::REGISTERS;
        $sum = 0.0;
        $zeros = 0;
        for ($i = 0; $i < $m; $i++) {
            $r = ord($this->registers[$i]);
            $sum += 2 ** -$r;
            $zeros += $r === 0 ? 1 : 0;
        }

        $alpha = 0.7213 / (1 + 1.079 / $m);
        $estimate = $alpha * $m * $m / $sum;

        if ($estimate <= 2.5 * $m && $zeros > 0) {
            $estimate = $m * log($m / $zeros);                        // linear counting
        }

        return (int) round($estimate);
    }

    public function registers(): string
    {
        return $this->registers;
    }

    public function isEmpty(): bool
    {
        return $this->registers === str_repeat("\0", self::REGISTERS);
    }

    /** Leading zero bits among the top `$width` bits of a 32-bit value positioned at bit 31. */
    private static function leadingZeros(int $value, int $width): int
    {
        $count = 0;
        for ($bit = 31; $bit > 31 - $width; $bit--) {
            if (($value >> $bit) & 1) {
                return $count;
            }
            $count++;
        }

        return $count;
    }
}
