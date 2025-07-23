<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;

class FileController extends Controller
{
    public function pdf(Request $request)
    {
        $formulir = $request->input('formulir', null);
        $data = $request->input('data', []);
        $dataRule = $request->input('dataRule', []);
        $penduduk = $request->input('penduduk', []);
        $to = $request->input('to', '-');

        // Pastikan folder pdf ada
        $pdfFolder = storage_path('app/public/pdf');
        if (!file_exists($pdfFolder)) {
            mkdir($pdfFolder, 0777, true);
        }

        // Deteksi isBantuanDuka sesuai dengan FormPermohonanSosial.jsx
        $isBantuanDuka = isset($dataRule['program_id']['title']) && strtoupper($dataRule['program_id']['title']) === 'BANTUAN UANG DUKA';

        $template = $this->generateTemplateProcessor($formulir, $data, $dataRule, $isBantuanDuka, $penduduk, $to);

        // Simpan docx ke folder pdf
        $docxFile = $pdfFolder . '/hasil.docx';
        $template->saveAs($docxFile);

        $pdfFile = $this->convertDocxToPdf($docxFile, $pdfFolder);

        // Pastikan $pdfFile adalah path absolut, dan dapatkan nama file saja untuk URL
        if (file_exists($pdfFile)) {
            // Ambil hanya nama file PDF (tanpa path)
            $pdfFileName = basename($pdfFile);

            // File sudah di storage/app/public/pdf, tidak perlu copy lagi
            // Buat signed URL dengan nama file saja
            $url = URL::temporarySignedRoute(
                'download.pdf', now()->addMinutes(5), ['filename' => $pdfFileName]
            );

            return response()->json([
                'url' => $url,
                'filename' => $pdfFileName
            ]);
        } else {
            return response()->json(['error' => 'PDF file not found'], 404);
        }
    }

    /**
     * Generate TemplateProcessor and set values based on formulir type
     * Ditambah parameter isBantuanDuka dan penduduk agar sesuai dengan FormPermohonanSosial.jsx
     * Ditambah parameter to untuk tujuan surat
     */
    private function generateTemplateProcessor($formulir, $data, $dataRule, $isBantuanDuka = false, $penduduk = null, $to = '-')
    {
        // Pilih template sesuai dengan jenis formulir menggunakan match
        // Jika formulir adalah 'formulir_permohonan_bantuan_uang_duka', tentukan $to (cq) sesuai switch di FormPermohonanSosial.jsx
        if ($formulir === 'formulir_permohonan_bantuan_uang_duka') {
            $programTitle = isset($dataRule['program_id']['title']) ? strtoupper($dataRule['program_id']['title']) : '';
            switch ($programTitle) {
                case 'BANTUAN UANG DUKA':
                case 'BANTUAN SOSIAL ANAK YATIM/PIATU':
                case 'BANTUAN SOSIAL LANSIA':
                case 'BANTUAN SOSIAL PENYANDANG DISABILITAS':
                case 'BANTUAN SOSIAL KEMISKINAN EKSTREM':
                    $to = 'Kepala Dinas Sosial';
                    break;
                case 'BANTUAN SOSIAL GURU NGAJI':
                case 'BANTUAN SOSIAL MARBOT MASJID KELURAHAN/KECAMATAN':
                    $to = 'Sekretaris Daerah';
                    break;
                case 'BANTUAN SOSIAL PAJAK BUMI DAN BANGUNAN':
                    $to = 'Kepala Badan Perencanaan dan Pembangunan Daerah';
                    break;
                case 'BANTUAN SOSIAL FM-332':
                    $to = 'Kepala Badan Amil Zakat Nasional';
                    break;
                default:
                    break;
            }
        }

        $templatePath = match ($formulir) {
            'formulir_permohonan_bantuan_biaya_awal_masuk' => 'templates/formulir_permohonan_bantuan_biaya_awal_masuk.docx',
            'formulir_permohonan_bantuan_kesehatan' => 'templates/formulir_permohonan_bantuan_kesehatan.docx',
            'formulir_permohonan_bantuan_uang_duka' => 'templates/formulir_permohonan_bantuan_uang_duka.docx',
            'formulir_permohonan_bantuan_perumahan' => 'templates/formulir_permohonan_bantuan_perumahan.docx',
            'formulir_permohonan_bantuan_tani_ternak' => 'templates/formulir_permohonan_bantuan_tani_ternak.docx',
            'formulir_permohonan_bantuan_perikanan' => 'templates/formulir_permohonan_bantuan_perikanan.docx',
            'formulir_permohonan_bantuan_umkm' => 'templates/formulir_permohonan_bantuan_umkm.docx',
            default => 'Template.docx',
        };
        $template = new TemplateProcessor(public_path($templatePath));

        // Default values
        $values = [
            'bantuan' => $programTitle,
            'pemohon' => data_get($dataRule, 'pemohon', '-'),
            'alamat_pemohon' => data_get($dataRule, 'alamat_pemohon', '-'),
            'pekerjaan_pemohon' => data_get($dataRule, 'pekerjaan_pemohon', '-'),
            'contact_pemohon' => data_get($dataRule, 'contact_pemohon', '-'),
            'nama' => data_get($data, 'nama', '-'),
            'tempat_lahir' => data_get($data, 'tempat_lahir', '-'),
            'tanggal_lahir' => ($tgl = data_get($data, 'tanggal_lahir')) && $tgl !== '-' ? Carbon::parse($tgl)->translatedFormat('d F Y') : '-',
            'jenis_kelamin' => data_get($data, 'jenis_kelamin.nama', '-'),
            'alamat' => data_get($data, 'alamat', '-'),
            'nik' => data_get($data, 'nik', '-'),
            'no_kk' => data_get($data, 'data_kk.no_kk', '-'),
            'tanda_tangan' => data_get($dataRule, 'pemohon', '-'),
            'cq' => $to,
            // Field khusus isBantuanDuka
            'is_bantuan_duka' => $isBantuanDuka,
        ];

        // Jika isBantuanDuka, tambahkan data penduduk (identitas sasaran meninggal dunia)
        if ($isBantuanDuka && $penduduk) {
            $values['penduduk_nama'] = data_get($penduduk, 'nama', '-');
            $values['penduduk_tempat_lahir'] = data_get($penduduk, 'tempat_lahir', '-');
            $tglPenduduk = data_get($penduduk, 'tanggal_lahir', '-');
            $values['penduduk_tanggal_lahir'] = ($tglPenduduk && $tglPenduduk !== '-') ? Carbon::parse($tglPenduduk)->translatedFormat('d F Y') : '-';
            $values['penduduk_jenis_kelamin'] = data_get($penduduk, 'jenis_kelamin.nama', '-');
            // Alamat penduduk: gabungan dusun, tempat_lahir, kecamatan.nama (lihat FormPermohonanSosial.jsx)
            $alamat_penduduk = collect([
                data_get($penduduk, 'dusun', null),
                data_get($penduduk, 'tempat_lahir', null),
                data_get($penduduk, 'kecamatan.nama', null)
            ])->filter()->implode(', ');
            $values['penduduk_alamat'] = $alamat_penduduk !== '' ? $alamat_penduduk : '-';
            $values['penduduk_nik'] = data_get($penduduk, 'nik', '-');
            $values['penduduk_no_kk'] = data_get($penduduk, 'data_kk.no_kk', '-');
        }

        // Set all values to template
        foreach ($values as $key => $val) {
            $template->setValue($key, $val);
        }

        return $template;
    }

    /**
     * Convert DOCX to PDF and return PDF file path
     */
    private function convertDocxToPdf($docxFile, $pdfFolder)
    {
        $phpWord = IOFactory::load($docxFile);

        // Simpan ke HTML dengan nama random di folder pdf
        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
        $randomName = 'temp_' . uniqid() . '.html';
        $htmlFile = $pdfFolder . '/' . $randomName;
        $htmlWriter->save($htmlFile);

        // Konversi HTML ke PDF
        $dompdf = new Dompdf();
        $dompdf->loadHtml(file_get_contents($htmlFile));
        $dompdf->setPaper('A4');
        $dompdf->render();

        $output = $dompdf->output();
        $randomPdfName = 'surat_' . uniqid() . '.pdf';
        $pdfFile = $pdfFolder . '/' . $randomPdfName;
        file_put_contents($pdfFile, $output);

        // Hapus file HTML setelah PDF jadi
        if (file_exists($htmlFile)) {
            unlink($htmlFile);
        }
        // Hapus file hasil.docx setelah PDF jadi
        if (file_exists($docxFile)) {
            unlink($docxFile);
        }

        return $pdfFile;
    }

    public function generatePdf(Request $request)
    {
        // --- Proses generate dokumen seperti fungsi pdf di atas ---
        $pdfFolder = storage_path('app/pdf');
        if (!file_exists($pdfFolder)) {
            mkdir($pdfFolder, 0777, true);
        }

        $template = new TemplateProcessor('formulir_permohonan.docx');

        // Set value dari data request, fallback ke string kosong jika tidak ada
        $template->setValue('nama', '-');
        $template->setValue('tanggal_lahir', '-');
        $template->setValue('alamat', '-');
        $template->setValue('ktp', '-');
        $template->setValue('tanda_tangan', '-');
        $template->setValue('tanggal', date('d F Y'));

        // Simpan docx ke folder pdf
        $docxFile = $pdfFolder . '/hasil_' . uniqid() . '.docx';
        $template->saveAs($docxFile);

        $phpWord = IOFactory::load($docxFile);

        // Simpan ke HTML dengan nama random di folder pdf
        $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
        $randomName = 'temp_' . uniqid() . '.html';
        $htmlFile = $pdfFolder . '/' . $randomName;
        $htmlWriter->save($htmlFile);

        // Konversi HTML ke PDF
        $dompdf = new Dompdf();
        $dompdf->loadHtml(file_get_contents($htmlFile));
        $dompdf->setPaper('A4');
        $dompdf->render();

        $output = $dompdf->output();
        $randomPdfName = 'surat_' . uniqid() . '.pdf';
        $pdfFile = $pdfFolder . '/' . $randomPdfName;
        file_put_contents($pdfFile, $output);

        // Hapus file HTML & docx setelah PDF jadi
        if (file_exists($htmlFile)) {
            unlink($htmlFile);
        }
        if (file_exists($docxFile)) {
            unlink($docxFile);
        }

        // Return temporary signed URL untuk download PDF
        if (file_exists($pdfFile)) {
            // Simpan file ke storage/public/pdf agar bisa diakses oleh Storage facade
            $publicPdfFolder = storage_path('app/public/pdf');
            if (!file_exists($publicPdfFolder)) {
                mkdir($publicPdfFolder, 0777, true);
            }
            $publicPdfFile = $publicPdfFolder . '/' . $randomPdfName;
            copy($pdfFile, $publicPdfFile);

            // Hapus file di folder pdf (bukan public/pdf)
            if (file_exists($pdfFile)) {
                unlink($pdfFile);
            }

            // Buat signed URL
            $url = URL::temporarySignedRoute(
                'download.pdf', now()->addMinutes(5), ['filename' => $randomPdfName]
            );

            return response()->json([
                'url' => $url,
                'filename' => $randomPdfName
            ]);
        } else {
            return response()->json(['error' => 'PDF file not found'], 404);
        }
    }

    /**
     * Validasi signature dan return file PDF dari temporary signed URL.
     * Setelah file diberikan, hapus file dengan fungsi deleteFileAfterSend.
     */
    public function downloadPdf(Request $request)
    {
        // Validasi signature
        if (!$request->hasValidSignature()) {
            return response()->json(['error' => 'Invalid or expired link'], 403);
        }

        $filename = $request->query('filename');
        if (!$filename) {
            return response()->json(['error' => 'Filename is required'], 400);
        }

        $publicPdfFolder = storage_path('app/public/pdf');
        $filePath = $publicPdfFolder . '/' . $filename;

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        // Return file response dan hapus file setelah dikirim
        return response()->file($filePath)->deleteFileAfterSend(true);
    }
}
