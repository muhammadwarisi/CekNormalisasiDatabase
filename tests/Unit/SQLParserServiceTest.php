<?php

namespace Tests\Unit;

use App\Services\SQLParserService;
use PHPUnit\Framework\TestCase;

class SQLParserServiceTest extends TestCase
{
    private SQLParserService $sqlParser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sqlParser = new SQLParserService();
    }

    /**
     * Test: Parse simple CREATE TABLE statement
     */
    public function test_parse_simple_create_table(): void
    {
        $sql = "CREATE TABLE `users` (
            `id` INT PRIMARY KEY,
            `nama` VARCHAR(100),
            `email` VARCHAR(100)
        );";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $this->assertArrayHasKey('tables', $result);
        $this->assertCount(1, $result['tables']);

        $table = $result['tables'][0];
        $this->assertEquals('users', $table['name']);
        $this->assertCount(3, $table['columns']);
        $this->assertEquals('id', $table['columns'][0]['name']);
        $this->assertEquals('INT', $table['columns'][0]['type']);

        unlink($tmpFile);
    }

    /**
     * Test: Parse CREATE TABLE with backticks
     */
    public function test_parse_create_table_with_backticks(): void
    {
        $sql = "CREATE TABLE `mahasiswa` (
            `id` INT PRIMARY KEY,
            `nama_lengkap` VARCHAR(255),
            `email` VARCHAR(100)
        );";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $this->assertEquals('mahasiswa', $result['tables'][0]['name']);
        $this->assertCount(3, $result['tables'][0]['columns']);

        unlink($tmpFile);
    }

    /**
     * Test: Parse CREATE TABLE without backticks
     */
    public function test_parse_create_table_without_backticks(): void
    {
        $sql = "CREATE TABLE users (
            id INT PRIMARY KEY,
            nama VARCHAR(100),
            email VARCHAR(100)
        );";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $this->assertEquals('users', $result['tables'][0]['name']);
        $this->assertCount(3, $result['tables'][0]['columns']);

        unlink($tmpFile);
    }

    /**
     * Test: Parse multiple CREATE TABLE statements
     */
    public function test_parse_multiple_tables(): void
    {
        $sql = "
            CREATE TABLE `users` (
                `id` INT PRIMARY KEY,
                `nama` VARCHAR(100)
            );
            
            CREATE TABLE `posts` (
                `id` INT PRIMARY KEY,
                `user_id` INT,
                `title` VARCHAR(200)
            );
        ";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $this->assertCount(2, $result['tables']);
        $this->assertEquals('users', $result['tables'][0]['name']);
        $this->assertEquals('posts', $result['tables'][1]['name']);

        unlink($tmpFile);
    }

    /**
     * Test: Parse INSERT statements
     */
    public function test_parse_insert_statements(): void
    {
        $sql = "
            CREATE TABLE `mahasiswa` (
                `id` INT PRIMARY KEY,
                `nama` VARCHAR(100),
                `hobi` VARCHAR(200)
            );
            
            INSERT INTO `mahasiswa` VALUES 
            (1, 'Andi', 'Membaca,Bersepeda'),
            (2, 'Budi', 'Coding');
        ";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $this->assertCount(1, $result['tables']);
        $data = $result['tables'][0]['data'];
        $this->assertCount(2, $data);
        $this->assertEquals('Andi', $data[0][1]);
        $this->assertEquals('Membaca,Bersepeda', $data[0][2]);

        unlink($tmpFile);
    }

    /**
     * Test: Parse INSERT with NULL values
     */
    public function test_parse_insert_with_null(): void
    {
        $sql = "
            CREATE TABLE `test` (
                `id` INT,
                `name` VARCHAR(100),
                `phone` VARCHAR(20)
            );
            
            INSERT INTO `test` VALUES 
            (1, 'Andi', '081234'),
            (2, 'Budi', NULL);
        ";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $data = $result['tables'][0]['data'];
        $this->assertEquals(1, $data[0][0]);
        $this->assertNull($data[1][2]);

        unlink($tmpFile);
    }

    /**
     * Test: Parse INSERT with string values containing quotes
     */
    public function test_parse_insert_with_quotes(): void
    {
        $sql = "
        CREATE TABLE `test` (
            `id` INT,
            `name` VARCHAR(100)
        );
        
        INSERT INTO `test` VALUES 
        (1, 'Andi''s Data');
    ";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $data = $result['tables'][0]['data'];

        // ✅ Yang benar: parser harus mengembalikan string yang sudah di-unescape
        $this->assertEquals("Andi's Data", $data[0][1]);

        unlink($tmpFile);
    }

    /**
     * Test: Ignore SQL comments
     */
    public function test_ignore_sql_comments(): void
    {
        $sql = "
            -- This is a comment
            /* This is a block comment */
            CREATE TABLE `users` (
                `id` INT PRIMARY KEY, -- Primary key
                `nama` VARCHAR(100)   /* User name */
            );
        ";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $this->assertCount(1, $result['tables']);
        $this->assertEquals('users', $result['tables'][0]['name']);

        unlink($tmpFile);
    }

    /**
     * Test: Parse multi-line CREATE TABLE
     */
    public function test_parse_multiline_create_table(): void
    {
        $sql = "
            CREATE TABLE `users` (
                `id` INT PRIMARY KEY AUTO_INCREMENT,
                `nama_depan` VARCHAR(100) NOT NULL,
                `nama_belakang` VARCHAR(100),
                `email` VARCHAR(100) UNIQUE,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
            );
        ";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $table = $result['tables'][0];
        $this->assertEquals('users', $table['name']);
        $this->assertGreaterThanOrEqual(5, count($table['columns']));

        // Verify column names
        $columnNames = array_map(fn($col) => $col['name'], $table['columns']);
        $this->assertContains('id', $columnNames);
        $this->assertContains('nama_depan', $columnNames);
        $this->assertContains('email', $columnNames);

        unlink($tmpFile);
    }

    /**
     * Test: Handle file not found
     */
    public function test_file_not_found_exception(): void
    {
        $this->expectException(\Exception::class);
        $this->sqlParser->parse('/non/existent/file.sql');
    }

    /**
     * Test: Parse INSERT with various data types
     */
    public function test_parse_insert_various_types(): void
    {
        $sql = "
            CREATE TABLE `test` (
                `id` INT,
                `nama` VARCHAR(100),
                `age` INT,
                `salary` DECIMAL(10,2)
            );
            
            INSERT INTO `test` VALUES 
            (1, 'Andi', 25, 5000.50);
        ";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $data = $result['tables'][0]['data'];
        $this->assertEquals('1', $data[0][0]);
        $this->assertEquals('Andi', $data[0][1]);
        $this->assertEquals('25', $data[0][2]);
        $this->assertEquals('5000.50', $data[0][3]);

        unlink($tmpFile);
    }

    /**
     * Test: Parse table with primary key constraint
     */
    public function test_parse_table_with_constraints(): void
    {
        $sql = "
            CREATE TABLE `users` (
                `id` INT NOT NULL,
                `email` VARCHAR(100),
                PRIMARY KEY (`id`),
                UNIQUE KEY `email_unique` (`email`)
            );
        ";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $table = $result['tables'][0];
        // Should parse only actual columns, skip constraints
        $this->assertCount(2, $table['columns']);
        $this->assertEquals('id', $table['columns'][0]['name']);
        $this->assertEquals('email', $table['columns'][1]['name']);

        unlink($tmpFile);
    }

    /**
     * Test: Parse limited rows from INSERT (max 20)
     */
    public function test_parse_limits_insert_rows(): void
    {
        $values = implode(",\n", array_map(fn($i) => "($i, 'User$i')", range(1, 30)));

        $sql = "
            CREATE TABLE `users` (
                `id` INT,
                `name` VARCHAR(100)
            );
            
            INSERT INTO `users` VALUES 
            $values;
        ";

        $tmpFile = tempnam(sys_get_temp_dir(), 'sql_');
        file_put_contents($tmpFile, $sql);

        $result = $this->sqlParser->parse($tmpFile);

        $data = $result['tables'][0]['data'];
        // Should limit to 20 rows
        $this->assertLessThanOrEqual(20, count($data));

        unlink($tmpFile);
    }
}
