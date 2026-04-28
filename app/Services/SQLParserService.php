<?php

namespace App\Services;

/**
 * SQLParserService
 *
 * Parser SQL yang robust untuk file dump phpMyAdmin / MySQL.
 *
 * Mampu menangani:
 *  - CREATE TABLE dengan atau tanpa backtick
 *  - INSERT INTO dengan named columns: INSERT INTO `t` (`a`,`b`) VALUES (...)
 *  - INSERT INTO tanpa named columns: INSERT INTO `t` VALUES (...)
 *  - Value berisi serialized PHP, JSON, base64, koma, tanda kurung, backslash
 *  - NULL, string kosong '', angka, desimal
 *  - Escaped quotes di dalam string: 'Andi\'s' atau 'Andi''s'
 *  - Komentar -- dan /* ... * / di dalam file
 *  - Constraint baris (PRIMARY KEY, UNIQUE KEY, FOREIGN KEY, KEY, INDEX)
 *  - ENGINE=..., CHARSET=... di akhir CREATE TABLE
 *  - Batas max 20 baris data per tabel
 */
class SQLParserService
{
    private const MAX_ROWS = 20;

    /**
     * Parse file SQL dan kembalikan struktur tabel beserta datanya.
     *
     * @param  string $filePath  Path ke file .sql
     * @return array{tables: list<array{name: string, columns: list<array{name: string, type: string}>, data: list<list<mixed>>}>}
     * @throws \Exception Jika file tidak ditemukan
     */
    public function parse(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $raw = file_get_contents($filePath);
        $sql = $this->stripComments($raw);

        $tables  = $this->parseCreateTables($sql);
        $inserts = $this->parseInserts($sql);

        // Gabungkan data INSERT ke tabel yang sesuai
        foreach ($inserts as $tableName => $rows) {
            foreach ($tables as &$table) {
                if (strtolower($table['name']) === strtolower($tableName)) {
                    $table['data'] = array_slice($rows, 0, self::MAX_ROWS);
                    break;
                }
            }
            unset($table);
        }

        // Pastikan setiap tabel punya key 'data'
        foreach ($tables as &$table) {
            if (!isset($table['data'])) {
                $table['data'] = [];
            }
        }
        unset($table);

        return ['tables' => $tables];
    }

    // -------------------------------------------------------------------------
    // STRIP COMMENTS
    // -------------------------------------------------------------------------

    /**
     * Hapus komentar SQL (-- ... dan /* ... * /) tanpa menyentuh isi string.
     */
    private function stripComments(string $sql): string
    {
        $result = '';
        $len    = strlen($sql);
        $i      = 0;

        while ($i < $len) {
            // Dalam string single-quote — lewati tanpa modifikasi
            if ($sql[$i] === "'") {
                $result .= $sql[$i++];
                while ($i < $len) {
                    if ($sql[$i] === '\\') {
                        // backslash escape: salin dua karakter
                        $result .= $sql[$i] . ($sql[$i + 1] ?? '');
                        $i += 2;
                    } elseif ($sql[$i] === "'" && isset($sql[$i + 1]) && $sql[$i + 1] === "'") {
                        // doubled-quote escape ''
                        $result .= "''";
                        $i += 2;
                    } elseif ($sql[$i] === "'") {
                        $result .= $sql[$i++];
                        break;
                    } else {
                        $result .= $sql[$i++];
                    }
                }
                continue;
            }

            // Komentar baris --
            if ($i + 1 < $len && $sql[$i] === '-' && $sql[$i + 1] === '-') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                $result .= "\n";
                continue;
            }

            // Komentar blok /* */
            if ($i + 1 < $len && $sql[$i] === '/' && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2; // lewati */
                $result .= ' ';
                continue;
            }

            $result .= $sql[$i++];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // PARSE CREATE TABLE
    // -------------------------------------------------------------------------

    private function parseCreateTables(string $sql): array
    {
        $tables = [];

        // Temukan semua blok CREATE TABLE ... ;
        // Gunakan regex multiline, non-greedy, tangkap isi dalam kurung luar
        $pattern = '/CREATE\s+TABLE\s+`?(\w+)`?\s*\((.+?)\)\s*(?:ENGINE\s*=|DEFAULT\s+CHARSET|;)/si';

        if (!preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            return $tables;
        }

        foreach ($matches as $match) {
            $tableName   = $match[1];
            $columnBlock = $match[2];
            $columns     = $this->parseColumnDefinitions($columnBlock);

            $tables[] = [
                'name'    => $tableName,
                'columns' => $columns,
                'data'    => [],
            ];
        }

        return $tables;
    }

    /**
     * Parse baris-baris definisi kolom dari dalam CREATE TABLE (...).
     * Skip baris constraint: PRIMARY KEY, UNIQUE KEY, FOREIGN KEY, KEY, INDEX.
     */
    private function parseColumnDefinitions(string $block): array
    {
        $columns = [];

        // Pisah per baris definisi kolom (pisah berdasarkan koma di level teratas)
        $lines = $this->splitTopLevelComma($block);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Skip constraint lines
            if (preg_match('/^\s*(PRIMARY\s+KEY|UNIQUE\s+KEY|UNIQUE\s+INDEX|FOREIGN\s+KEY|KEY\s+|INDEX\s+|CONSTRAINT\s+)/i', $line)) {
                continue;
            }

            // Ambil nama kolom (dengan atau tanpa backtick)
            if (!preg_match('/^`?(\w+)`?\s+(\w+)/i', $line, $colMatch)) {
                continue;
            }

            $columns[] = [
                'name' => $colMatch[1],
                'type' => strtoupper($colMatch[2]),
            ];
        }

        return $columns;
    }

    // -------------------------------------------------------------------------
    // PARSE INSERT
    // -------------------------------------------------------------------------

    /**
     * Parse semua INSERT INTO dari SQL.
     *
     * Menangani dua format:
     *   INSERT INTO `tbl` VALUES (v1, v2), (v3, v4);
     *   INSERT INTO `tbl` (`col1`, `col2`) VALUES (v1, v2), (v3, v4);
     *
     * @return array<string, list<list<mixed>>>  keyed by table name
     */
    private function parseInserts(string $sql): array
    {
        $result = [];

        // Pattern: INSERT INTO `tbl` [optional column list] VALUES ...;
        // Kita cari posisi masing-masing INSERT agar bisa extract VALUES block yang besar
        $pattern = '/INSERT\s+INTO\s+`?(\w+)`?\s*(?:\([^)]*\)\s*)?VALUES\s*/si';

        $offset = 0;
        while (preg_match($pattern, $sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $tableName  = $m[1][0];
            $valuesStart = $m[0][1] + strlen($m[0][0]); // posisi setelah "VALUES "

            // Extract VALUES block hingga ; di level teratas
            $valuesBlock = $this->extractUntilSemicolon($sql, $valuesStart);

            $rows = $this->parseValuesBlock($valuesBlock);

            if (!isset($result[$tableName])) {
                $result[$tableName] = [];
            }
            foreach ($rows as $row) {
                $result[$tableName][] = $row;
            }

            $offset = $valuesStart + strlen($valuesBlock) + 1;
        }

        return $result;
    }

    /**
     * Extract teks dari posisi $start hingga `;` yang berada di level teratas
     * (tidak di dalam string atau kurung).
     */
    private function extractUntilSemicolon(string $sql, int $start): string
    {
        $len    = strlen($sql);
        $i      = $start;
        $depth  = 0;
        $result = '';

        while ($i < $len) {
            $ch = $sql[$i];

            if ($ch === "'" ) {
                // Masuk ke string — salin sampai quote penutup
                $strResult = $ch;
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === '\\') {
                        $strResult .= $sql[$i] . ($sql[$i + 1] ?? '');
                        $i += 2;
                    } elseif ($sql[$i] === "'" && isset($sql[$i + 1]) && $sql[$i + 1] === "'") {
                        $strResult .= "''";
                        $i += 2;
                    } elseif ($sql[$i] === "'") {
                        $strResult .= $sql[$i++];
                        break;
                    } else {
                        $strResult .= $sql[$i++];
                    }
                }
                $result .= $strResult;
                continue;
            }

            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            } elseif ($ch === ';' && $depth === 0) {
                break; // selesai
            }

            $result .= $ch;
            $i++;
        }

        return $result;
    }

    /**
     * Parse VALUES block: `(v1, v2), (v3, v4), ...`
     * Kembalikan array of rows, masing-masing row adalah array of values.
     */
    private function parseValuesBlock(string $block): array
    {
        $rows  = [];
        $len   = strlen($block);
        $i     = 0;

        while ($i < $len) {
            // Cari '(' awal row
            while ($i < $len && $block[$i] !== '(') {
                $i++;
            }
            if ($i >= $len) {
                break;
            }

            $i++; // lewati '('

            // Extract isi row hingga ')' level teratas
            $rowContent = '';
            $depth      = 0;

            while ($i < $len) {
                $ch = $block[$i];

                if ($ch === "'") {
                    // string literal
                    $strContent = $ch;
                    $i++;
                    while ($i < $len) {
                        if ($block[$i] === '\\') {
                            $strContent .= $block[$i] . ($block[$i + 1] ?? '');
                            $i += 2;
                        } elseif ($block[$i] === "'" && isset($block[$i + 1]) && $block[$i + 1] === "'") {
                            $strContent .= "''";
                            $i += 2;
                        } elseif ($block[$i] === "'") {
                            $strContent .= $block[$i++];
                            break;
                        } else {
                            $strContent .= $block[$i++];
                        }
                    }
                    $rowContent .= $strContent;
                    continue;
                }

                if ($ch === '(') {
                    $depth++;
                    $rowContent .= $ch;
                    $i++;
                    continue;
                }

                if ($ch === ')') {
                    if ($depth === 0) {
                        $i++; // lewati ')' penutup row
                        break;
                    }
                    $depth--;
                    $rowContent .= $ch;
                    $i++;
                    continue;
                }

                $rowContent .= $ch;
                $i++;
            }

            $rows[] = $this->parseRowValues($rowContent);
        }

        return $rows;
    }

    /**
     * Parse nilai-nilai di dalam satu row: `v1, 'str', NULL, 3.14`
     * Menggunakan character-level tokenizer agar koma di dalam string tidak dihitung.
     *
     * @return list<mixed>
     */
    private function parseRowValues(string $rowContent): array
    {
        $values = [];
        $len    = $i = 0;
        $len    = strlen($rowContent);
        $token  = '';
        $depth  = 0;

        while ($i < $len) {
            $ch = $rowContent[$i];

            if ($ch === "'") {
                // String literal — tangkap seluruhnya termasuk quote
                $str = $ch;
                $i++;
                while ($i < $len) {
                    if ($rowContent[$i] === '\\') {
                        $str .= $rowContent[$i] . ($rowContent[$i + 1] ?? '');
                        $i += 2;
                    } elseif ($rowContent[$i] === "'" && isset($rowContent[$i + 1]) && $rowContent[$i + 1] === "'") {
                        $str .= "''";
                        $i += 2;
                    } elseif ($rowContent[$i] === "'") {
                        $str .= $rowContent[$i++];
                        break;
                    } else {
                        $str .= $rowContent[$i++];
                    }
                }
                $token .= $str;
                continue;
            }

            // Track kedalaman kurung (untuk sub-expressions)
            if ($ch === '(') {
                $depth++;
                $token .= $ch;
                $i++;
                continue;
            }
            if ($ch === ')') {
                $depth--;
                $token .= $ch;
                $i++;
                continue;
            }

            // Koma sebagai pemisah nilai — hanya di level 0
            if ($ch === ',' && $depth === 0) {
                $values[] = $this->parseScalar(trim($token));
                $token = '';
                $i++;
                continue;
            }

            $token .= $ch;
            $i++;
        }

        // Token terakhir
        if (trim($token) !== '') {
            $values[] = $this->parseScalar(trim($token));
        }

        return $values;
    }

    /**
     * Konversi token mentah ke nilai PHP yang tepat.
     *   NULL        → null
     *   '...'       → string (unescape)
     *   123, 3.14   → string numerik (sesuai test case)
     */
    private function parseScalar(string $token): mixed
    {
        if (strtoupper($token) === 'NULL') {
            return null;
        }

        // String dalam single-quote
        if (strlen($token) >= 2 && $token[0] === "'" && $token[-1] === "'") {
            $inner = substr($token, 1, -1);
            // Unescape \' dan ''
            $inner = str_replace("\\'", "'", $inner);
            $inner = str_replace("''", "'", $inner);
            // Unescape backslash lainnya yang umum di MySQL dump
            $inner = str_replace('\\\\', '\\', $inner);
            $inner = str_replace('\\n', "\n", $inner);
            $inner = str_replace('\\r', "\r", $inner);
            $inner = str_replace('\\"', '"', $inner);
            return $inner;
        }

        // Angka — kembalikan sebagai string (sesuai test_parse_insert_various_types)
        return $token;
    }

    // -------------------------------------------------------------------------
    // HELPER: Split berdasarkan koma di level teratas (tidak di dalam kurung/string)
    // -------------------------------------------------------------------------

    /**
     * Split string berdasarkan koma, tapi hanya yang berada di level teratas
     * (tidak di dalam tanda kurung atau string).
     *
     * @return list<string>
     */
    private function splitTopLevelComma(string $input): array
    {
        $parts  = [];
        $len    = strlen($input);
        $i      = 0;
        $depth  = 0;
        $current = '';

        while ($i < $len) {
            $ch = $input[$i];

            if ($ch === "'") {
                $current .= $ch;
                $i++;
                while ($i < $len) {
                    if ($input[$i] === '\\') {
                        $current .= $input[$i] . ($input[$i + 1] ?? '');
                        $i += 2;
                    } elseif ($input[$i] === "'") {
                        $current .= $input[$i++];
                        break;
                    } else {
                        $current .= $input[$i++];
                    }
                }
                continue;
            }

            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            } elseif ($ch === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                $i++;
                continue;
            }

            $current .= $ch;
            $i++;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }
}