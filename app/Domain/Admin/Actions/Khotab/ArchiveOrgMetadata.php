<?php

namespace App\Domain\Admin\Actions\Khotab;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The «التالي» step of «إضافة مادة» — OLD `KhotabLeechImages` → `LeechImages`
 * (`modules/Khotab/admin/index.php`, production working copy) plus its
 * helpers `fix_archive_links`, `get_identifier_xml`, `get_identifier_xml_new`,
 * `Duration` and `CoolSize` (`way2allah_functions.php`).
 *
 * For an archive.org link OLD read the item's `{identifier}_files.xml` and
 * offered, for the linked file: its size (and the exact byte count as
 * `original_size`), its duration, its format, its archive.org thumbnails
 * (the middle one preselected as the still frame) and its animated GIF.
 * Any other link yielded nothing — the admin then types the size by hand.
 * Reproduced exactly, including OLD's `https:` → `http:` rewrite of the
 * link before reading it.
 *
 * Correction: every request is bounded by `admin_khotab.archive_timeout`
 * (OLD had none), and a failed request yields "nothing found" instead of
 * OLD's PHP warning output.
 */
final class ArchiveOrgMetadata
{
    /**
     * @return array{found: bool, size: string, original_size: string, duration: string, format: string, images: list<string>, frame: string, gif: string}
     */
    public function read(string $link): array
    {
        $empty = ['found' => false, 'size' => '', 'original_size' => '', 'duration' => '', 'format' => '', 'images' => [], 'frame' => '', 'gif' => ''];

        $link = str_replace('https:', 'http:', $link);
        if (preg_match('/archive.org/i', $link) !== 1) {
            return $empty;
        }

        $info = pathinfo($link);
        $fileName = $info['basename'];
        $dir = $info['dirname'];

        $xml = $this->load(self::identifierXml($link)) ?? $this->load(self::identifierXmlNew($link));
        if ($xml === null) {
            return $empty;
        }

        $data = ['size' => '', 'duration' => '', 'format' => ''];
        $images = [];
        $gif = '';

        foreach ($xml->file as $file) {
            $name = (string) ($file->attributes()['name'] ?? '');
            $original = (string) $file->original;
            $format = (string) $file->format;
            $length = (float) (string) $file->length;

            if ($name === $fileName) {
                if ($length > 0) {
                    $data['duration'] = self::duration((int) ($length * 1000));
                }
                $data['format'] = $format;
                $data['size'] = (string) $file->size;
            }

            if ($original === $fileName && strlen($data['duration']) < 5 && $length > 0) {
                $data['duration'] = self::duration((int) ($length * 1000));
            }

            if ($original === $fileName && $format !== 'Metadata' && $format === 'Thumbnail') {
                $images[] = $dir.'/'.$name;
            }

            if ($original === $fileName && $format === 'Animated GIF') {
                $gif = $dir.'/'.$name;
            }
        }

        return [
            'found' => true,
            'size' => self::coolSize((float) $data['size']),
            'original_size' => $data['size'],
            'duration' => $data['duration'],
            'format' => $data['format'],
            'images' => $images,
            'frame' => $images === [] ? '' : $images[(int) floor(count($images) / 2)],
            'gif' => $gif,
        ];
    }

    private function load(string $url): ?\SimpleXMLElement
    {
        try {
            $response = Http::timeout(max(1, (int) config('admin_khotab.archive_timeout', 20)))->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response->body());
        libxml_use_internal_errors($previous);

        return $xml === false ? null : $xml;
    }

    /** OLD `get_identifier_xml(fix_archive_links($url))`. */
    public static function identifierXml(string $url): string
    {
        $parts = explode('/', self::fixArchiveLink($url));

        return 'http://www.archive.org/download/'.($parts[4] ?? '').'/'.($parts[4] ?? '').'_files.xml';
    }

    /** OLD `get_identifier_xml_new($url)`. */
    public static function identifierXmlNew(string $url): string
    {
        $items = explode('items', $url);
        $parts = explode('/', $items[1] ?? '');
        $identifier = $parts[1] ?? '';

        return $items[0].'items/'.$identifier.'/'.$identifier.'_files.xml';
    }

    /** OLD `fix_archive_links()`: any archive.org host becomes www.archive.org/download/{folder}/{file}. */
    public static function fixArchiveLink(string $url): string
    {
        $parts = explode('/', $url);
        $count = count($parts);
        if ($url === '' || $count < 3) {
            return $url;
        }

        $domain = explode('.', $parts[2]);
        $n = count($domain);
        if ($n < 2) {
            return $url;
        }

        if (strtolower($domain[$n - 1]) === 'org' && strtolower($domain[$n - 2]) === 'archive') {
            return 'http://www.archive.org/download/'.$parts[$count - 2].'/'.$parts[$count - 1];
        }

        return $url;
    }

    /** OLD `Duration()` — note the 12-hour `h`, kept as OLD rendered it. */
    public static function duration(int $milliseconds): string
    {
        $time = mktime(0, 0, (int) floor($milliseconds / 1000), 0, 0, 0);

        return $milliseconds > 60 * 60 * 1000 ? date('h:i:s', $time) : date('i:s', $time);
    }

    /** OLD `CoolSize()` (Arabic). */
    public static function coolSize(float $size): string
    {
        $mb = 1024 * 1024;
        $gb = $mb * 1024;
        $tb = $gb * 1024;

        return match (true) {
            $size > $tb => sprintf('%01.2f', $size / $tb).' تيرا بايت',
            $size >= $gb => sprintf('%01.2f', $size / $gb).' جيجا بايت',
            $size >= $mb => sprintf('%01.2f', $size / $mb).' ميجا بايت',
            $size >= 1024 => sprintf('%01.2f', $size / 1024).' كيلو بايت',
            $size == 0.0 => 'غير معروف',
            default => $size.' بايت',
        };
    }
}
