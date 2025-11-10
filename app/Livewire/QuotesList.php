<?php

namespace App\Livewire;

use App\Models\Quote;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class QuotesList extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $customerFilter = '';

    /**
     * Get quotes based on user role
     * - Sales users see only their own quotes
     * - Admin users see all quotes
     */
    public function getQuotesProperty()
    {
        $query = Quote::with(['customer', 'user'])
            ->orderBy('quote_date', 'desc')
            ->orderBy('version', 'desc');

        // Sales users can only see their own quotes
        if (Auth::user()->isSalesAgent()) {
            $query->where('user_id', Auth::id());
        }
        // Admin users see all quotes (no filter)

        // Apply search filter (quote number or customer name)
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('quote_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('customer', function ($customerQuery) {
                      $customerQuery->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Apply status filter
        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        // Apply customer filter
        if ($this->customerFilter) {
            $query->where('customer_id', $this->customerFilter);
        }

        return $query->paginate(15);
    }

    /**
     * Reset filters
     */
    public function resetFilters()
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->customerFilter = '';
        $this->resetPage();
    }

    /**
     * Delete a quote (with authorization check)
     */
    public function deleteQuote($quoteId)
    {
        $quote = Quote::findOrFail($quoteId);
        
        // Check authorization using policy
        $this->authorize('delete', $quote);
        
        $quote->delete();
        
        session()->flash('message', 'Quote deleted successfully.');
    }

    public function render()
    {
        return view('livewire.quotes-list', [
            'quotes' => $this->quotes,
            'statuses' => $this->getStatuses(),
            'customers' => $this->getCustomers(),
        ]);
    }

    /**
     * Get available statuses from system settings or default list
     */
    protected function getStatuses(): array
    {
        $statuses = \App\Models\SystemSetting::getValue('quote_statuses', [
            'draft',
            'sent',
            'under_review',
            'accepted',
            'rejected',
            'closed',
        ]);

        return array_combine($statuses, array_map('ucfirst', $statuses));
    }

    /**
     * Get customers for filter dropdown
     */
    protected function getCustomers()
    {
        $query = \App\Models\Customer::where('is_active', true)
            ->orderBy('name');

        // Sales users only see customers from their quotes
        if (Auth::user()->isSalesAgent()) {
            $customerIds = Quote::where('user_id', Auth::id())
                ->distinct()
                ->pluck('customer_id');
            $query->whereIn('id', $customerIds);
        }

        return $query->get();
    }
}
