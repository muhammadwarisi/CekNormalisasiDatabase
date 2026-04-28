@extends('layouts.app')

@section('content')
    <div class="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-900">Hasil Analisis 1NF</h1>
                        <p class="text-gray-600 mt-2">
                            File: <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded">{{ $uploadedFile }}</span>
                        </p>
                        <p class="text-sm text-gray-500 mt-1">
                            Waktu analisis: {{ $uploadTime }}
                        </p>
                    </div>
                    <a href="{{ route('normalization.upload') }}"
                        class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Upload File Baru
                    </a>
                </div>
            </div>

            <!-- Summary Section -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-gray-600 text-sm font-medium">Total Tabel</p>
                    <p class="text-3xl font-bold text-gray-900">{{ $analysis['summary']['total_tables'] }}</p>
                </div>
                <div class="bg-green-50 rounded-lg shadow p-6 border-l-4 border-green-500">
                    <p class="text-green-600 text-sm font-medium">Tabel Ternormalisasi</p>
                    <p class="text-3xl font-bold text-green-600">{{ $analysis['summary']['tables_normalized'] }}</p>
                </div>
                <div class="bg-red-50 rounded-lg shadow p-6 border-l-4 border-red-500">
                    <p class="text-red-600 text-sm font-medium">Tabel Bermasalah</p>
                    <p class="text-3xl font-bold text-red-600">{{ $analysis['summary']['tables_with_violations'] }}</p>
                </div>
                <div class="bg-yellow-50 rounded-lg shadow p-6 border-l-4 border-yellow-500">
                    <p class="text-yellow-600 text-sm font-medium">Total Jenis Violation</p>
                    <p class="text-3xl font-bold text-yellow-600">{{ $analysis['summary']['total_violation_types'] }}</p>
                </div>
            </div>

            <!-- Tables List -->
            <div class="space-y-4">
                @foreach ($analysis['tables'] as $tableIndex => $table)
                    @php
                        $statusBg = $table['has_violations']
                            ? 'bg-red-50 border-red-200'
                            : 'bg-green-50 border-green-200';
                        $statusIcon = $table['has_violations'] ? '❌' : '✅';
                        $statusText = $table['has_violations'] ? 'Tidak Ternormalisasi' : 'Ternormalisasi 1NF';
                        $statusColor = $table['has_violations'] ? 'text-red-700' : 'text-green-700';
                    @endphp

                    <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition">
                        <!-- Table Header (Clickable) -->
                        <div class="cursor-pointer table-toggle" data-table="{{ $tableIndex }}">
                            <div
                                class="border-l-4 {{ $table['has_violations'] ? 'border-red-500' : 'border-green-500' }} px-6 py-4 bg-gray-50 hover:bg-gray-100 transition flex justify-between items-center">
                                <div class="flex items-center gap-4">
                                    <span class="text-2xl">{{ $statusIcon }}</span>
                                    <div>
                                        <h3 class="text-xl font-bold text-gray-900">{{ $table['name'] }}</h3>
                                        <p class="text-sm {{ $statusColor }}">{{ $statusText }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-6">
                                    <div class="text-right">
                                        <p class="text-sm text-gray-600">Kolom: <span
                                                class="font-bold">{{ count($table['columns']) }}</span></p>
                                        @if ($table['has_violations'])
                                            <p class="text-sm text-red-600 font-semibold">Violations:
                                                {{ $table['violation_count'] }}</p>
                                        @endif
                                    </div>
                                    <svg class="w-6 h-6 text-gray-400 toggle-icon transition transform"
                                        data-table="{{ $tableIndex }}" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Table Details (Hidden by default) -->
                        <div class="table-details hidden" data-table="{{ $tableIndex }}">
                            <div class="px-6 py-6 border-t space-y-6">
                                <!-- Columns Info -->
                                <div>
                                    <h4 class="font-semibold text-gray-900 mb-3">📋 Kolom-Kolom:</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                        @foreach ($table['columns'] as $col)
                                            <div class="bg-gray-50 px-4 py-2 rounded text-sm">
                                                <span class="font-mono text-blue-600">{{ $col['name'] }}</span>
                                                <span class="text-gray-600"> - {{ $col['type'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Violations -->
                                @if ($table['has_violations'])
                                    <div>
                                        <h4 class="font-semibold text-gray-900 mb-3">⚠️ Pelanggaran Ditemukan:</h4>
                                        <div class="space-y-4">
                                            @foreach ($table['violations'] as $violation)
                                                <div
                                                    class="border-l-4 {{ $violation['severity'] === 'high' ? 'border-red-500 bg-red-50' : 'border-yellow-500 bg-yellow-50' }} p-4 rounded">
                                                    <div class="flex justify-between items-start mb-2">
                                                        <h5
                                                            class="font-bold {{ $violation['severity'] === 'high' ? 'text-red-700' : 'text-yellow-700' }}">
                                                            {{ $violation['description'] }}
                                                            @if ($violation['severity'] === 'high')
                                                                <span class="text-red-500">⚠️ HIGH</span>
                                                            @else
                                                                <span class="text-yellow-600">⚠️ MEDIUM</span>
                                                            @endif
                                                        </h5>
                                                    </div>

                                                    @if (isset($violation['note']))
                                                        <p
                                                            class="text-sm {{ $violation['severity'] === 'high' ? 'text-red-600' : 'text-yellow-600' }} mb-2">
                                                            📌 {{ $violation['note'] }}
                                                        </p>
                                                    @endif

                                                    <!-- Violation Details -->
                                                    @if ($violation['type'] === 'repeating_groups')
                                                        @foreach ($violation['columns'] as $group)
                                                            <div class="mt-3 bg-white rounded p-3 text-sm">
                                                                <p class="font-mono text-red-700 mb-2">
                                                                    Repeating Group:
                                                                    <strong>{{ $group['base_pattern'] }}</strong>
                                                                </p>
                                                                <p class="text-gray-700 mb-2">
                                                                    Kolom:
                                                                    <span
                                                                        class="font-mono">{{ implode(', ', $group['columns']) }}</span>
                                                                </p>
                                                                <p class="text-gray-600">
                                                                    💡 {{ $group['suggestion'] }}
                                                                </p>
                                                            </div>
                                                        @endforeach
                                                    @elseif ($violation['type'] === 'composite_columns')
                                                        @foreach ($violation['columns'] as $composite)
                                                            <div class="mt-3 bg-white rounded p-3 text-sm">
                                                                <p class="font-mono text-red-700 mb-2">
                                                                    Composite Column:
                                                                    <strong>{{ $composite['column'] }}</strong>
                                                                </p>
                                                                <p class="text-gray-700 mb-2">
                                                                    Keyword: <span
                                                                        class="bg-red-100 px-2 py-1 rounded text-red-700">{{ $composite['detected_keyword'] }}</span>
                                                                </p>
                                                                <p class="text-gray-600">
                                                                    💡 {{ $composite['suggestion'] }}
                                                                </p>
                                                            </div>
                                                        @endforeach
                                                    @elseif ($violation['type'] === 'multi_value_data')
                                                        @foreach ($violation['columns'] as $mv)
                                                            <div class="mt-3 bg-white rounded p-3 text-sm">
                                                                <p class="font-mono text-red-700 mb-2">
                                                                    Multi-Value Column:
                                                                    <strong>{{ $mv['column'] }}</strong>
                                                                </p>
                                                                @if (!empty($mv['samples']))
                                                                    <p class="text-gray-700 font-semibold mb-2">Sample Data:
                                                                    </p>
                                                                    <ul class="list-disc list-inside space-y-1 mb-2">
                                                                        @foreach ($mv['samples'] as $sample)
                                                                            <li class="text-gray-600">
                                                                                <span
                                                                                    class="font-mono">{{ $sample['value'] }}</span>
                                                                                <span
                                                                                    class="text-xs text-gray-500">(separator:
                                                                                    {{ $sample['separator'] === ',' ? 'comma' : ($sample['separator'] === ';' ? 'semicolon' : ($sample['separator'] === '|' ? 'pipe' : 'newline')) }})</span>
                                                                            </li>
                                                                        @endforeach
                                                                    </ul>
                                                                @endif
                                                                <p class="text-gray-600">
                                                                    💡 {{ $mv['suggestion'] }}
                                                                </p>
                                                            </div>
                                                        @endforeach
                                                    @elseif ($violation['type'] === 'potential_multi_value')
                                                        @foreach ($violation['columns'] as $potential)
                                                            <div class="mt-3 bg-white rounded p-3 text-sm">
                                                                <p class="font-mono text-yellow-700 mb-2">
                                                                    Potential Multi-Value:
                                                                    <strong>{{ $potential['column'] }}</strong>
                                                                </p>
                                                                <p class="text-gray-700 mb-2">
                                                                    Keyword: <span
                                                                        class="bg-yellow-100 px-2 py-1 rounded text-yellow-700">{{ $potential['detected_keyword'] }}</span>
                                                                </p>
                                                                @if (!empty($potential['note']))
                                                                    <p class="text-yellow-600 text-xs mb-2">
                                                                        📍 {{ $potential['note'] }}
                                                                    </p>
                                                                @endif
                                                                <p class="text-gray-600">
                                                                    💡 {{ $potential['suggestion'] }}
                                                                </p>
                                                            </div>
                                                        @endforeach
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Recommendations -->
                                    @if (!empty($table['recommendations']['sql_scripts']))
                                        <div>
                                            <h4 class="font-semibold text-gray-900 mb-3">📝 Rekomendasi Normalisasi:</h4>

                                            <!-- Normalization Steps -->
                                            @if (!empty($table['recommendations']['normalization_steps']))
                                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                                                    <p class="font-semibold text-blue-900 mb-2">Langkah-langkah Normalisasi:
                                                    </p>
                                                    <ol class="list-decimal list-inside space-y-1 text-sm text-blue-800">
                                                        @foreach ($table['recommendations']['normalization_steps'] as $step)
                                                            <li>{{ $step }}</li>
                                                        @endforeach
                                                    </ol>
                                                </div>
                                            @endif

                                            <!-- SQL Scripts -->
                                            <p class="text-sm font-semibold text-gray-700 mb-2">SQL Scripts untuk
                                                Normalisasi:</p>
                                            <div class="space-y-3">
                                                @foreach ($table['recommendations']['sql_scripts'] as $script)
                                                    <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                                        <pre class="text-gray-100 text-xs font-mono">
                                                            <code>
                                                                @if (is_array($script))
                                                                {{ json_encode($script, JSON_PRETTY_PRINT) }}
                                                                @else
                                                                {{ $script }}
                                                                @endif
                                                            </code>
                                                        </pre>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <!-- Notes -->
                                            @if (!empty($table['recommendations']['notes']))
                                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mt-4">
                                                    <p class="font-semibold text-amber-900 mb-2">📌 Catatan Penting:</p>
                                                    <ul class="list-disc list-inside space-y-1 text-sm text-amber-800">
                                                        @foreach ($table['recommendations']['notes'] as $note)
                                                            <li>{{ $note }}</li>
                                                        @endforeach
                                                    </ul>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                @else
                                    <!-- Success Message -->
                                    <div class="bg-green-50 border-l-4 border-green-500 p-6 rounded">
                                        <p class="text-green-700 font-semibold">
                                            ✅ Skema ini sudah ternormalisasi 1NF
                                        </p>
                                        <p class="text-green-600 text-sm mt-2">
                                            Tidak ada pelanggaran First Normal Form yang ditemukan. Anda dapat melanjutkan
                                            dengan algoritma Demba untuk normalisasi ke level yang lebih tinggi (2NF, 3NF).
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- JSON Report Button -->
            <div class="mt-8 flex justify-center gap-4">
                <a href="{{ route('normalization.report') }}"
                    class="inline-flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg transition"
                    target="_blank">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    Download JSON Report
                </a>
                <form action="{{ route('normalization.clear') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                        class="inline-flex items-center gap-2 bg-gray-400 hover:bg-gray-500 text-white px-6 py-3 rounded-lg transition"
                        onclick="return confirm('Hapus session dan upload file baru?')">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                            </path>
                        </svg>
                        Reset & Upload Baru
                    </button>
                </form>
            </div>

            <!-- Footer -->
            <div class="mt-12 text-center text-sm text-gray-600 border-t pt-8">
                <p>PRE-PROCESSOR untuk Algoritma Demba</p>
                <p class="font-semibold text-gray-900 mt-2">
                    Rancang Bangun Sistem Rekomendasi Database 1N-3NF menggunakan Algoritma Demba
                </p>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.table-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const tableIdx = this.dataset.table;
                const details = document.querySelector(`.table-details[data-table="${tableIdx}"]`);
                const icon = document.querySelector(`.toggle-icon[data-table="${tableIdx}"]`);

                details.classList.toggle('hidden');
                icon.style.transform = details.classList.contains('hidden') ? 'rotate(0deg)' :
                    'rotate(180deg)';
            });
        });
    </script>
@endsection
