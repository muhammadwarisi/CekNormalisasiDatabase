@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-3xl mx-auto">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">
                Analisis Pelanggaran 1NF
            </h1>
            <p class="text-lg text-gray-600 mb-2">
                PRE-PROCESSOR untuk Algoritma Demba
            </p>
            <p class="text-sm text-gray-500">
                Upload file SQL database Anda untuk analisis pelanggaran First Normal Form (1NF)
            </p>
        </div>

        @if ($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <h3 class="text-red-800 font-semibold mb-2">Terjadi Kesalahan:</h3>
                <ul class="list-disc list-inside text-red-700 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Upload Card -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <!-- Info Box -->
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-8 rounded">
                <h3 class="font-semibold text-blue-900 mb-2">📋 Informasi File</h3>
                <ul class="text-sm text-blue-800 space-y-1">
                    <li>✓ Format file: .sql atau .txt</li>
                    <li>✓ Ukuran maksimal: 10 MB</li>
                    <li>✓ Harus berisi CREATE TABLE statements</li>
                    <li>✓ INSERT statements opsional (untuk validasi multi-value)</li>
                </ul>
            </div>

            <!-- Upload Form -->
            <form action="{{ route('normalization.upload.post') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <!-- File Input -->
                <div>
                    <label for="sql_file" class="block text-sm font-medium text-gray-700 mb-3">
                        Upload File SQL
                    </label>
                    <div class="relative">
                        <input 
                            type="file" 
                            id="sql_file" 
                            name="sql_file"
                            accept=".sql,.txt"
                            required
                            class="hidden"
                            onchange="updateFileName(this)"
                        >
                        <label for="sql_file" class="block cursor-pointer">
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-500 hover:bg-blue-50 transition">
                                <svg class="mx-auto h-12 w-12 text-gray-400 mb-3" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20a4 4 0 004 4h24a4 4 0 004-4V20m-8-12l-4-4m0 0l-4 4m4-4v12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <p id="file-name" class="text-gray-600">
                                    Klik untuk memilih file atau drag and drop
                                </p>
                                <p class="text-xs text-gray-500 mt-2">
                                    File .sql atau .txt (max 10 MB)
                                </p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition transform hover:scale-105 active:scale-95"
                >
                    <span class="inline-flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Analisis File SQL
                    </span>
                </button>
            </form>
        </div>

        <!-- Information Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Apa itu 1NF -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">❓ Apa itu First Normal Form (1NF)?</h3>
                <ul class="text-sm text-gray-700 space-y-2">
                    <li><strong>1.</strong> Setiap kolom harus atomic (tidak ada repeating values)</li>
                    <li><strong>2.</strong> Tidak ada kolom composite (harus dipecah)</li>
                    <li><strong>3.</strong> Setiap kolom harus unique</li>
                    <li><strong>4.</strong> Urutan kolom/baris tidak penting</li>
                </ul>
            </div>

            <!-- Deteksi Sistem -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">🔍 Sistem Ini Mendeteksi:</h3>
                <ul class="text-sm text-gray-700 space-y-2">
                    <li><strong>🔄 Repeating Groups:</strong> telp1, telp2, telp3</li>
                    <li><strong>🔗 Composite Columns:</strong> nama_lengkap, alamat_lengkap</li>
                    <li><strong>📊 Multi-Value Attributes:</strong> comma/semicolon separated values</li>
                    <li><strong>⚠️ Potential Multi-Value:</strong> dari naming patterns</li>
                </ul>
            </div>
        </div>

        <!-- Example Section -->
        <div class="mt-8 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">📝 Contoh File SQL yang Valid:</h3>
            <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                <pre class="text-gray-100 text-xs"><code>CREATE TABLE `mahasiswa` (
    `id` INT PRIMARY KEY,
    `nama` VARCHAR(100),
    `nama_lengkap` VARCHAR(200),
    `hobi` VARCHAR(200),
    `telp1` VARCHAR(20),
    `telp2` VARCHAR(20),
    `telp3` VARCHAR(20)
);

INSERT INTO `mahasiswa` VALUES 
(1, 'Andi', 'Andi Setiawan', 'Membaca,Bersepeda', '081234', '081567', '081890'),
(2, 'Budi', 'Budi Santoso', 'Coding;Gaming', '082134', NULL, NULL);</code></pre>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-600">
            <p>Sistem ini adalah PRE-PROCESSOR untuk Algoritma Demba dalam Skripsi: </p>
            <p class="font-semibold text-gray-900">
                "Rancang Bangun Sistem Rekomendasi Database 1N-3NF menggunakan Algoritma Demba"
            </p>
        </div>
    </div>
</div>

<style>
    #sql_file:hover ~ label,
    #sql_file:focus ~ label {
        border-color: #3b82f6;
        background-color: #eff6ff;
    }
</style>

<script>
    function updateFileName(input) {
        const fileName = input.files[0]?.name || 'Klik untuk memilih file atau drag and drop';
        document.getElementById('file-name').textContent = fileName;
    }

    // Drag and drop support
    const dropZone = document.querySelector('label[for="sql_file"]');
    const fileInput = document.getElementById('sql_file');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('bg-blue-50', 'border-blue-500');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('bg-blue-50', 'border-blue-500');
        }, false);
    });

    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        const files = dt.files;
        fileInput.files = files;
        updateFileName(fileInput);
    }, false);
</script>
@endsection
