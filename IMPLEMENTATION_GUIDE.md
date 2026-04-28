# 📊 Sistem Rekomendasi 1NF - Setup & Implementation Guide

## PRE-PROCESSOR untuk Algoritma Demba

Sistem ini adalah pre-processor untuk deteksi pelanggaran First Normal Form (1NF) sebelum data diproses dengan Algoritma Demba untuk normalisasi ke 2NF/3NF.

### Skripsi
**Rancang Bangun Sistem Rekomendasi Database 1N-3NF menggunakan Algoritma Demba**

---

## 📁 File Structure

```
app/
├── Services/
│   ├── SQLParserService.php              # Parse SQL files
│   └── FirstNormalFormService.php        # Detect 1NF violations & recommendations
├── Http/
│   └── Controllers/
│       └── NormalizationController.php   # Handle requests

routes/
└── web.php                               # Add normalization routes

resources/views/
├── layouts/
│   └── app.blade.php                     # Base layout
└── normalization/
    ├── upload.blade.php                  # Upload form
    └── results.blade.php                 # Analysis results

storage/app/public/
└── sql-uploads/                          # Uploaded SQL files

tests/Unit/
├── SQLParserServiceTest.php             # SQL parsing tests
└── FirstNormalFormServiceTest.php       # 40 comprehensive test cases
```

---

## 🚀 Quick Start

### 1. **Pastikan Dependencies Terinstall**

```bash
composer install
npm install
```

### 2. **Compile Assets (Tailwind CSS)**

```bash
npm run dev
```

Atau untuk production:
```bash
npm run build
```

### 3. **Serve Application**

```bash
php artisan serve
```

Akses di: `http://localhost:8000/normalization/upload`

---

## 🧪 Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific Test File
```bash
php artisan test tests/Unit/SQLParserServiceTest.php
php artisan test tests/Unit/FirstNormalFormServiceTest.php
```

### Run Specific Test
```bash
php artisan test --filter test_detect_repeating_groups_numeric_suffix
```

### Test Coverage
```bash
php artisan test --coverage
```

---

## 📋 Features

### 1. **SQL File Parsing**
- Parse CREATE TABLE statements (dengan/tanpa backticks)
- Extract column names & data types
- Parse INSERT statements (max 20 baris per tabel)
- Handle SQL comments, multi-line, special characters

### 2. **Deteksi 4 Jenis Pelanggaran 1NF**

#### a) **Repeating Groups**
```sql
-- Tidak 1NF ❌
CREATE TABLE mahasiswa (
    id INT,
    nama VARCHAR(100),
    telp1 VARCHAR(20),      -- Repeating group
    telp2 VARCHAR(20),      -- Repeating group
    telp3 VARCHAR(20)       -- Repeating group
);
```

#### b) **Composite Columns**
```sql
-- Tidak 1NF ❌
CREATE TABLE siswa (
    id INT,
    nama_lengkap VARCHAR(200)  -- Composite column (harus dipecah)
);

-- 1NF ✅
CREATE TABLE siswa (
    id INT,
    nama_depan VARCHAR(100),
    nama_belakang VARCHAR(100)
);
```

#### c) **Multi-Value Attributes (dari data)**
```sql
-- Tidak 1NF ❌
INSERT INTO mahasiswa VALUES 
(1, 'Andi', 'Membaca,Bersepeda,Renang');  -- Multi-value dalam satu kolom
```

#### d) **Potential Multi-Value (dari naming)**
```sql
-- Perlu verifikasi ⚠️
CREATE TABLE person (
    id INT,
    hobi VARCHAR(200)  -- Keyword "hobi" menunjukkan multi-value
);
```

### 3. **Generate Recommendations**
- SQL scripts untuk normalisasi
- Langkah-langkah normalisasi
- Saran pemisahan tabel

---

## 📊 Test Coverage

### 40 Comprehensive Test Cases

**20 Non-Normalized Test Cases:**
1. Repeating groups (numeric, underscore, multi-pattern)
2. Composite columns (nama_lengkap, alamat_lengkap, composite patterns)
3. Multi-value dari data (comma, semicolon, pipe, newline separated)
4. Potential multi-value (dari naming patterns)
5. Mix violations (2-4 jenis sekaligus)
6. Edge cases (mixed separators, case variations, complex patterns)

**20 Normalized Test Cases:**
1. Simple atomic tables
2. Properly separated columns (nama_depan, nama_belakang)
3. Separate address tables
4. Junction tables untuk many-to-many
5. Separate tables untuk 1-to-many relationships
6. Enum/boolean columns
7. Multi-table normalized structures
8. Complex valid schemas

---

## 🔍 API Routes

### Upload Form
```
GET /normalization/upload
```

### Process Upload
```
POST /normalization/upload
Content-Type: multipart/form-data
Body: sql_file (file)
```

### View Results
```
GET /normalization/results
```

### Get JSON Report
```
GET /normalization/report
```

### Clear Session
```
POST /normalization/clear
```

---

## 💡 Example Usage

### 1. Upload File SQL
- Navigasi ke `http://localhost:8000/normalization/upload`
- Pilih file .sql atau .txt
- Click "Analisis File SQL"

### 2. Review Results
- Lihat daftar tabel dengan status (✅ atau ❌)
- Klik table untuk expand detail violations
- Review recommendations & SQL scripts

### 3. Terapkan Normalisasi
- Salin SQL scripts dari recommendations
- Jalankan di database Anda
- Verify data migration

---

## 📝 Validation Rules

### File Upload
- **Format**: .sql atau .txt
- **Ukuran**: Max 10 MB
- **Content**: Minimal satu CREATE TABLE statement

### SQL Requirements
- CREATE TABLE statements (required)
- INSERT statements (optional, untuk validasi multi-value)

---

## 🛠️ Teknologi

- **Framework**: Laravel 12
- **PHP**: 8.2+
- **CSS**: Tailwind CSS
- **Testing**: PHPUnit

---

## 📌 Key Implementation Details

### SQLParserService
```php
// Parse file SQL
$parsed = $sqlParser->parse('/path/to/file.sql');
// Output: ['tables' => [['name' => '...', 'columns' => [...], 'data' => [...]]]]
```

### FirstNormalFormService
```php
// Analyze untuk violations
$analysis = $analyzer->analyze($parsedData);
// Output: ['tables' => [...], 'summary' => [...]]
```

---

## 🚨 Common Issues & Solutions

### Issue: "File not found" error
**Solution**: Pastikan file SQL ada dan readable

### Issue: "File too large" error
**Solution**: Gunakan file ≤ 10 MB atau split into smaller files

### Issue: Parse error pada SQL special characters
**Solution**: Pastikan SQL format valid (matching quotes, proper escaping)

### Issue: Multi-value detection tidak akurat
**Solution**: Pastikan ada sample data (INSERT statements) dalam file SQL

---

## 📚 Architecture Overview

```
User Upload SQL File
        ↓
   SQLParserService
   - Parse CREATE TABLE
   - Extract INSERT data
        ↓
FirstNormalFormService
   - Detect Repeating Groups
   - Detect Composite Columns
   - Detect Multi-Value (data)
   - Detect Potential Multi-Value (naming)
   - Generate Recommendations
        ↓
   Blade View Display
   - Table summary
   - Violations detail
   - Recommendations & SQL scripts
```

---

## 🔄 Workflow Demba Integration

```
1. User Upload SQL → NormalizationController
2. Parse & Analyze untuk 1NF violations
3. Generate Recommendations
4. User Review & Apply Normalisasi
5. SQL diupdate ke 1NF ✅
6. Kirim ke Algoritma Demba untuk 2NF/3NF normalization
```

---

## 📧 Support & Documentation

Untuk detail lebih lengkap, lihat:
- Code comments di setiap service & controller
- Test cases untuk contoh penggunaan
- Blade views untuk UI implementation

---

## ✅ Checklist Implementation

- [x] SQLParserService - Parse SQL files
- [x] FirstNormalFormService - Detect violations
- [x] NormalizationController - Handle requests
- [x] Routes (web.php) - Setup routes
- [x] Blade Views - Upload form & results
- [x] SQLParserServiceTest - 12 parsing tests
- [x] FirstNormalFormServiceTest - 40 test cases (20 non-norm + 20 norm)
- [x] Storage directory - Create upload directory
- [x] Documentation - This file

---

## 🎓 Thesis Context

**Judul Skripsi**: Rancang Bangun Sistem Rekomendasi Database 1N-3NF menggunakan Algoritma Demba

**Sistem ini**: Pre-processor untuk mengidentifikasi dan merekomendasikan perbaikan pelanggaran 1NF sebelum algoritma Demba dijalankan untuk normalisasi ke level lebih tinggi (2NF, 3NF).

---

Generated: 2024-04-27
Version: 1.0
