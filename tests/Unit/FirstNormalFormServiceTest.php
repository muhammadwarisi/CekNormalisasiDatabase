<?php

namespace Tests\Unit;

use App\Services\FirstNormalFormService;
use PHPUnit\Framework\TestCase;

/**
 * FirstNormalFormServiceTest - 40 comprehensive test cases
 * 
 * 20 Non-Normalized Test Cases (dengan violations yang dideteksi)
 * 20 Normalized Test Cases (output: "Skema ini sudah ternormalisasi 1NF")
 */
class FirstNormalFormServiceTest extends TestCase
{
    private FirstNormalFormService $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new FirstNormalFormService();
    }

    // ============================================================================
    // NON-NORMALIZED TEST CASES (1-20) - VIOLATIONS DETECTED
    // ============================================================================

    /**
     * Test 1: Repeating Groups - telp1, telp2, telp3
     */
    public function test_detect_repeating_groups_numeric_suffix(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'mahasiswa',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'telp1', 'type' => 'VARCHAR(20)'],
                        ['name' => 'telp2', 'type' => 'VARCHAR(20)'],
                        ['name' => 'telp3', 'type' => 'VARCHAR(20)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $this->assertCount(1, $result['tables'][0]['violations']);
        $this->assertEquals('repeating_groups', $result['tables'][0]['violations'][0]['type']);
        $this->assertCount(1, $result['tables'][0]['violations'][0]['columns']);
    }

    /**
     * Test 2: Repeating Groups - underscore pattern (email_1, email_2)
     */
    public function test_detect_repeating_groups_underscore_pattern(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'email_1', 'type' => 'VARCHAR(100)'],
                        ['name' => 'email_2', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $this->assertEquals('repeating_groups', $result['tables'][0]['violations'][0]['type']);
    }

    /**
     * Test 3: Repeating Groups - alamat_rumah, alamat_kantor, alamat_lain
     */
    public function test_detect_repeating_groups_address_pattern(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'kontak',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'alamat_rumah', 'type' => 'TEXT'],
                        ['name' => 'alamat_kantor', 'type' => 'TEXT'],
                        ['name' => 'alamat_lain', 'type' => 'TEXT'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $this->assertEquals('composite_columns', $result['tables'][0]['violations'][0]['type']);
    }

    /**
     * Test 4: Composite Column - nama_lengkap
     */
    public function test_detect_composite_column_nama_lengkap(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'siswa',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama_lengkap', 'type' => 'VARCHAR(200)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $this->assertEquals('composite_columns', $result['tables'][0]['violations'][0]['type']);
    }

    /**
     * Test 5: Composite Column - alamat_lengkap
     */
    public function test_detect_composite_column_alamat_lengkap(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'pelanggan',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'alamat_lengkap', 'type' => 'TEXT'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $this->assertEquals('composite_columns', $result['tables'][0]['violations'][0]['type']);
    }

    /**
     * Test 6: Composite Column - nama_depan_belakang
     */
    public function test_detect_composite_column_nama_depan_belakang(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'karyawan',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama_depan_belakang', 'type' => 'VARCHAR(200)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $this->assertEquals('composite_columns', $result['tables'][0]['violations'][0]['type']);
    }

    /**
     * Test 7: Multi-Value from Data - comma separated (hobi: Membaca,Bersepeda,Renang)
     */
    public function test_detect_multivalue_comma_separated(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'mahasiswa',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'hobi', 'type' => 'VARCHAR(200)'],
                    ],
                    'data' => [
                        [1, 'Andi', 'Membaca,Bersepeda,Renang'],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $violations = $result['tables'][0]['violations'];
        $multiValueViolations = array_filter($violations, fn($v) => $v['type'] === 'multi_value_data');
        $this->assertNotEmpty($multiValueViolations);
    }

    /**
     * Test 8: Multi-Value from Data - semicolon separated
     */
    public function test_detect_multivalue_semicolon_separated(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'developer',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'skill', 'type' => 'VARCHAR(200)'],
                    ],
                    'data' => [
                        [1, 'Budi', 'PHP;JavaScript;Python'],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $violations = $result['tables'][0]['violations'];
        $multiValueViolations = array_filter($violations, fn($v) => $v['type'] === 'multi_value_data');
        $this->assertNotEmpty($multiValueViolations);
    }

    /**
     * Test 9: Multi-Value from Data - pipe separated
     */
    public function test_detect_multivalue_pipe_separated(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'task',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'judul', 'type' => 'VARCHAR(100)'],
                        ['name' => 'tag', 'type' => 'VARCHAR(200)'],
                    ],
                    'data' => [
                        [1, 'Build API', 'urgent|important|review'],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $violations = $result['tables'][0]['violations'];
        $multiValueViolations = array_filter($violations, fn($v) => $v['type'] === 'multi_value_data');
        $this->assertNotEmpty($multiValueViolations);
    }

    /**
     * Test 10: Multi-Value from Data - newline separated
     */
    public function test_detect_multivalue_newline_separated(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'document',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'title', 'type' => 'VARCHAR(100)'],
                        ['name' => 'kategori', 'type' => 'TEXT'],
                    ],
                    'data' => [
                        [1, 'Panduan', "Teknis\nAdmin\nUser"],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $violations = $result['tables'][0]['violations'];
        $multiValueViolations = array_filter($violations, fn($v) => $v['type'] === 'multi_value_data');
        $this->assertNotEmpty($multiValueViolations);
    }

    /**
     * Test 11: Potential Multi-Value - hobi column (dari naming, no data)
     */
    public function test_detect_potential_multivalue_hobi_no_data(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'person',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'hobi', 'type' => 'VARCHAR(200)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $violations = $result['tables'][0]['violations'];
        $potentialViolations = array_filter($violations, fn($v) => $v['type'] === 'potential_multi_value');
        $this->assertNotEmpty($potentialViolations);
    }

    /**
     * Test 12: Potential Multi-Value - skill column
     */
    public function test_detect_potential_multivalue_skill_no_data(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'pekerja',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'skill', 'type' => 'VARCHAR(200)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $violations = $result['tables'][0]['violations'];
        $potentialViolations = array_filter($violations, fn($v) => $v['type'] === 'potential_multi_value');
        $this->assertNotEmpty($potentialViolations);
    }

    /**
     * Test 13: Mix - Repeating Groups + Composite Column
     */
    public function test_mix_repeating_groups_and_composite(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'kontak',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama_lengkap', 'type' => 'VARCHAR(200)'],
                        ['name' => 'telp1', 'type' => 'VARCHAR(20)'],
                        ['name' => 'telp2', 'type' => 'VARCHAR(20)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $this->assertGreaterThanOrEqual(2, count($result['tables'][0]['violations']));
    }

    /**
     * Test 14: Mix - Repeating Groups + Multi-Value Data
     */
    public function test_mix_repeating_groups_and_multivalue(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'siswa',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'hobi', 'type' => 'VARCHAR(200)'],
                        ['name' => 'alamat1', 'type' => 'TEXT'],
                        ['name' => 'alamat2', 'type' => 'TEXT'],
                    ],
                    'data' => [
                        [1, 'Andi', 'Membaca,Gaming', 'Jl. A', 'Jl. B'],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $this->assertGreaterThanOrEqual(2, count($result['tables'][0]['violations']));
    }

    /**
     * Test 15: Mix - Composite Column + Multi-Value Data
     */
    public function test_mix_composite_and_multivalue(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'pegawai',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama_lengkap', 'type' => 'VARCHAR(200)'],
                        ['name' => 'skill', 'type' => 'VARCHAR(200)'],
                    ],
                    'data' => [
                        [1, 'Budi Santoso', 'PHP;JavaScript;Python'],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $this->assertGreaterThanOrEqual(2, count($result['tables'][0]['violations']));
    }

    /**
     * Test 16: Mix - All violation types
     */
    public function test_mix_all_violation_types(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'mahasiswa',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama_lengkap', 'type' => 'VARCHAR(200)'], // Composite
                        ['name' => 'hobi', 'type' => 'VARCHAR(200)'],          // Potential multi-value
                        ['name' => 'telp1', 'type' => 'VARCHAR(20)'],         // Repeating
                        ['name' => 'telp2', 'type' => 'VARCHAR(20)'],         // Repeating
                    ],
                    'data' => [
                        [1, 'Andi Setiawan', 'Membaca,Bersepeda', '081234', '085678'],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $this->assertGreaterThanOrEqual(3, count($result['tables'][0]['violations']));
    }

    /**
     * Test 17: Edge Case - telp_lengkap_1, telp_lengkap_2 (repeating + composite pattern)
     */
    public function test_edge_case_repeating_with_composite_pattern(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'customer',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'telp_lengkap_1', 'type' => 'VARCHAR(200)'],
                        ['name' => 'telp_lengkap_2', 'type' => 'VARCHAR(200)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        // Should detect at least repeating groups
        $violations = $result['tables'][0]['violations'];
        $this->assertNotEmpty($violations);
    }

    /**
     * Test 18: Edge Case - Mixed separators in same column
     */
    public function test_edge_case_mixed_separators(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'data',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'values', 'type' => 'TEXT'],
                    ],
                    'data' => [
                        [1, 'val1,val2;val3|val4'],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        // Should detect multi-value from data
        $violations = $result['tables'][0]['violations'];
        $multiValueViolations = array_filter($violations, fn($v) => $v['type'] === 'multi_value_data');
        $this->assertNotEmpty($multiValueViolations);
    }

    /**
     * Test 19: Edge Case - Potential multi-value keywords in different case (HOBI, Skill)
     */
    public function test_edge_case_keyword_case_variations(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'person',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'HOBI', 'type' => 'VARCHAR(200)'],
                        ['name' => 'Skill', 'type' => 'VARCHAR(200)'],
                        ['name' => 'KATEGORI', 'type' => 'VARCHAR(200)'],
                        ['name' => 'category', 'type' => 'VARCHAR(200)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);

        $violations = $result['tables'][0]['violations'];

        // ⭐ CARA 1: Cek total kolom yang terdeteksi (bukan jumlah violation)
        $totalPotentialMultiValueColumns = 0;
        foreach ($violations as $v) {
            if ($v['type'] === 'potential_multi_value') {
                $totalPotentialMultiValueColumns += count($v['columns']);
            }
        }
        $this->assertGreaterThanOrEqual(2, $totalPotentialMultiValueColumns);
    }

    /**
     * Test 20: Edge Case - Multiple repeating group patterns in same table
     */
    public function test_edge_case_multiple_repeating_patterns(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'kontak',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'telp1', 'type' => 'VARCHAR(20)'],
                        ['name' => 'telp2', 'type' => 'VARCHAR(20)'],
                        ['name' => 'email_1', 'type' => 'VARCHAR(100)'],
                        ['name' => 'email_2', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertTrue($result['tables'][0]['has_violations']);
        $violations = $result['tables'][0]['violations'];
        $repeatingViolations = array_filter($violations, fn($v) => $v['type'] === 'repeating_groups');
        // Should detect multiple repeating group patterns
        $this->assertGreaterThanOrEqual(1, count($repeatingViolations));
    }

    // ============================================================================
    // NORMALIZED TEST CASES (21-40) - NO VIOLATIONS
    // ============================================================================

    /**
     * Test 21: Simple atomic table - id, nama, email, alamat
     */
    public function test_normalized_simple_atomic_table(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'email', 'type' => 'VARCHAR(100)'],
                        ['name' => 'alamat', 'type' => 'VARCHAR(255)'],
                    ],
                    'data' => [
                        [1, 'Andi', 'andi@email.com', 'Jalan A'],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
        $this->assertCount(0, $result['tables'][0]['violations']);
        $this->assertStringContainsString('ternormalisasi', strtolower($result['tables'][0]['message']));
    }

    /**
     * Test 22: Separate columns - nama_depan, nama_belakang (NOT nama_lengkap)
     */
    public function test_normalized_separate_name_columns(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'karyawan',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama_depan', 'type' => 'VARCHAR(100)'],
                        ['name' => 'nama_belakang', 'type' => 'VARCHAR(100)'],
                        ['name' => 'email', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 23: Separate address columns - jalan, kelurahan, kota, provinsi
     */
    public function test_normalized_separate_address_columns(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'pelanggan',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'jalan', 'type' => 'VARCHAR(255)'],
                        ['name' => 'kelurahan', 'type' => 'VARCHAR(100)'],
                        ['name' => 'kota', 'type' => 'VARCHAR(100)'],
                        ['name' => 'provinsi', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 24: Junction table for many-to-many - users + user_hobi + hobi
     */
    public function test_normalized_junction_table_users_hobi(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [
                        [1, 'Andi Setiawan'],
                        [2, 'Budi Pranoto'],
                        [3, 'Citra Dewi'],
                    ],
                ],
                [
                    'name' => 'hobi',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [
                        [1, 'Membaca'],
                        [2, 'Bersepeda'],
                        [3, 'Fotografi'],
                        [4, 'Memancing'],
                    ],
                ],
                [
                    'name' => 'user_hobi',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'user_id', 'type' => 'INT'],
                        ['name' => 'hobi_id', 'type' => 'INT'],
                    ],
                    'data' => [
                        [1, 1, 1],
                        [2, 1, 2],
                        [3, 2, 2],
                        [4, 2, 3],
                        [5, 3, 4],
                    ],
                ],
            ],
        ];

        $result = $this->analyzer->analyze($parsedData);

        // Assertions
        foreach ($result['tables'] as $table) {
            $this->assertFalse($table['has_violations'], "Table {$table['name']} has violations: " . json_encode($table['violations']));
        }
    }

    /**
     * Test 25: Junction table - students + student_skills + skills
     */
    public function test_normalized_junction_table_student_skills(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'students',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ],
                [
                    'name' => 'skills',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'name', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ],
                [
                    'name' => 'student_skills',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'student_id', 'type' => 'INT'],
                        ['name' => 'skill_id', 'type' => 'INT'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        foreach ($result['tables'] as $table) {
            $this->assertFalse($table['has_violations']);
        }
    }

    /**
     * Test 26: Normalized phone - separate telefon table (1-to-many)
     */
    public function test_normalized_separate_phone_table(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ],
                [
                    'name' => 'telefon',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'user_id', 'type' => 'INT'],
                        ['name' => 'nomor', 'type' => 'VARCHAR(20)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        foreach ($result['tables'] as $table) {
            $this->assertFalse($table['has_violations']);
        }
    }

    /**
     * Test 27: Normalized email - separate email table
     */
    public function test_normalized_separate_email_table(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ],
                [
                    'name' => 'email',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'user_id', 'type' => 'INT'],
                        ['name' => 'email_address', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        foreach ($result['tables'] as $table) {
            $this->assertFalse($table['has_violations']);
        }
    }

    /**
     * Test 28: Normalized address - users + user_addresses table
     */
    public function test_normalized_separate_address_table(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ],
                [
                    'name' => 'user_addresses',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'user_id', 'type' => 'INT'],
                        ['name' => 'jalan', 'type' => 'VARCHAR(255)'],
                        ['name' => 'kota', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        foreach ($result['tables'] as $table) {
            $this->assertFalse($table['has_violations']);
        }
    }

    /**
     * Test 29: Enum/Limited multi-value - status (ACTIVE, INACTIVE, PENDING)
     */
    public function test_normalized_enum_column(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'status', 'type' => 'ENUM("ACTIVE","INACTIVE","PENDING")'],
                    ],
                    'data' => [
                        [1, 'Andi', 'ACTIVE'],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 30: Single contact method per row (normalized repetition)
     */
    public function test_normalized_single_contact_per_row(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'user_contacts',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'user_id', 'type' => 'INT'],
                        ['name' => 'contact_type', 'type' => 'VARCHAR(50)'],
                        ['name' => 'contact_value', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [
                        [1, 1, 'phone', '081234'],
                        [2, 1, 'email', 'andi@email.com'],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 31: Surrogate key + atomic columns only
     */
    public function test_normalized_surrogate_key_atomic_columns(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'products',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'sku', 'type' => 'VARCHAR(50)'],
                        ['name' => 'nama', 'type' => 'VARCHAR(200)'],
                        ['name' => 'harga', 'type' => 'DECIMAL(10,2)'],
                        ['name' => 'stok', 'type' => 'INT'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 32: Foreign keys but all atomic columns
     */
    public function test_normalized_with_foreign_keys(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'orders',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'user_id', 'type' => 'INT'],
                        ['name' => 'product_id', 'type' => 'INT'],
                        ['name' => 'quantity', 'type' => 'INT'],
                        ['name' => 'order_date', 'type' => 'DATETIME'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 33: Single-value columns from what could be composite (first_name, last_name NOT full_name)
     */
    public function test_normalized_proper_column_separation(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'employees',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'first_name', 'type' => 'VARCHAR(100)'],
                        ['name' => 'last_name', 'type' => 'VARCHAR(100)'],
                        ['name' => 'email', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 34: Timestamps - created_at, updated_at (atomic, standard naming)
     */
    public function test_normalized_timestamps(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'posts',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'title', 'type' => 'VARCHAR(200)'],
                        ['name' => 'content', 'type' => 'TEXT'],
                        ['name' => 'created_at', 'type' => 'TIMESTAMP'],
                        ['name' => 'updated_at', 'type' => 'TIMESTAMP'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 35: Nullable atomic columns (NULL allowed when filled is atomic)
     */
    public function test_normalized_nullable_atomic_columns(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'phone', 'type' => 'VARCHAR(20)'], // nullable
                        ['name' => 'avatar_url', 'type' => 'VARCHAR(255)'], // nullable
                    ],
                    'data' => [
                        [1, 'Andi', '081234', 'http://...'],
                        [2, 'Budi', null, null],
                    ],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 36: Boolean flags (not multi-value, each is separate boolean)
     */
    public function test_normalized_boolean_flags(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'is_active', 'type' => 'BOOLEAN'],
                        ['name' => 'is_verified', 'type' => 'BOOLEAN'],
                        ['name' => 'is_admin', 'type' => 'BOOLEAN'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 37: Numeric columns (id, age, price - all atomic)
     */
    public function test_normalized_numeric_columns(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'products',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'quantity', 'type' => 'INT'],
                        ['name' => 'price', 'type' => 'DECIMAL(10,2)'],
                        ['name' => 'discount_percent', 'type' => 'DECIMAL(5,2)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 38: Proper multi-table normalization - users -> orders -> order_items
     */
    public function test_normalized_multitable_structure(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'users',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ],
                [
                    'name' => 'orders',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'user_id', 'type' => 'INT'],
                        ['name' => 'order_date', 'type' => 'DATE'],
                    ],
                    'data' => [],
                ],
                [
                    'name' => 'order_items',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'order_id', 'type' => 'INT'],
                        ['name' => 'product_id', 'type' => 'INT'],
                        ['name' => 'quantity', 'type' => 'INT'],
                        ['name' => 'price', 'type' => 'DECIMAL(10,2)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        foreach ($result['tables'] as $table) {
            $this->assertFalse($table['has_violations']);
        }
    }

    /**
     * Test 39: Normalized date format - birth_date (single date, NOT day/month/year columns)
     */
    public function test_normalized_date_format(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'persons',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'nama', 'type' => 'VARCHAR(100)'],
                        ['name' => 'birth_date', 'type' => 'DATE'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        $this->assertFalse($result['tables'][0]['has_violations']);
    }

    /**
     * Test 40: Complex valid schema - employees with proper relationships to departments and projects
     */
    public function test_normalized_complex_valid_schema(): void
    {
        $parsedData = [
            'tables' => [
                [
                    'name' => 'employees',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'first_name', 'type' => 'VARCHAR(100)'],
                        ['name' => 'last_name', 'type' => 'VARCHAR(100)'],
                        ['name' => 'email', 'type' => 'VARCHAR(100)'],
                        ['name' => 'phone', 'type' => 'VARCHAR(20)'],
                        ['name' => 'hire_date', 'type' => 'DATE'],
                        ['name' => 'department_id', 'type' => 'INT'],
                    ],
                    'data' => [],
                ],
                [
                    'name' => 'departments',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'name', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ],
                [
                    'name' => 'projects',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'name', 'type' => 'VARCHAR(200)'],
                        ['name' => 'description', 'type' => 'TEXT'],
                        ['name' => 'start_date', 'type' => 'DATE'],
                        ['name' => 'end_date', 'type' => 'DATE'],
                    ],
                    'data' => [],
                ],
                [
                    'name' => 'employee_projects',
                    'columns' => [
                        ['name' => 'id', 'type' => 'INT'],
                        ['name' => 'employee_id', 'type' => 'INT'],
                        ['name' => 'project_id', 'type' => 'INT'],
                        ['name' => 'role', 'type' => 'VARCHAR(100)'],
                    ],
                    'data' => [],
                ]
            ]
        ];

        $result = $this->analyzer->analyze($parsedData);

        foreach ($result['tables'] as $table) {
            $this->assertFalse($table['has_violations'], "Table {$table['name']} should not have violations");
        }
    }
}
