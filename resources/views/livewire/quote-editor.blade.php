<div>
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm rounded-lg">
            <div class="p-6">
                <!-- Header -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">
                        {{ $quoteId ? 'Edit Quote' : 'Create New Quote' }}
                    </h2>
                    <div class="flex space-x-2">
                        @if($quoteId)
                            <button wire:click="generatePdf" 
                                    class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md">
                                Generate PDF
                            </button>
                        @endif
                        <a href="{{ route('sales.quotes.index') }}" 
                           class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-md">
                            Back to List
                        </a>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                @if (session()->has('message'))
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        {{ session('message') }}
                    </div>
                @endif

                @if($pdfGenerated)
                    <div class="mb-4 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                        PDF generated successfully! 
                        <a href="{{ $pdfDownloadUrl }}" target="_blank" class="underline font-semibold">Download PDF</a>
                    </div>
                @endif

                <form wire:submit.prevent="save">
                    <!-- Quote Information -->
                    <div class="mb-6 border-b pb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quote Information</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Quote Number (read-only if editing) -->
                            @if($quoteId)
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quote Number</label>
                                    <input type="text" 
                                           value="{{ $quoteNumber }}" 
                                           readonly
                                           class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-600">
                                </div>
                            @endif

                            <!-- Customer -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Customer <span class="text-red-500">*</span>
                                </label>
                                <select wire:model="customerId" 
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select Customer...</option>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                    @endforeach
                                </select>
                                @error('customerId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                <button type="button" 
                                        wire:click="$set('showCustomerModal', true)"
                                        class="mt-1 text-xs text-blue-600 hover:text-blue-800">
                                    + Create New Customer
                                </button>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select wire:model="status" 
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    @foreach($statuses as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Quote Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Quote Date</label>
                                <input type="date" 
                                       wire:model="quoteDate" 
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <!-- Valid Until -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Valid Until</label>
                                <input type="date" 
                                       wire:model="validUntil" 
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>

                    <!-- Items Section -->
                    <div class="mb-6 border-b pb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900">Items</h3>
                            <button type="button" 
                                    wire:click="addItem"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
                                Add Item
                            </button>
                        </div>

                        @if(empty($items))
                            <p class="text-gray-500 italic">No items added yet. Click "Add Item" to get started.</p>
                        @else
                            <div class="space-y-6">
                                @foreach($items as $index => $item)
                                    @php
                                        $tempId = $item['temp_id'] ?? null;
                                    @endphp
                                    <div wire:key="item-{{ $tempId ?? $index }}" class="border rounded-lg p-4 bg-gray-50">
                                        <!-- Item Header -->
                                        <div class="flex justify-between items-center mb-4">
                                            <h4 class="font-medium text-gray-900">Item #{{ $index + 1 }}</h4>
                                            @if(count($items) > 1)
                                                <button type="button" 
                                                        wire:click="removeItem('{{ $tempId }}')"
                                                        wire:confirm="Are you sure you want to remove this item?"
                                                        class="text-red-600 hover:text-red-800 text-sm">
                                                    Remove
                                                </button>
                                            @endif
                                        </div>

                                        <!-- Product and Quantity -->
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                            <!-- Product -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Product <span class="text-red-500">*</span>
                                                </label>
                                                <select wire:model.live="items.{{ $index }}.product_id" 
                                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                    <option value="">Select Product...</option>
                                                    @foreach($products as $product)
                                                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                                                    @endforeach
                                                </select>
                                                @error("items.{$index}.product_id") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                            <!-- Custom Display Name -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Custom Display Name
                                                    <span class="text-xs text-gray-500 font-normal">(Optional)</span>
                                                </label>
                                                <input type="text" 
                                                       wire:model="items.{{ $index }}.custom_name" 
                                                       placeholder="e.g., Custom Glass Panel - Office Window"
                                                       maxlength="255"
                                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <p class="text-xs text-gray-500 mt-1">
                                                    Override the product name with a custom name for this quote item. Leave empty to use the product name.
                                                </p>
                                            </div>

                                            <!-- Quantity -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Quantity <span class="text-red-500">*</span>
                                                </label>
                                                <input type="number" 
                                                       wire:model.live.debounce.300ms="items.{{ $index }}.quantity" 
                                                       step="0.01" 
                                                       min="0.01"
                                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                @error("items.{$index}.quantity") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>

                                            <!-- Width (mm) -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Width (mm)</label>
                                                <input type="number" 
                                                       wire:model.live.debounce.300ms="items.{{ $index }}.width_mm" 
                                                       min="0"
                                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>

                                            <!-- Height (mm) -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Height (mm)</label>
                                                <input type="number" 
                                                       wire:model.live.debounce.300ms="items.{{ $index }}.height_mm" 
                                                       min="0"
                                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                            </div>
                                        </div>

                                        <!-- Calculated Area, Linear Meter and Pricing -->
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                            <!-- Calculated Area (m²) - Read-only -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Area (m²)</label>
                                                <input type="text" 
                                                       value="{{ isset($item['calculated_area']) && $item['calculated_area'] !== null ? number_format($item['calculated_area'], 2) : '-' }}"
                                                       readonly
                                                       class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-600 cursor-not-allowed">
                                                <p class="text-xs text-gray-500 mt-1">Auto-calculated: (width × height) / 1,000,000 × quantity</p>
                                            </div>

                                            <!-- Calculated Linear Meter (lm) - Read-only -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Linear Meter (lm)</label>
                                                <input type="text" 
                                                       value="{{ isset($item['calculated_linear_meter']) && $item['calculated_linear_meter'] !== null ? number_format($item['calculated_linear_meter'], 2) : '-' }}"
                                                       readonly
                                                       class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-600 cursor-not-allowed">
                                                <p class="text-xs text-gray-500 mt-1">Auto-calculated: 2 × (width + height) / 1000 × quantity</p>
                                            </div>

                                            <!-- Unit Price (€/m²) -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price (€/m²)</label>
                                                <input type="number" 
                                                       wire:model.live.debounce.300ms="items.{{ $index }}.unit_price" 
                                                       step="0.01" 
                                                       min="0"
                                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                                <p class="text-xs text-gray-500 mt-1">Price per square meter</p>
                                            </div>

                                            <!-- Line Total (€) - Read-only -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Line Total (€)</label>
                                                <input type="text" 
                                                       value="€{{ isset($item['calculated_line_total']) ? number_format($item['calculated_line_total'], 2) : '0.00' }}"
                                                       readonly
                                                       class="w-full rounded-md border-gray-300 shadow-sm bg-gray-50 text-gray-900 font-semibold cursor-not-allowed">
                                                <p class="text-xs text-gray-500 mt-1">Auto-calculated: area × unit_price × quantity</p>
                                            </div>
                                        </div>

                                @if($item['product_id'])
                                    <!-- Compatible Services -->
                                    <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                                        <label class="block text-sm font-medium text-gray-700 mb-3">Services</label>
                                        
                                        @php
                                            $compatibleServices = $this->getCompatibleServices($item['product_id']);
                                            $serviceCalcService = app(\App\Services\ServiceCalculationService::class);
                                        @endphp
                                        
                                        @if($compatibleServices->isEmpty())
                                            <p class="text-sm text-gray-500 italic">No services available for this product.</p>
                                        @else
                                            <div class="space-y-3">
                                                @foreach($compatibleServices as $service)
                                                    @php
                                                        $serviceId = $service->id;
                                                        $isSelected = isset($item['selected_services'][$serviceId]);
                                                        $serviceData = $item['selected_services'][$serviceId] ?? null;
                                                        $isEnabled = $serviceData['enabled'] ?? false;
                                                        $unitType = $serviceData['unit_type'] ?? ($service->productPricing->first()->unit_type ?? 'sqm');
                                                        $isToggleable = $serviceCalcService->isToggleable($service);
                                                        
                                                        // Get unit label
                                                        $unitLabel = match($unitType) {
                                                            'piece' => 'piece',
                                                            'sqm' => 'm²',
                                                            'lm' => 'lm',
                                                            default => 'unit'
                                                        };
                                                        
                                                        // Calculate service total for display
                                                        $serviceTotal = 0;
                                                        if ($isSelected && $isEnabled) {
                                                            $tempItem = new \App\Models\QuoteItem();
                                                            $tempItem->product_id = $item['product_id'];
                                                            $tempItem->quantity = (float) ($item['quantity'] ?? 1);
                                                            $tempItem->width_mm = $item['width_mm'] ?? null;
                                                            $tempItem->height_mm = $item['height_mm'] ?? null;
                                                            $tempItem->surface_area_m2 = $item['calculated_area'] ?? null;
                                                            $tempItem->linear_meter = $item['calculated_linear_meter'] ?? null;
                                                            
                                                            $serviceTotal = $serviceCalcService->calculateServiceTotal(
                                                                $tempItem,
                                                                $service,
                                                                $isEnabled,
                                                                isset($serviceData['quantity']) ? (float) $serviceData['quantity'] : null,
                                                                isset($serviceData['price_per_unit']) ? (float) $serviceData['price_per_unit'] : null
                                                            );
                                                        }
                                                    @endphp
                                                    
                                                    <div class="border rounded-lg p-3 {{ $isSelected && $isEnabled ? 'bg-white border-blue-300' : 'bg-gray-50 border-gray-200' }}">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <label class="flex items-center space-x-2 cursor-pointer">
                                                                <input type="checkbox" 
                                                                       wire:click="toggleService({{ $index }}, {{ $serviceId }})"
                                                                       {{ $isSelected && $isEnabled ? 'checked' : '' }}
                                                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                                <span class="text-sm font-medium text-gray-700">{{ $service->name }}</span>
                                                            </label>
                                                            @if($isSelected && $isEnabled)
                                                                <span class="text-sm font-semibold text-green-600">
                                                                    €{{ number_format($serviceTotal, 2) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        
                                                        @if($isSelected)
                                                            <div class="ml-6 mt-2 space-y-2">
                                                                <div class="flex items-center space-x-4 text-xs text-gray-600">
                                                                    <span>Unit: <strong>{{ $unitLabel }}</strong></span>
                                                                    <span>Price: <strong>€{{ number_format($serviceData['price_per_unit'] ?? 0, 2) }}/{{ $unitLabel }}</strong></span>
                                                                </div>
                                                                
                                                                @if($unitType === 'piece' || $unitType === 'sqm')
                                                                    <!-- Show quantity input for piece-based and m²-based services (for override) -->
                                                                    <div class="flex items-center space-x-2">
                                                                        <label class="text-xs text-gray-600 w-20">Quantity:</label>
                                                                        <input type="number" 
                                                                               wire:model.live.debounce.300ms="items.{{ $index }}.selected_services.{{ $serviceId }}.quantity"
                                                                               step="0.01" 
                                                                               min="0"
                                                                               class="w-24 rounded-md border-gray-300 shadow-sm text-sm">
                                                                        <span class="text-xs text-gray-500">{{ $unitLabel }}</span>
                                                                    </div>
                                                                @elseif($unitType === 'lm')
                                                                    <!-- Linear meter is auto-calculated, show info -->
                                                                    <div class="text-xs text-gray-500 italic">
                                                                        Auto-calculated from dimensions: {{ $item['calculated_linear_meter'] ? number_format($item['calculated_linear_meter'], 2) . ' lm' : 'N/A' }}
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Compatible Accessories -->
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Accessories</label>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                            @foreach($this->getCompatibleAccessories($item['product_id']) as $accessory)
                                                <label class="flex items-center space-x-2 p-2 border rounded cursor-pointer hover:bg-gray-50">
                                                    <input type="checkbox" 
                                                           wire:click="toggleAccessory({{ $index }}, {{ $accessory->id }})"
                                                           {{ isset($item['selected_accessories'][$accessory->id]) ? 'checked' : '' }}
                                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    <span class="text-sm">{{ $accessory->name }}</span>
                                                </label>
                                                @if(isset($item['selected_accessories'][$accessory->id]))
                                                    <div class="col-span-full ml-6 mb-2">
                                                        <label class="text-xs text-gray-600">Quantity:</label>
                                                        <input type="number" 
                                                               wire:model.live.debounce.300ms="items.{{ $index }}.selected_accessories.{{ $accessory->id }}.quantity"
                                                               wire:change="recalculateTotals"
                                                               step="0.01" 
                                                               min="0.01"
                                                               class="w-24 ml-2 rounded-md border-gray-300 shadow-sm text-sm">
                                                        <span class="text-xs text-gray-500 ml-2">
                                                            Price: €{{ number_format(\App\Models\Accessory::find($accessory->id)->uniform_price ?? 0, 2) }}/unit
                                                        </span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Item Notes -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Item Notes</label>
                                    <textarea wire:model="items.{{ $index }}.notes" 
                                              rows="2" 
                                              class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"></textarea>
                                </div>
                            </div>
                        @endforeach
                        @endif
                    </div>

                    <!-- Summary Section -->
                    <div class="mb-6 border-b pb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Summary</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Delivery Distance -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Distance (km)</label>
                                <input type="number" 
                                       wire:model.live.debounce.300ms="deliveryDistanceKm" 
                                       step="0.01" 
                                       min="0"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <!-- Installation Multiplier Override -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Installation Multiplier Override (%)</label>
                                <input type="number" 
                                       wire:model.live.debounce.300ms="installationMultiplierOverride" 
                                       step="0.01" 
                                       min="0"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Leave empty to use system default</p>
                            </div>

                            <!-- Discount Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Discount Type</label>
                                <select wire:model.live="discountType" 
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="none">No Discount</option>
                                    <option value="fixed">Fixed Amount</option>
                                    <option value="percentage">Percentage</option>
                                </select>
                            </div>

                            <!-- Discount Value -->
                            @if($discountType !== 'none')
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Discount Value</label>
                                    <input type="number" 
                                           wire:model.live.debounce.300ms="discountValue" 
                                           step="0.01" 
                                           min="0"
                                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </div>
                            @endif

                            <!-- VAT Rate -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">VAT Rate (%)</label>
                                <input type="number" 
                                       wire:model.live.debounce.300ms="vatRate" 
                                       step="0.01" 
                                       min="0"
                                       class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>

                        <!-- Totals (Read-only) -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Subtotal</label>
                                    <div class="text-lg font-semibold text-gray-900">€{{ number_format($subtotal, 2) }}</div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Installation</label>
                                    <div class="text-lg font-semibold text-gray-900">€{{ number_format($installationCost, 2) }}</div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Delivery</label>
                                    <div class="text-lg font-semibold text-gray-900">€{{ number_format($deliveryCost, 2) }}</div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Discount</label>
                                    <div class="text-lg font-semibold text-red-600">-€{{ number_format($totalDiscount, 2) }}</div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">VAT ({{ $vatRate }}%)</label>
                                    <div class="text-lg font-semibold text-gray-900">€{{ number_format($vatAmount, 2) }}</div>
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-600 mb-1">Grand Total</label>
                                    <div class="text-2xl font-bold text-green-600">€{{ number_format($totalAmount, 2) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quote Notes</label>
                        <textarea wire:model="notes" 
                                  rows="3" 
                                  class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-4">
                        <a href="{{ route('sales.quotes.index') }}" 
                           class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-md">
                            Cancel
                        </a>
                        @if($quoteId)
                            <button type="button" 
                                    wire:click="duplicateQuote"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md">
                                Create New Version
                            </button>
                        @endif
                        <button type="submit" 
                                class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded-md">
                            Save Quote
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Customer Modal -->
    @if($showCustomerModal)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" wire:click="$set('showCustomerModal', false)">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white" wire:click.stop>
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Create New Customer</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                            <input type="text" 
                                   wire:model="newCustomerName" 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('newCustomerName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" 
                                   wire:model="newCustomerEmail" 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('newCustomerEmail') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" 
                                   wire:model="newCustomerPhone" 
                                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @error('newCustomerPhone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" 
                                    wire:click="$set('showCustomerModal', false)"
                                    class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-md">
                                Cancel
                            </button>
                            <button type="button" 
                                    wire:click="createCustomer"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-md">
                                Create
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
