<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;

class FileController extends Controller
{
    public function pdf()
    {
        // Pastikan folder pdf ada
        $pdfFolder = storage_path('app/public/pdf');
        if (!file_exists($pdfFolder)) {
            mkdir($pdfFolder, 0777, true);
        }

        $template = new TemplateProcessor('Template.docx');

        $template->setValue('nama', 'Gatau apa namanya');
        $template->setValue('tanggal_lahir', '1 Januari 1990');
        $template->setValue('alamat', 'Jl. Merdeka No. 123');
        $template->setValue('ktp', '1234567890123456');
        $template->setValue('tanda_tangan', 'Gatau apa namanya');
        $template->setValue('tanggal', date('d F Y'));

        // Simpan docx ke folder pdf
        $docxFile = $pdfFolder . '/hasil.docx';
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

        // Hapus file HTML setelah PDF jadi
        if (file_exists($htmlFile)) {
            unlink($htmlFile);
        }
        // Hapus file hasil.docx setelah PDF jadi
        if (file_exists($docxFile)) {
            unlink($docxFile);
        }

        // Stream PDF file ke response
        if (file_exists($pdfFile)) {
            return response()->download($pdfFile)->deleteFileAfterSend(true);
        } else {
            return response()->json(['error' => 'PDF file not found'], 404);
        }
    }

    public function generateAndDownload(Request $request)
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

        // Stream PDF file ke response, sertakan nama file di header
        if (file_exists($pdfFile)) {
            return response()->download($pdfFile, $randomPdfName, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $randomPdfName . '"'
            ])->deleteFileAfterSend(true);
        } else {
            return response()->json(['error' => 'PDF file not found'], 404);
        }
    }

    // Untuk handle delete file PDF dari FE
    public function delete($filename)
    {
        $pdfFolder = storage_path('app/pdf');
        $filePath = $pdfFolder . '/' . basename($filename);

        if (file_exists($filePath)) {
            unlink($filePath);
            return response()->json(['success' => true]);
        } else {
            return response()->json(['error' => 'File not found'], 404);
        }
    }
}
