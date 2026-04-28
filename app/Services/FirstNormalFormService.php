<?php

namespace App\Services;

/**
 * FirstNormalFormService - Deteksi pelanggaran 1NF dan generate rekomendasi normalisasi
 * 
 * PRE-PROCESSOR untuk Algoritma Demba. Deteksi 4 jenis pelanggaran 1NF:
 * 1. Repeating Groups - kolom dengan pola angka di akhir (telp1, telp2, email_1, email_2)
 * 2. Composite Columns - kolom yang seharusnya dipecah (nama_lengkap, alamat_lengkap)
 * 3. Multi-Value Attributes - nilai terpisah koma/semicolon dalam satu kolom
 * 4. Potential Multi-Value - dari naming patterns (hobi, skill, tag, kategori)
 * 
 * @author Sistem Rekomendasi 1NF - Algoritma Demba
 */
class FirstNormalFormService
{
    // Keywords untuk deteksi composite columns
    private const COMPOSITE_KEYWORDS = [
        'lengkap',
        'full',
        'complete',
        'alamat',
        'address',
        // 'nama',
        'nama_depan',
        'nama_belakang',
        'first_name',
        'last_name',
        'nama_depan_belakang',
    ];

    // ⭐ EXCEPTION: Kolom yang TIDAK boleh dianggap composite
    private const COMPOSITE_EXCEPTIONS = [
        'ip_address',
        'ip',
        'ip_addr',
        'user_agent',
        'useragent',
        'ua',
        'email',
        'email_address',
        'phone',
        'telp',
        'telepon',
        'phone_number',
        'url',
        'website',
        'link',
        'uuid',
        'guid',
        'token',
        'api_key',
        'api_token',
        'hash',
        'password_hash',
        'filename',
        'file_path',
        'path',
        'coordinates',
        'location_point',
    ];

    // ⭐ EXCEPTION: Kolom dengan format khusus yang TIDAK multi-value
    private const MULTIVALUE_EXCEPTIONS = [
        'user_agent',
        'useragent',
        'ua',
        'ip_address',
        'ip',
        'email',
        'email_address',
        'url',
        'website',
        'path',
        'file_path',
        'coordinates',
        'geolocation',
        'signature',
        'fingerprint',
        'stack_trace',
        'error_trace',
        'headers',
        'http_headers',
    ];

    // ⭐ Pattern yang menandakan multi-value PALSU (false positive)
    private const FALSE_MULTIVALUE_PATTERNS = [
        '/^[a-zA-Z0-9\.\_\-]+\/[0-9\.]+/',      // User-Agent: Mozilla/5.0
        '/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/',   // IP Address
        '/^[a-zA-Z0-9\.\_\%\+\-]+@[a-zA-Z0-9\.\-]+\.[a-zA-Z]{2,}$/', // Email
        '/^https?:\/\//',                        // URL
        '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', // UUID
    ];

    // Separators untuk deteksi multi-value dalam data
    private const MULTIVALUE_SEPARATORS = [',', ';', '|', "\n", "\r\n"];

    /**
     * Analyze parsed data dan deteksi pelanggaran 1NF
     * 
     * @param array $parsedData Result dari SQLParserService::parse()
     * @return array Array with structure ['tables' => [...], 'summary' => [...]]
     */
    public function analyze(array $parsedData): array
    {
        $analyzedTables = [];
        $totalTables = 0;
        $tablesWithViolations = 0;
        $totalViolations = 0;

        foreach ($parsedData['tables'] ?? [] as $table) {
            $tableName = $table['name'];
            $columns = $table['columns'];
            $data = $table['data'] ?? [];

            $totalTables++;

            // Deteksi semua jenis violation
            $repeatingGroups = $this->detectRepeatingGroups($columns);
            $compositeColumns = $this->detectCompositeColumns($columns);
            $multiValueFromData = $this->detectMultiValueFromData($columns, $data);
            $potentialMultiValue = $this->detectPotentialMultiValue($columns, !empty($data));

            // Compile violations
            $violations = [];
            if (!empty($repeatingGroups)) {
                $violations[] = [
                    'type' => 'repeating_groups',
                    'description' => 'Repeating Groups',
                    'columns' => $repeatingGroups,
                    'severity' => 'high',
                ];
            }

            if (!empty($compositeColumns)) {
                $violations[] = [
                    'type' => 'composite_columns',
                    'description' => 'Composite Columns',
                    'columns' => $compositeColumns,
                    'severity' => 'high',
                ];
            }

            if (!empty($multiValueFromData)) {
                $violations[] = [
                    'type' => 'multi_value_data',
                    'description' => 'Multi-Value Attributes (dari data)',
                    'columns' => $multiValueFromData,
                    'severity' => 'high',
                ];
            }

            if (!empty($potentialMultiValue)) {
                $violations[] = [
                    'type' => 'potential_multi_value',
                    'description' => 'Potential Multi-Value (dari naming)',
                    'columns' => $potentialMultiValue,
                    'severity' => 'medium',
                    'note' => 'Berdasarkan nama kolom. Verifikasi dengan data actual untuk memastikan.',
                ];
            }

            $hasViolations = !empty($violations);
            if ($hasViolations) {
                $tablesWithViolations++;
                $totalViolations += count($violations);
            }

            // Generate recommendations jika ada violations
            $recommendations = [];
            if ($hasViolations) {
                $recommendations = $this->generateRecommendations($tableName, $columns, $violations, $data);
            }

            $violationCount = count($violations);
            $analyzedTables[] = [
                'name' => $tableName,
                'columns' => $columns,
                'has_violations' => $hasViolations,
                'violations' => $violations,
                'violation_count' => $violationCount,
                'recommendations' => $recommendations,
                'status' => $hasViolations ? 'not_normalized' : 'normalized',
                'message' => $hasViolations
                    ? "Skema ini TIDAK memenuhi 1NF. Ditemukan {$violationCount} jenis pelanggaran."
                    : "Skema ini sudah ternormalisasi 1NF ✅",
            ];
        }

        return [
            'tables' => $analyzedTables,
            'summary' => [
                'total_tables' => $totalTables,
                'tables_with_violations' => $tablesWithViolations,
                'tables_normalized' => $totalTables - $tablesWithViolations,
                'total_violation_types' => $totalViolations,
                'analysis_timestamp' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Deteksi Repeating Groups
     * Kolom dengan pola: telp1, telp2, telp3 atau email_1, email_2
     * 
     * @param array $columns
     * @return array
     */
    private function detectRepeatingGroups(array $columns): array
    {
        $groups = [];
        $patterns = [];

        foreach ($columns as $column) {
            $colName = $column['name'];

            // Pattern: underscore + digit at end (_1, _2, _3)
            if (preg_match('/^(.+?)_(\d+)$/', $colName, $matches)) {
                $basePattern = $matches[1];
                $patterns[$basePattern][] = $colName;
            }
            // Pattern: digit at end without underscore (telp1, telp2, telp3)
            elseif (preg_match('/^([a-zA-Z_]+?)(\d+)$/', $colName, $matches)) {
                $basePattern = $matches[1];
                $patterns[$basePattern][] = $colName;
            }
        }

        // Filter patterns yang actual repeating groups (2+ columns dengan base sama)
        foreach ($patterns as $basePattern => $cols) {
            if (count($cols) >= 2) {
                $groups[] = [
                    'base_pattern' => $basePattern,
                    'columns' => $cols,
                    'count' => count($cols),
                    'suggestion' => "Pisahkan ke table terpisah atau gunakan junction table untuk '$basePattern'",
                ];
            }
        }

        return $groups;
    }

    /**
     * Deteksi Composite Columns dengan exception handling
     * Kolom yang seharusnya dipecah: nama_lengkap, alamat_lengkap, dll
     * 
     * @param array $columns
     * @return array
     */
    private function detectCompositeColumns(array $columns): array
    {
        $composites = [];

        foreach ($columns as $column) {
            $colName = strtolower($column['name']);

            // ⭐ LEWATKAN jika termasuk exception
            if (in_array($colName, self::COMPOSITE_EXCEPTIONS)) {
                continue;
            }

            // Check untuk pattern exception (wildcard)
            $isException = false;
            foreach (self::COMPOSITE_EXCEPTIONS as $exception) {
                if (str_contains($colName, $exception)) {
                    $isException = true;
                    break;
                }
            }
            if ($isException) {
                continue;
            }

            // Check untuk composite keywords
            foreach (self::COMPOSITE_KEYWORDS as $keyword) {
                if (strpos($colName, $keyword) !== false) {
                    if ($this->isActuallyComposite($colName, $keyword)) {
                        $composites[] = [
                            'column' => $column['name'],
                            'detected_keyword' => $keyword,
                            'suggestion' => $this->getCompositeDecompositionSuggestion($column['name']),
                        ];
                        break;
                    }
                }
            }
        }

        return $composites;
    }

    /**
     * Check apakah composite column detection adalah true positive
     * 
     * @param string $colName
     * @param string $keyword
     * @return bool
     */
    private function isActuallyComposite(string $colName, string $keyword): bool
    {
        // Atomic columns yang tidak perlu dipecah
        $atomicColumns = [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            'password_hash',
            'ip_address',
            'ip',
            'email',
            'phone',
            'telp',
            'nama_depan',
            'nama_belakang',
            'first_name',
            'last_name',
        ];

        if (in_array($colName, $atomicColumns)) {
            return false;
        }

        // Jika kolom adalah IP address pattern
        if (preg_match('/^ip_?/', $colName)) {
            return false;
        }

        if ($keyword === 'nama_depan_belakang') {
            return true;
        }


        // Jika kolom adalah user_agent
        if (str_contains($colName, 'user_agent') || $colName === 'useragent') {
            return false;
        }

        // Jika kolom mengandung 'address' tapi bukan alamat fisik
        if ($keyword === 'address' && (str_contains($colName, 'ip') || str_contains($colName, 'email'))) {
            return false;
        }

        // Check untuk composite indicators
        if ($keyword === 'lengkap' || $keyword === 'full' || $keyword === 'complete') {
            return true;
        }

        if ($keyword === 'alamat' || $keyword === 'address') {
            // Jangan anggap 'ip_address' sebagai composite
            if (str_contains($colName, 'ip_')) {
                return false;
            }
            // Kecualikan 'email_address' karena sudah atomic
            if (str_contains($colName, 'email')) {
                return false;
            }
            return !in_array($colName, ['alamat', 'address']);
        }

        if ($keyword === 'nama') {
            return strpos($colName, '_') !== false &&
                !in_array($colName, ['nama', 'name']);
        }

        return false;
    }

    /**
     * Generate saran dekomposisi untuk composite column
     * 
     * @param string $columnName
     * @return string
     */
    private function getCompositeDecompositionSuggestion(string $columnName): string
    {
        $lowerName = strtolower($columnName);

        // ⭐ Kembalikan warning jika ini kolom yang sebaiknya tidak dipecah
        if (str_contains($lowerName, 'ip_')) {
            return "⚠️ IP ADDRESS adalah nilai atomik, TIDAK PERLU dipecah. Lewatkan kolom ini.";
        }

        if (str_contains($lowerName, 'email')) {
            return "⚠️ EMAIL adalah nilai atomik, TIDAK PERLU dipecah. Lewatkan kolom ini.";
        }

        if (str_contains($lowerName, 'user_agent')) {
            return "⚠️ USER AGENT adalah string tunggal format standar, TIDAK PERLU dipecah.";
        }

        $suggestions = [
            'alamat_lengkap' => 'Pisahkan ke: jalan, kelurahan, kecamatan, kota, provinsi, kode_pos',
            'full_address' => 'Split into: street, village, district, city, province, postal_code',
            'nama_lengkap' => 'Pisahkan ke: nama_depan, nama_belakang',
            'full_name' => 'Split into: first_name, last_name',
            'buyer_address' => 'Pisahkan ke: jalan, kelurahan, kota, provinsi, kode_pos (opsional, tergantung kebutuhan aplikasi)',
            'alamat' => 'Pisahkan ke: jalan, kelurahan, kecamatan, kota, provinsi, kode_pos',
            'address' => 'Split into: street, village, district, city, province, postal_code',
        ];

        foreach ($suggestions as $pattern => $suggestion) {
            if (strpos($lowerName, $pattern) !== false || $lowerName === $pattern) {
                return $suggestion;
            }
        }

        return "Pisahkan jika memang multiple attributes, namun verifikasi terlebih dahulu apakah kolom '{$columnName}' memang seharusnya dipecah.";
    }

    /**
     * Deteksi Multi-Value dari sample data dengan filter false positive
     * Cek apakah dalam satu kolom ada nilai terpisah koma/semicolon/pipe
     * 
     * @param array $columns
     * @param array $data
     * @return array
     */
    private function detectMultiValueFromData(array $columns, array $data): array
    {
        $multiValues = [];

        // Jika tidak ada sample data, tidak bisa deteksi
        if (empty($data)) {
            return $multiValues;
        }

        // Map column index to column name
        $columnNames = array_map(fn($col) => $col['name'], $columns);

        foreach ($columnNames as $colIndex => $colName) {
            // ⭐ LEWATKAN jika termasuk exception
            $lowerColName = strtolower($colName);
            if (in_array($lowerColName, self::MULTIVALUE_EXCEPTIONS)) {
                continue;
            }

            // Check pattern exception (wildcard)
            $isException = false;
            foreach (self::MULTIVALUE_EXCEPTIONS as $exception) {
                if (str_contains($lowerColName, $exception)) {
                    $isException = true;
                    break;
                }
            }
            if ($isException) {
                continue;
            }

            $hasMultiValue = false;
            $samplesWithMultiValue = [];

            foreach ($data as $rowData) {
                if (!isset($rowData[$colIndex])) {
                    continue;
                }

                $value = (string) $rowData[$colIndex];

                // Skip null/empty
                if ($value === null || $value === '' || $value === '0') {
                    continue;
                }

                // ⭐ CEK FALSE POSITIVE PATTERNS terlebih dahulu
                $isFalsePositive = false;
                foreach (self::FALSE_MULTIVALUE_PATTERNS as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $isFalsePositive = true;
                        break;
                    }
                }

                if ($isFalsePositive) {
                    continue; // Skip, ini bukan multi-value sebenarnya
                }

                // Check untuk separators
                foreach (self::MULTIVALUE_SEPARATORS as $separator) {
                    if (strpos($value, $separator) !== false) {
                        $parts = explode($separator, $value);

                        // Minimal 2 parts, dan ada keyakinan ini benar multi-value
                        if (count($parts) >= 2 && $this->isLikelyRealMultiValue($parts, $colName)) {
                            $hasMultiValue = true;
                            if (count($samplesWithMultiValue) < 3) {
                                $samplesWithMultiValue[] = [
                                    'value' => strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value,
                                    'separator' => $separator,
                                    'parts_count' => count($parts),
                                ];
                            }
                            break;
                        }
                    }
                }

                if ($hasMultiValue) {
                    break;
                }
            }

            if ($hasMultiValue) {
                $multiValues[] = [
                    'column' => $colName,
                    'samples' => $samplesWithMultiValue,
                    'suggestion' => "Pindahkan ke junction table atau normalize ke atomic values",
                ];
            }
        }

        return $multiValues;
    }

    /**
     * ⭐ Cek apakah ini benar-benar multi-value atau false positive
     * 
     * @param array $parts
     * @param string $columnName
     * @return bool
     */
    private function isLikelyRealMultiValue(array $parts, string $columnName): bool
    {
        // Jika hanya 2 parts dan salah satu adalah nomor/chars pendek, mungkin bukan multi-value
        if (count($parts) === 2) {
            $part1 = trim($parts[0]);
            $part2 = trim($parts[1]);

            // Case: "value: description" (bukan multi-value, tapi format key-value)
            if (strlen($part1) > 20 && strlen($part2) > 10 && strpos($part2, ' ') !== false) {
                return false;
            }

            // Case: "key=value" format (bukan multi-value)
            if (strpos($part1, '=') !== false || strpos($part2, '=') !== false) {
                return false;
            }

            // Case: format "value1 value2" dengan spasi (bukan separator koma/semicolon)
            if ($parts[0] !== $part1 && strpos($parts[0], ' ') === false && strpos($parts[1], ' ') === false) {
                // Mungkin dua kata pendek, bukan multi-value
                if (strlen($part1) < 20 && strlen($part2) < 20) {
                    return true; // Bisa jadi multi-value seperti "Merah,Biru"
                }
            }
        }

        // Jika nama kolom mengandung kata yang biasanya single value
        $singleValueIndicators = ['description', 'note', 'remarks', 'comment', 'detail', 'message'];
        foreach ($singleValueIndicators as $indicator) {
            if (strpos(strtolower($columnName), $indicator) !== false) {
                // Teks panjang dengan separator mungkin adalah kalimat, bukan multi-value
                if (strpos($parts[0], ' ') !== false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Deteksi Potential Multi-Value dari naming patterns
     * Jika column name menunjukkan multi-value (hobi, skill, tag, dll) tapi tidak ada sample data
     * 
     * @param array $columns
     * @param bool $hasSampleData
     * @return array
     */
    /**
     * Deteksi Potential Multi-Value dari naming patterns
     * 
     * @param array $columns
     * @param bool $hasSampleData
     * @param string $tableName
     * @return array
     */
    private function detectPotentialMultiValue(array $columns, bool $hasSampleData, string $tableName = ''): array
    {
        $potentials = [];

        $multiValueKeywords = ['hobi', 'skill', 'tag', 'kategori', 'category', 'interest', 'hobby'];

        foreach ($columns as $column) {
            $colName = strtolower($column['name']);
            $originalName = $column['name'];

            // ⭐⭐ LEWATKAN KOLOM FOREIGN KEY (BERAKHIRAN _id)
            if (preg_match('/_id$/', $colName)) {
                continue;  // ← INI YANG PALING PENTING!
            }

            // Lewatkan primary key
            if ($colName === 'id') {
                continue;
            }

            // Lewatkan kolom dengan prefix 'nama_' (atomic)
            if (preg_match('/^nama_/', $colName)) {
                continue;
            }

            // Lewatkan jika kolom sama dengan nama tabel
            if (!empty($tableName) && $colName === strtolower($tableName)) {
                continue;
            }

            // Lewatkan jika termasuk exception
            if (in_array($colName, self::MULTIVALUE_EXCEPTIONS)) {
                continue;
            }

            // Cek apakah sudah terdeteksi sebagai composite
            $isComposite = false;
            foreach (self::COMPOSITE_KEYWORDS as $keyword) {
                if (strpos($colName, $keyword) !== false) {
                    $isComposite = true;
                    break;
                }
            }

            if ($isComposite) {
                continue;
            }

            // Cek potential multi-value keywords
            foreach ($multiValueKeywords as $keyword) {
                if (strpos($colName, $keyword) !== false) {
                    $potentials[] = [
                        'column' => $originalName,
                        'detected_keyword' => $keyword,
                        'has_sample_data' => $hasSampleData,
                        'suggestion' => "Verifikasi apakah ini multi-value attribute. Jika ya, normalize ke junction table.",
                        'note' => !$hasSampleData ? 'Perlu data sample untuk memastikan.' : '',
                    ];
                    break;
                }
            }
        }

        return $potentials;
    }

    /**
     * Generate recommendations untuk normalisasi
     * 
     * @param string $tableName
     * @param array $columns
     * @param array $violations
     * @param array $sampleData
     * @return array
     */
    private function generateRecommendations(string $tableName, array $columns, array $violations, array $sampleData): array
    {
        $sqlScripts = [];
        $normalizationSteps = [];
        $stepNum = 1;

        foreach ($violations as $violation) {
            if ($violation['type'] === 'repeating_groups') {
                $normalizationSteps[] = $stepNum . ". Repeating Groups: Buat junction table untuk setiap group repeating columns";
                $sqlScripts = array_merge($sqlScripts, $this->generateRepeatingGroupSQL($tableName, $violation['columns']));
                $stepNum++;
            } elseif ($violation['type'] === 'composite_columns') {
                $normalizationSteps[] = $stepNum . ". Composite Columns: Decompose composite columns ke atomic columns terpisah";
                $sqlScripts = array_merge($sqlScripts, $this->generateCompositeColumnSQL($tableName, $violation['columns']));
                $stepNum++;
            } elseif ($violation['type'] === 'multi_value_data') {
                $normalizationSteps[] = $stepNum . ". Multi-Value Attributes: Buat junction table dan split nilai berdasarkan separator";
                $sqlScripts = array_merge($sqlScripts, $this->generateMultiValueSQL($tableName, $violation['columns']));
                $stepNum++;
            } elseif ($violation['type'] === 'potential_multi_value') {
                $normalizationSteps[] = $stepNum . ". Potential Multi-Value: Verifikasi dan normalize jika terbukti multi-value";
                $sqlScripts = array_merge($sqlScripts, $this->generatePotentialMultiValueSQL($tableName, $violation['columns']));
                $stepNum++;
            }
        }

        return [
            'normalization_steps' => $normalizationSteps,
            'sql_scripts' => $sqlScripts,
            'notes' => [
                'Pastikan untuk backup data sebelum melakukan migration ke 1NF',
                'Test normalization di environment staging terlebih dahulu',
                'Update application queries yang mengakses kolom-kolom yang di-normalize',
            ],
        ];
    }

    /**
     * Generate SQL untuk normalisasi Repeating Groups
     * 
     * @param string $tableName
     * @param array $groups
     * @return array
     */
    private function generateRepeatingGroupSQL(string $tableName, array $groups): array
    {
        $scripts = [];

        foreach ($groups as $group) {
            $basePattern = $group['base_pattern'];
            $newTableName = "{$tableName}_{$basePattern}";

            $sql = "-- Normalisasi Repeating Groups: {$basePattern}\n";
            $sql .= "-- Buat junction table untuk menggantikan repeating columns\n\n";
            $sql .= "CREATE TABLE `{$newTableName}` (\n";
            $sql .= "    `id` INT PRIMARY KEY AUTO_INCREMENT,\n";
            $sql .= "    `{$tableName}_id` INT NOT NULL,\n";
            $sql .= "    `{$basePattern}` VARCHAR(255),\n";
            $sql .= "    FOREIGN KEY (`{$tableName}_id`) REFERENCES `{$tableName}`(`id`) ON DELETE CASCADE\n";
            $sql .= ");\n\n";

            $sql .= "-- Migrate data dari repeating columns ke junction table\n";
            $columnsList = implode("', '", $group['columns']);
            $sql .= "-- TODO: INSERT INTO `{$newTableName}` ({$tableName}_id, {$basePattern})\n";
            $sql .= "-- SELECT id, {$columnsList} FROM `{$tableName}` WHERE ...\n\n";

            $sql .= "-- Hapus repeating columns dari table original\n";
            $sql .= "ALTER TABLE `{$tableName}` DROP COLUMN `" . implode("`, DROP COLUMN `", $group['columns']) . "`;\n\n";

            $scripts[] = $sql;
        }

        return $scripts;
    }

    /**
     * Generate SQL untuk normalisasi Composite Columns
     * 
     * @param string $tableName
     * @param array $composites
     * @return array
     */
    private function generateCompositeColumnSQL(string $tableName, array $composites): array
    {
        $scripts = [];

        foreach ($composites as $composite) {
            $originalColumn = $composite['column'];
            $suggestion = $composite['suggestion'];

            // ⭐ Jika suggestion mengandung "TIDAK PERLU", berikan warning saja
            if (strpos($suggestion, 'TIDAK PERLU') !== false || strpos($suggestion, '⚠️') !== false) {
                $sql = "-- ⚠️ SKIP: {$originalColumn}\n";
                $sql .= "-- {$suggestion}\n";
                $sql .= "-- Tidak perlu dilakukan normalisasi untuk kolom ini.\n\n";
                $scripts[] = $sql;
                continue;
            }

            $sql = "-- Normalisasi Composite Column: {$originalColumn}\n";
            $sql .= "-- Saran: {$suggestion}\n\n";

            if (strpos(strtolower($originalColumn), 'nama_lengkap') !== false || strpos(strtolower($originalColumn), 'full_name') !== false) {
                $sql .= "ALTER TABLE `{$tableName}` ADD COLUMN `nama_depan` VARCHAR(100) AFTER `{$originalColumn}`;\n";
                $sql .= "ALTER TABLE `{$tableName}` ADD COLUMN `nama_belakang` VARCHAR(100) AFTER `nama_depan`;\n";
                $sql .= "-- TODO: Migrate data dari {$originalColumn} menggunakan string split logic\n";
                $sql .= "-- UPDATE `{$tableName}` SET \n";
                $sql .= "--     nama_depan = SUBSTRING_INDEX({$originalColumn}, ' ', 1),\n";
                $sql .= "--     nama_belakang = SUBSTRING_INDEX({$originalColumn}, ' ', -1);\n";
                $sql .= "-- Setelah migration selesai, drop column:\n";
                $sql .= "-- ALTER TABLE `{$tableName}` DROP COLUMN `{$originalColumn}`;\n";
            } elseif (strpos(strtolower($originalColumn), 'alamat') !== false || strpos(strtolower($originalColumn), 'address') !== false) {
                $sql .= "-- ⚠️ Address normalization is complex and context-dependent\n";
                $sql .= "-- Saran struktur:\n";
                $sql .= "ALTER TABLE `{$tableName}` ADD COLUMN `jalan` VARCHAR(255) AFTER `{$originalColumn}`;\n";
                $sql .= "ALTER TABLE `{$tableName}` ADD COLUMN `kelurahan` VARCHAR(100) AFTER `jalan`;\n";
                $sql .= "ALTER TABLE `{$tableName}` ADD COLUMN `kecamatan` VARCHAR(100) AFTER `kelurahan`;\n";
                $sql .= "ALTER TABLE `{$tableName}` ADD COLUMN `kota` VARCHAR(100) AFTER `kecamatan`;\n";
                $sql .= "ALTER TABLE `{$tableName}` ADD COLUMN `provinsi` VARCHAR(100) AFTER `kota`;\n";
                $sql .= "ALTER TABLE `{$tableName}` ADD COLUMN `kode_pos` VARCHAR(10) AFTER `provinsi`;\n";
                $sql .= "-- TODO: Migrate data menggunakan parsing logic sesuai format alamat\n";
                $sql .= "-- Setelah migration selesai, drop column: ALTER TABLE `{$tableName}` DROP COLUMN `{$originalColumn}`;\n";
            } else {
                $sql .= "-- TODO: Pisahkan manual menggunakan ALTER TABLE ADD COLUMN\n";
                $sql .= "-- {$suggestion}\n";
            }

            $scripts[] = $sql;
        }

        return $scripts;
    }

    /**
     * Generate SQL untuk normalisasi Multi-Value dari data
     * 
     * @param string $tableName
     * @param array $multiValues
     * @return array
     */
    private function generateMultiValueSQL(string $tableName, array $multiValues): array
    {
        $scripts = [];

        foreach ($multiValues as $mv) {
            $columnName = $mv['column'];
            $newTableName = "{$tableName}_{$columnName}";

            $sql = "-- Normalisasi Multi-Value Attribute: {$columnName}\n";
            $sql .= "-- Buat junction table untuk menyimpan nilai-nilai terpisah\n\n";
            $sql .= "CREATE TABLE `{$newTableName}` (\n";
            $sql .= "    `id` INT PRIMARY KEY AUTO_INCREMENT,\n";
            $sql .= "    `{$tableName}_id` INT NOT NULL,\n";
            $sql .= "    `{$columnName}` VARCHAR(255),\n";
            $sql .= "    FOREIGN KEY (`{$tableName}_id`) REFERENCES `{$tableName}`(`id`) ON DELETE CASCADE\n";
            $sql .= ");\n\n";

            $sql .= "-- TODO: Migrate data dari {$tableName}.{$columnName}\n";
            if (!empty($mv['samples'])) {
                $sample = $mv['samples'][0];
                $separator = $sample['separator'];
                $sql .= "-- Sample value: '{$sample['value']}'\n";
                $sql .= "-- Separator detected: '{$separator}'\n";
                $sql .= "-- Split nilai berdasarkan separator '{$separator}'\n";
            }
            $sql .= "-- Kemudian drop column original:\n";
            $sql .= "-- ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`;\n\n";

            $scripts[] = $sql;
        }

        return $scripts;
    }

    /**
     * Generate SQL untuk Potential Multi-Value
     * 
     * @param string $tableName
     * @param array $potentials
     * @return array
     */
    private function generatePotentialMultiValueSQL(string $tableName, array $potentials): array
    {
        $scripts = [];

        foreach ($potentials as $potential) {
            $columnName = $potential['column'];
            $script = "-- Potential Multi-Value: {$columnName}\n";
            $script .= "-- Keyword: {$potential['detected_keyword']}\n";
            $script .= "-- Status verifikasi: " . ($potential['has_sample_data'] ? 'Ada sample data' : 'PERLU verifikasi manual') . "\n\n";
            $script .= "-- ⚠️ VERIFIKASI MANUAL DIBUTUHKAN\n";
            $script .= "-- Cek sample data untuk memastikan kolom '{$columnName}' berisi multi-value\n\n";

            $newTableName = "{$tableName}_{$columnName}";
            $script .= "-- Jika ini adalah multi-value, gunakan struktur berikut:\n";
            $script .= "CREATE TABLE `{$newTableName}` (\n";
            $script .= "    `id` INT PRIMARY KEY AUTO_INCREMENT,\n";
            $script .= "    `{$tableName}_id` INT NOT NULL,\n";
            $script .= "    `{$columnName}` VARCHAR(255),\n";
            $script .= "    FOREIGN KEY (`{$tableName}_id`) REFERENCES `{$tableName}`(`id`) ON DELETE CASCADE\n";
            $script .= ");\n\n";

            $scripts[] = $script;
        }

        return $scripts;
    }
}
