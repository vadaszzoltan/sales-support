<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Sales quote management routes (accessible to both Sales and Admin users)
Route::middleware(['auth', 'verified'])->prefix('sales')->name('sales.')->group(function () {
    Route::get('/quotes', \App\Livewire\QuotesList::class)
        ->name('quotes.index');
    
    Route::get('/quotes/create', \App\Livewire\QuoteEditor::class)
        ->name('quotes.create');
    
    Route::get('/quotes/{quote}/edit', \App\Livewire\QuoteEditor::class)
        ->name('quotes.edit');
    
    // PDF download route
    Route::get('/quotes/{quote}/pdf', function (\App\Models\Quote $quote) {
        // Check authorization
        abort_unless(auth()->user()->isAdmin() || auth()->id() === $quote->user_id, 403);
        
        $pdfService = app(\App\Services\QuotePdfService::class);
        
        // If PDF doesn't exist, generate it
        if (!$pdfService->pdfExists($quote)) {
            $pdfService->generatePdf($quote);
            $quote->refresh();
        }
        
        // Return PDF download
        $pdfPath = $quote->pdf_path;
        if ($pdfPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($pdfPath)) {
            return \Illuminate\Support\Facades\Storage::disk('public')->download(
                $pdfPath,
                $quote->quote_number . '.pdf'
            );
        }
        
        abort(404, 'PDF not found');
    })->name('quotes.pdf');
});

require __DIR__.'/auth.php';
