<?php

namespace App\Support;

/**
 * Menerjemahkan tampilan ke bahasa Inggris pada hasil akhir halaman, memakai kamus lang/en.json.
 * Mode Indonesia tidak melewati kelas ini sama sekali, jadi tampilannya tetap persis seperti teks aslinya.
 * Yang diterjemahkan hanya teks yang tampil: nilai form, kode, dan isi skrip tidak disentuh.
 * Teks yang muncul belakangan lewat JavaScript ditangani partials/i18n-client memakai kamus yang sama.
 */
class UiTranslator
{
    private const MONTHS = [
        'Januari' => 'January', 'Februari' => 'February', 'Maret' => 'March', 'April' => 'April', 'Mei' => 'May', 'Juni' => 'June', 'Juli' => 'July',
        'Agustus' => 'August', 'September' => 'September', 'Oktober' => 'October', 'November' => 'November', 'Desember' => 'December',
        'Agu' => 'Aug', 'Okt' => 'Oct', 'Des' => 'Dec',
    ];

    private const ATTRIBUTES = 'placeholder|title|aria-label|alt|data-tooltip|label';

    /** @var array<string,string> */
    private array $exact = [];
    /** @var array<string,string> kunci huruf kecil */
    private array $lower = [];
    /** @var array<string,array<int,array{0:string,1:string}>> kata pertama => [pola, terjemahan] */
    private array $patterns = [];
    /** @var array<string,true> teks yang belum punya terjemahan, hanya saat I18N_LOG aktif */
    public array $misses = [];

    private bool $logMisses;
    /** @var array<string,true> hasil terjemahan, supaya teks yang sudah Inggris tidak dicatat sebagai kekurangan */
    private array $english = [];

    private static ?self $instance = null;

    public function __construct(?array $dictionary = null)
    {
        $this->logMisses = (bool) env('I18N_LOG', false);
        foreach ($dictionary ?? self::dictionary() as $key => $value) {
            if (str_contains($key, ':x')) {
                $this->addPattern($key, $value);
            } else {
                $this->exact[$key] = $value;
                $this->lower[mb_strtolower($key)] = $value;
                if ($this->logMisses) {
                    $this->english[$value] = true;
                }
            }
        }
        foreach ($this->patterns as &$bucket) {
            usort($bucket, fn ($a, $b) => strlen($b[0]) <=> strlen($a[0]));
        }
        unset($bucket);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public static function dictionaryPath(): string
    {
        return lang_path('en.json');
    }

    public static function dictionary(): array
    {
        $path = self::dictionaryPath();

        return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
    }

    private function addPattern(string $key, string $value): void
    {
        $regex = '/^' . str_replace(preg_quote(':x', '/'), '(.+?)', preg_quote($key, '/')) . '$/u';
        $first = strtok($key, ' ');
        $this->patterns[str_contains((string) $first, ':x') ? '*' : mb_strtolower((string) $first)][] = [$regex, $value];
    }

    /** Terjemahan satu potongan teks. Spasi di awal dan akhir dipertahankan. */
    public function text(string $source, int $depth = 0): string
    {
        $core = trim($source, " \t\n\r\0\x0B\u{00A0}");
        if ($core === '' || ! preg_match('/\p{L}{2}/u', $core)) {
            return $source;
        }
        $normal = preg_replace('/\s+/u', ' ', $core);
        $translated = $this->lookup($normal, $depth);
        if ($translated === null) {
            $dated = $this->months($normal);
            if ($dated !== $normal) {
                return str_replace($core, $dated, $source);
            }
            if ($this->logMisses && $depth === 0 && ! isset($this->english[$normal])) {
                $this->misses[$normal] = true;
            }

            return $source;
        }

        return str_replace($core, $translated, $source);
    }

    private function lookup(string $text, int $depth): ?string
    {
        if (isset($this->exact[$text])) {
            return $this->exact[$text];
        }
        // Tanda baca di ujung tidak perlu masuk kamus: "Total:", "Nama *", "(Opsional)"
        if (preg_match('/^([\s(\["\'“*•·–+=−←→▲▼>-]*)(.*?)([\s)\]"\'”:;.,!?*…·–→-]*)$/us', $text, $m) && $m[2] !== '' && ($m[1] !== '' || $m[3] !== '')) {
            $inner = $this->lookup($m[2], $depth);
            if ($inner !== null) {
                return $m[1] . $inner . $m[3];
            }
        }
        if (str_starts_with($text, 'TRUSTA - ')) {
            $inner = $this->lookup(substr($text, 9), $depth);

            return $inner === null ? null : 'TRUSTA - ' . $inner;
        }
        // Kode atau jumlah di depan: "5322 Beban Umum", "3 barang"
        if (preg_match('/^(\d[\d.,\-]*\s+(?:[-–—:]\s+)?)(\p{L}.*)$/us', $text, $m)) {
            $inner = $this->lookup($m[2], $depth);
            if ($inner !== null) {
                return $m[1] . $inner;
            }
        }
        // Gabungan dengan pemisah: "Lampiran 1C · Periode 01 Jan s/d 31 Des"
        foreach ([' · ', ' | '] as $separator) {
            if (str_contains($text, $separator)) {
                $parts = array_map(fn ($part) => $this->months($this->text($part, $depth + 1)), explode($separator, $text));
                $joined = implode($separator, $parts);
                if ($joined !== $text) {
                    return $joined;
                }
            }
        }
        $lower = mb_strtolower($text);
        if (isset($this->lower[$lower])) {
            $value = $this->lower[$lower];
            if ($text === mb_strtoupper($text)) {
                return mb_strtoupper($value);
            }

            return mb_substr($text, 0, 1) === mb_strtolower(mb_substr($text, 0, 1)) ? mb_strtolower(mb_substr($value, 0, 1)) . mb_substr($value, 1) : $value;
        }

        return $depth < 2 ? $this->pattern($text, $depth) : null;
    }

    private function pattern(string $text, int $depth): ?string
    {
        $first = mb_strtolower((string) strtok($text, ' '));
        foreach ([...($this->patterns[$first] ?? []), ...($this->patterns['*'] ?? [])] as [$regex, $value]) {
            if (preg_match($regex, $text, $m)) {
                array_shift($m);
                $i = 0;

                return $this->months(preg_replace_callback('/:x/', function () use (&$i, $m, $depth) {
                    $part = $m[$i++] ?? '';

                    return $this->text($part, $depth + 1);
                }, $value));
            }
        }

        return null;
    }

    private function months(string $text): string
    {
        return preg_replace_callback('/\b(\d{1,2} )?(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember|Agu|Okt|Des)(?=( \d{4})|\b)/u', function ($m) {
            // Nama bulan saja, tanpa tanggal atau tahun, bisa berupa kata biasa: biarkan kamus yang menangani
            return ($m[1] ?? '') === '' && empty($m[3]) ? $m[0] : $m[1] . self::MONTHS[$m[2]];
        }, $text);
    }

    /** Menerjemahkan dokumen HTML utuh. Bila pemecahan gagal, halaman dikembalikan apa adanya. */
    public function html(string $html): string
    {
        $parts = preg_split('~(<!--.*?-->|<script\b.*?</script\s*>|<style\b.*?</style\s*>|<textarea\b.*?</textarea\s*>|<pre\b.*?</pre\s*>|<[a-zA-Z/!](?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $html;
        }
        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }
            if ($i % 2 === 0) {
                $parts[$i] = $this->htmlText($part);
            } elseif ($part[1] !== '/' && $part[1] !== '!' && stripos($part, '<script') !== 0 && stripos($part, '<style') !== 0 && stripos($part, '<textarea') !== 0 && stripos($part, '<pre') !== 0) {
                $parts[$i] = $this->tag($part);
            }
        }

        return implode('', $parts);
    }

    private function htmlText(string $raw): string
    {
        if (! preg_match('/\p{L}{2}/u', $raw)) {
            return $raw;
        }
        $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $translated = $this->text($decoded);

        return $translated === $decoded ? $raw : htmlspecialchars($translated, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    private function tag(string $tag): string
    {
        $tag = preg_replace_callback('/(?<![\w:@.-])(' . self::ATTRIBUTES . ')="([^"]*)"/i', function ($m) {
            $decoded = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $translated = $this->text($decoded);

            return $translated === $decoded ? $m[0] : $m[1] . '="' . htmlspecialchars($translated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) . '"';
        }, $tag) ?? $tag;
        // Tombol berupa input: teksnya ada di value
        if (preg_match('/^<input\b/i', $tag) && preg_match('/\stype="(submit|button|reset)"/i', $tag)) {
            $tag = preg_replace_callback('/(\svalue=")([^"]*)(")/i', function ($m) {
                $decoded = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $m[1] . htmlspecialchars($this->text($decoded), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false) . $m[3];
            }, $tag) ?? $tag;
        }

        return $tag;
    }

    /** Dokumen laporan, supaya Excel dan CSV ikut berbahasa Inggris. */
    public function report(array $doc): array
    {
        foreach (['title', 'subtitle', 'empty'] as $key) {
            if (isset($doc[$key]) && is_string($doc[$key])) {
                $doc[$key] = $this->text($doc[$key]);
            }
        }
        foreach ($doc['columns'] ?? [] as $i => $column) {
            $doc['columns'][$i]['label'] = $this->text((string) $column['label']);
        }
        foreach ($doc['rows'] ?? [] as $i => $row) {
            if (isset($row['label'])) {
                $doc['rows'][$i]['label'] = $this->text((string) $row['label']);
            }
            foreach ($row['cells'] ?? [] as $j => $cell) {
                if (is_string($cell) && $cell !== '') {
                    $doc['rows'][$i]['cells'][$j] = $this->text($cell);
                }
            }
        }
        if (isset($doc['side_by_side'])) {
            $doc['side_by_side']['title'] = $this->text($doc['side_by_side']['title']);
            $doc['side_by_side']['sheet'] = $this->text($doc['side_by_side']['sheet']);
            foreach ($doc['side_by_side']['columns'] as $i => $column) {
                $doc['side_by_side']['columns'][$i]['label'] = $this->text($column['label']);
            }
            foreach (['left', 'right'] as $side) {
                foreach ($doc['side_by_side'][$side] as $i => $row) {
                    if (isset($row['label'])) {
                        $doc['side_by_side'][$side][$i]['label'] = $this->text($row['label']);
                    }
                    if (isset($row['cells'][1])) {
                        $doc['side_by_side'][$side][$i]['cells'][1] = $this->text($row['cells'][1]);
                    }
                }
            }
        }
        foreach ($doc['summary'] ?? [] as $i => $item) {
            $doc['summary'][$i]['label'] = $this->text((string) $item['label']);
        }
        foreach ($doc['notes'] ?? [] as $i => $note) {
            if (is_array($note)) {
                $doc['notes'][$i]['text'] = $this->text((string) $note['text']);
            } else {
                $doc['notes'][$i] = $this->text((string) $note);
            }
        }
        foreach ($doc['filters'] ?? [] as $i => $filter) {
            $doc['filters'][$i]['label'] = $this->text((string) $filter['label']);
        }
        foreach ($doc['links'] ?? [] as $i => $link) {
            $doc['links'][$i]['label'] = $this->text((string) $link['label']);
        }

        return $doc;
    }
}
