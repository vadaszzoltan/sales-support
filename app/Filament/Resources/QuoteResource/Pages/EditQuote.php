<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Services\QuotePdfService;
use App\Services\QuotePricingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generatePdf')
                ->label('Generate PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function () {
                    $quote = $this->record;
                    
                    // Recalculate totals to ensure PDF has accurate data
                    $pricingService = app(QuotePricingService::class);
                    $quote = $pricingService->recalculateAndSave($quote);
                    
                    // Generate PDF
                    $pdfService = app(QuotePdfService::class);
                    $pdfService->generatePdf($quote);
                    
                    // Refresh record to get updated PDF info
                    $quote->refresh();
                    
                    // Show success notification with download link
                    $pdfUrl = $pdfService->getDownloadUrl($quote);
                    
                    Notification::make()
                        ->title('PDF generated successfully!')
                        ->body('The quote PDF has been generated.')
                        ->success()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('download')
                                ->label('Download PDF')
                                ->url($pdfUrl, shouldOpenInNewTab: true)
                                ->button(),
                        ])
                        ->send();
                }),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
