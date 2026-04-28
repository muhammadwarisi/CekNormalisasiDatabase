<?php

namespace App\Http\Controllers;

use App\Services\SQLParserService;
use App\Services\FirstNormalFormService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * NormalizationController - Handle file upload dan analyze 1NF violations
 * 
 * PRE-PROCESSOR untuk Algoritma Demba. Controller ini menangani:
 * - Upload file .sql
 * - Parse dan extract schema/data
 * - Analyze pelanggaran 1NF
 * - Display hasil ke Blade view
 * 
 * @author Sistem Rekomendasi 1NF - Algoritma Demba
 */
class NormalizationController extends Controller
{
    private SQLParserService $sqlParser;
    private FirstNormalFormService $normalFormAnalyzer;
    
    public function __construct(
        SQLParserService $sqlParser,
        FirstNormalFormService $normalFormAnalyzer
    ) {
        $this->sqlParser = $sqlParser;
        $this->normalFormAnalyzer = $normalFormAnalyzer;
    }

    /**
     * Display upload form
     * 
     * @return \Illuminate\View\View
     */
    public function showUpload()
    {
        return view('normalization.upload');
    }

    /**
     * Handle file upload dan analyze
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function uploadAndAnalyze(Request $request)
    {
        // Validation
        $validated = $request->validate([
            'sql_file' => [
                'required',
                'file',
                'mimes:sql,txt',
                'max:10240', // 10 MB
            ],
        ], [
            'sql_file.required' => 'File SQL harus diupload',
            'sql_file.file' => 'Upload harus berupa file',
            'sql_file.mimes' => 'File harus bertipe .sql atau .txt',
            'sql_file.max' => 'File maksimal 10 MB',
        ]);

        try {
            // Store file to storage/app/public/sql-uploads
            $storagePath = 'sql-uploads';
            if (!Storage::disk('public')->exists($storagePath)) {
                Storage::disk('public')->makeDirectory($storagePath);
            }

            $file = $request->file('sql_file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs($storagePath, $fileName, 'public');

            // Get full path
            $fullPath = Storage::disk('public')->path($filePath);

            // Parse SQL file
            $parsedData = $this->sqlParser->parse($fullPath);

            // Analyze 1NF violations
            $analysis = $this->normalFormAnalyzer->analyze($parsedData);

            // Store results dalam session
            session([
                'analysis_results' => $analysis,
                'uploaded_file' => $fileName,
                'upload_time' => now()->format('Y-m-d H:i:s'),
                'file_path' => $filePath,
            ]);

            return redirect()->route('normalization.results')
                ->with('success', 'File berhasil dianalisis. Lihat hasil di bawah.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withErrors(['error' => 'Gagal menganalisis file: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display analysis results
     * 
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function showResults()
    {
        $analysis = session('analysis_results');
        $uploadedFile = session('uploaded_file');
        $uploadTime = session('upload_time');

        if (!$analysis) {
            return redirect()->route('normalization.upload')
                ->withErrors(['error' => 'Tidak ada hasil analisis. Silakan upload file terlebih dahulu.']);
        }

        return view('normalization.results', [
            'analysis' => $analysis,
            'uploadedFile' => $uploadedFile,
            'uploadTime' => $uploadTime,
        ]);
    }

    /**
     * Get detail untuk single table (untuk future enhancement: AJAX detail view)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetail(Request $request)
    {
        $analysis = session('analysis_results');
        $tableIndex = $request->input('table_index');

        if (!$analysis || !isset($analysis['tables'][$tableIndex])) {
            return response()->json([
                'error' => 'Table not found',
            ], 404);
        }

        return response()->json($analysis['tables'][$tableIndex]);
    }

    /**
     * Generate JSON report
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateReport()
    {
        $analysis = session('analysis_results');
        $uploadedFile = session('uploaded_file');
        $uploadTime = session('upload_time');

        if (!$analysis) {
            return response()->json([
                'error' => 'No analysis results available',
            ], 404);
        }

        return response()->json([
            'report_title' => 'Analisis Pelanggaran 1NF',
            'uploaded_file' => $uploadedFile,
            'analysis_timestamp' => $uploadTime,
            'summary' => $analysis['summary'],
            'tables' => $analysis['tables'],
        ]);
    }

    /**
     * Clear session data (optional, untuk reset)
     * 
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clearSession()
    {
        session()->forget(['analysis_results', 'uploaded_file', 'upload_time', 'file_path']);

        return redirect()->route('normalization.upload')
            ->with('success', 'Session telah dihapus. Upload file baru untuk analisis.');
    }
}
