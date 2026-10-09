<?php

namespace App\Services;

use DateTimeImmutable;

/**
 * Pembaca pertanyaan untuk asisten ketersediaan barang.
 * Murni PHP (tanpa database) supaya mudah diuji: tanggal/periode, jumlah,
 * pencocokan nama barang, dan penentuan maksud pertanyaan.
 */
class AssistantParser
{
    private const MONTH_NAMES = 'januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember|'
        .'jan|feb|mar|apr|jun|jul|agu|agt|ags|sept|sep|okt|nov|des';

    private const MONTHS = [
        'januari' => 1, 'jan' => 1, 'februari' => 2, 'feb' => 2, 'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4, 'mei' => 5, 'juni' => 6, 'jun' => 6, 'juli' => 7, 'jul' => 7,
        'agustus' => 8, 'agu' => 8, 'agt' => 8, 'ags' => 8, 'september' => 9, 'sept' => 9, 'sep' => 9,
        'oktober' => 10, 'okt' => 10, 'november' => 11, 'nov' => 11, 'desember' => 12, 'des' => 12,
    ];

    /** Kata umum pada nama barang yang tidak boleh dipakai untuk mencocokkan. */
    private const GENERIC_WORDS = ['meter', 'digital', 'unit', 'set', 'buah', 'pcs', 'paket', 'dan', 'yang'];

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return (string) preg_replace('/\s+/u', ' ', $text);
    }

    /**
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable, found: bool, quantity: int|null, rest: string}
     */
    public static function analyze(string $question, DateTimeImmutable $today): array
    {
        $text = self::normalize($question);
        $hits = []; // [posisi, tanggal]

        $take = function (string $pattern, callable $make) use (&$text, &$hits): void {
            if (! preg_match_all($pattern, $text, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                return;
            }
            foreach ($all as $m) {
                $date = $make($m);
                if ($date !== null) {
                    $hits[] = [$m[0][1], $date];
                }
                $text = substr_replace($text, str_repeat(' ', strlen($m[0][0])), $m[0][1], strlen($m[0][0]));
            }
        };

        // 2026-10-12
        $take('/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/', fn ($m) => self::make((int) $m[1][0], (int) $m[2][0], (int) $m[3][0]));

        // 12/10/2026, 12-10-2026, 12.10.26
        $take('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4}|\d{2})\b/', function ($m) {
            $year = (int) $m[3][0];
            if ($year < 100) {
                $year += 2000;
            }

            return self::make($year, (int) $m[2][0], (int) $m[1][0]);
        });

        // 12 oktober, 12 okt 2026
        $take('/\b(\d{1,2})\s*('.self::MONTH_NAMES.')\b\.?(?:\s*(\d{4})\b)?/', function ($m) use ($today) {
            $month = self::MONTHS[$m[2][0]] ?? null;
            if (! $month) {
                return null;
            }
            $day = (int) $m[1][0];

            if (isset($m[3]) && $m[3][0] !== '') {
                return self::make((int) $m[3][0], $month, $day);
            }

            $date = self::make((int) $today->format('Y'), $month, $day);
            if ($date !== null && $date < $today) {
                $date = self::make((int) $today->format('Y') + 1, $month, $day);
            }

            return $date;
        });

        // tanggal 12
        $take('/\btanggal\s+(\d{1,2})\b/', function ($m) use ($today) {
            $day = (int) $m[1][0];
            $year = (int) $today->format('Y');
            $month = (int) $today->format('n');

            $date = self::make($year, $month, $day);
            if ($date !== null && $date >= $today) {
                return $date;
            }

            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }

            return self::make($year, $month, $day);
        });

        // hari ini, besok, lusa, minggu depan
        $take('/\b(hari ini|besok|esok|lusa|minggu depan)\b/', function ($m) use ($today) {
            $offset = match ($m[1][0]) {
                'hari ini' => 0,
                'besok', 'esok' => 1,
                'lusa' => 2,
                default => 7,
            };

            return $today->modify("+{$offset} days");
        });

        usort($hits, fn ($a, $b) => $a[0] <=> $b[0]);
        $dates = array_map(fn ($h) => $h[1], $hits);

        // selama 3 hari
        $duration = null;
        $take('/\b(?:selama|untuk)\s+(\d{1,2})\s*hari\b/', function ($m) use (&$duration) {
            $duration = max(1, min(60, (int) $m[1][0]));

            return null;
        });

        $found = count($dates) > 0;

        if (! $found) {
            $start = $today;
            $end = $today->modify('+'.(($duration ?? 1) - 1).' days');
        } elseif (count($dates) === 1) {
            $start = $dates[0];
            $end = $duration ? $start->modify('+'.($duration - 1).' days') : $start;
        } else {
            $start = min($dates);
            $end = max($dates);
        }

        // Jumlah yang ingin dipinjam, mis. "pinjam 3 proyektor" atau "3 unit"
        $quantity = null;
        if (preg_match('/\b(?:pinjam|meminjam|butuh|perlu|mau|ingin|bawa)\s+(\d{1,3})\b/', $text, $q)
            || preg_match('/\b(\d{1,3})\s*(?:unit|buah|pcs|biji)\b/', $text, $q)) {
            $quantity = (int) $q[1];
        }

        return [
            'start' => $start,
            'end' => $end,
            'found' => $found,
            'quantity' => $quantity,
            'rest' => trim((string) preg_replace('/\s+/', ' ', $text)),
        ];
    }

    /**
     * Cocokkan pertanyaan dengan daftar barang.
     *
     * @param  array<int, array{id: int, name: string, code: string, category: string}>  $items
     * @return array<int, int> id barang yang cocok, skor tertinggi lebih dulu
     */
    public static function matchItems(string $rest, array $items): array
    {
        $qTokens = self::tokens($rest);
        $scores = [];

        foreach ($items as $item) {
            $score = 0;
            $code = strtolower($item['code']);
            $name = strtolower($item['name']);

            if ($code !== '' && str_contains($rest, $code)) {
                $score += 10;
            }
            if ($name !== '' && str_contains($rest, $name)) {
                $score += 8;
            }

            foreach (self::nameTokens($name) as $token) {
                if (self::tokenInQuestion($token, $qTokens)) {
                    $score += 2;
                }
            }

            if ($score > 0) {
                $scores[$item['id']] = $score;
            }
        }

        // Tidak ada nama barang yang cocok: coba nama kategori ("barang elektronik").
        if (! $scores) {
            foreach ($items as $item) {
                foreach (self::nameTokens(strtolower($item['category'])) as $token) {
                    if (self::tokenInQuestion($token, $qTokens)) {
                        $scores[$item['id']] = 1;
                        break;
                    }
                }
            }
        }

        if (! $scores) {
            return [];
        }

        $top = max($scores);
        $scores = array_filter($scores, fn ($s) => $s >= $top * 0.75);
        arsort($scores);

        return array_keys($scores);
    }

    /**
     * Tentukan maksud: help, procedure, availability, atau unknown.
     */
    public static function intent(string $rest, bool $hasItemMatch): string
    {
        $has = fn (array $words) => (bool) preg_match('/\b('.implode('|', $words).')/u', $rest);

        if ($has(['cara', 'prosedur', 'alur', 'langkah', 'syarat', 'ketentuan', 'aturan', 'bagaimana'])
            && $has(['pinjam', 'meminjam', 'peminjaman', 'ajukan', 'mengajukan', 'kembali', 'mengembalikan', 'pengembalian'])) {
            return 'procedure';
        }

        if ($hasItemMatch) {
            return 'availability';
        }

        if ($has(['tersedia', 'ketersediaan', 'stok', 'sisa', 'kosong', 'available', 'bisa dipinjam', 'bisa dipakai',
            'daftar', 'katalog', 'semua barang', 'apa saja', 'barang apa'])) {
            return 'availability';
        }

        if ($has(['halo', 'hai', 'hi', 'hello', 'selamat', 'permisi', 'bantuan', 'bantu', 'bisa apa', 'fitur', 'menu', 'tolong'])) {
            return 'help';
        }

        return 'unknown';
    }

    /** @return array<int, string> */
    private static function tokens(string $text): array
    {
        return preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** @return array<int, string> */
    private static function nameTokens(string $name): array
    {
        return array_values(array_filter(
            self::tokens($name),
            fn ($t) => strlen($t) >= 3 && ! ctype_digit($t) && ! in_array($t, self::GENERIC_WORDS, true)
        ));
    }

    /** @param  array<int, string>  $qTokens */
    private static function tokenInQuestion(string $token, array $qTokens): bool
    {
        foreach ($qTokens as $q) {
            if ($q === $token || (strlen($token) >= 4 && str_starts_with($q, $token))) {
                return true;
            }
        }

        return false;
    }

    private static function make(int $year, int $month, int $day): ?DateTimeImmutable
    {
        if ($year < 2000 || $year > 2100 || ! checkdate($month, $day, $year)) {
            return null;
        }

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
