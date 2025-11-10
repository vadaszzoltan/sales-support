<?php

namespace App\Services;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Service class for generating PDF documents from quotes.
 * 
 * This implements UC-S-01 step 10 from the SRS: "Generate a printable PDF document
 * from a finalized quote."
 * 
 * The PDF includes:
 * - Company details
 * - Customer details
 * - Quote number, date, and version
 * - Table of items with products, services, accessories
 * - Summary section with all totals
 */
class QuotePdfService
{
    /**
     * Generate a PDF document for a quote
     * 
     * Steps:
     * 1. Load the quote with all necessary relationships
     * 2. Render the PDF view template with quote data
     * 3. Generate PDF using DomPDF
     * 4. Save PDF to storage/app/public/quotes/
     * 5. Update quote record with PDF path and generation timestamp
     * 6. Return the file path for download
     * 
     * @param Quote $quote The quote to generate PDF for
     * @return string The storage path to the generated PDF file
     */
    public function generatePdf(Quote $quote): string
    {
        // Step 1: Load quote with all necessary relationships
        // We need: customer, user, items with products, services, and accessories
        $quote->loadMissing([
            'customer',
            'user',
            'items.product',
            'items.services',
            'items.accessories',
        ]);
        
        // IMPORTANT: PDFs must ALWAYS be generated in Romanian
        // Set the locale for PDF generation
        $pdfLocale = config('locales.pdf_locale', 'ro');
        app()->setLocale($pdfLocale);

        // Step 2: Ensure the quotes directory exists in storage
        $quotesDirectory = 'quotes';
        if (!Storage::disk('public')->exists($quotesDirectory)) {
            Storage::disk('public')->makeDirectory($quotesDirectory);
        }

        // Step 3: Generate the PDF filename based on quote number
        // Format: AJ-2024-00123-V2.pdf
        $filename = $quote->quote_number . '.pdf';
        $filePath = $quotesDirectory . '/' . $filename;

        // Step 4: Render the PDF view template
        // The view will receive the $quote object with all its relationships
        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
        ]);

        // Step 5: Configure PDF options
        // Set paper size to A4 and ensure UTF-8 encoding for special characters
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isRemoteEnabled', true);
        $pdf->setOption('defaultFont', 'DejaVu Sans'); // Supports UTF-8 characters (ș, ț, ă, etc.)
        $pdf->setOption('enableFontSubsetting', true);
        $pdf->setOption('enableUnicode', true);

        // Step 6: Save PDF to storage
        // This saves the file to storage/app/public/quotes/{quote_number}.pdf
        Storage::disk('public')->put($filePath, $pdf->output());

        // Step 7: Update quote record with PDF information
        $quote->update([
            'pdf_path' => $filePath,
            'pdf_generated_at' => now(),
        ]);

        // Step 8: Return the full storage path
        // This can be used to generate download URLs
        return $filePath;
    }

    /**
     * Get the download URL for a quote's PDF
     * 
     * @param Quote $quote The quote
     * @return string|null The download URL, or null if PDF doesn't exist
     */
    public function getDownloadUrl(Quote $quote): ?string
    {
        if (!$quote->pdf_path || !Storage::disk('public')->exists($quote->pdf_path)) {
            return null;
        }

        // Return the public URL to the PDF file
        return Storage::disk('public')->url($quote->pdf_path);
    }

    /**
     * Check if a PDF exists for a quote
     * 
     * @param Quote $quote The quote
     * @return bool True if PDF exists, false otherwise
     */
    public function pdfExists(Quote $quote): bool
    {
        if (!$quote->pdf_path) {
            return false;
        }

        return Storage::disk('public')->exists($quote->pdf_path);
    }

    /**
     * Delete the PDF file for a quote
     * 
     * @param Quote $quote The quote
     * @return bool True if deleted, false if file didn't exist
     */
    public function deletePdf(Quote $quote): bool
    {
        if (!$quote->pdf_path) {
            return false;
        }

        if (Storage::disk('public')->exists($quote->pdf_path)) {
            Storage::disk('public')->delete($quote->pdf_path);
            $quote->update([
                'pdf_path' => null,
                'pdf_generated_at' => null,
            ]);
            return true;
        }

        return false;
    }
}

