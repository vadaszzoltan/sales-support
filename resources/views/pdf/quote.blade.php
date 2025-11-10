<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quote {{ $quote->quote_number }}</title>
    <style>
        /* Basic styling for PDF - using inline CSS as DomPDF works best with inline styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.5;
        }

        /* Header section with company and customer info */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .header-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .header-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: right;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .company-details {
            font-size: 11px;
            color: #666;
        }

        .customer-label {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 11px;
        }

        .customer-details {
            font-size: 11px;
        }

        /* Quote info section */
        .quote-info {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f5f5f5;
        }

        .quote-info-row {
            margin-bottom: 5px;
        }

        .quote-info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }

        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th {
            background-color: #333;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
            font-weight: bold;
        }

        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }

        .items-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Item details (services and accessories) */
        .item-details {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
            padding-left: 10px;
        }

        .item-details ul {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .item-details li {
            margin-bottom: 2px;
        }

        /* Summary section */
        .summary {
            margin-top: 30px;
            width: 100%;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }

        .summary-table td:first-child {
            text-align: right;
            padding-right: 20px;
            font-weight: bold;
        }

        .summary-table td:last-child {
            text-align: right;
            font-weight: bold;
        }

        .summary-total {
            background-color: #333;
            color: white;
            font-size: 14px;
            font-weight: bold;
        }

        .summary-total td {
            padding: 12px;
        }

        /* Notes section */
        .notes {
            margin-top: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border-left: 3px solid #333;
        }

        .notes-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        /* Footer */
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }

        /* Page break */
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <!-- Header: Company and Customer Information -->
    <div class="header">
        <div class="header-left">
            <div class="company-name">Your Company Name</div>
            <div class="company-details">
                Company Address Line 1<br>
                Company Address Line 2<br>
                City, Postal Code, Country<br>
                Phone: +XX XXX XXX XXXX<br>
                Email: info@company.com<br>
                Tax ID: XX-XXX-XXX
            </div>
        </div>
        <div class="header-right">
            <div class="customer-label">CUSTOMER:</div>
            <div class="customer-details">
                <strong>{{ $quote->customer->name ?? 'N/A' }}</strong><br>
                @if($quote->customer)
                    @if($quote->customer->address)
                        {{ $quote->customer->address }}<br>
                    @endif
                    @if($quote->customer->city && $quote->customer->postal_code)
                        {{ $quote->customer->postal_code }} {{ $quote->customer->city }}<br>
                    @endif
                    @if($quote->customer->country)
                        {{ $quote->customer->country }}<br>
                    @endif
                    @if($quote->customer->email)
                        {{ $quote->customer->email }}<br>
                    @endif
                    @if($quote->customer->phone)
                        {{ $quote->customer->phone }}<br>
                    @endif
                    @if($quote->customer->tax_number)
                        Tax ID: {{ $quote->customer->tax_number }}
                    @endif
                @endif
            </div>
        </div>
    </div>

    <!-- Quote Information -->
    <div class="quote-info">
        <div class="quote-info-row">
            <span class="quote-info-label">Quote Number:</span>
            <span>{{ $quote->quote_number }}</span>
        </div>
        <div class="quote-info-row">
            <span class="quote-info-label">Quote Date:</span>
            <span>{{ $quote->quote_date->format('Y-m-d') }}</span>
        </div>
        @if($quote->valid_until)
            <div class="quote-info-row">
                <span class="quote-info-label">Valid Until:</span>
                <span>{{ $quote->valid_until->format('Y-m-d') }}</span>
            </div>
        @endif
        <div class="quote-info-row">
            <span class="quote-info-label">Status:</span>
            <span>{{ strtoupper($quote->status) }}</span>
        </div>
        @if($quote->version > 1)
            <div class="quote-info-row">
                <span class="quote-info-label">Version:</span>
                <span>V{{ $quote->version }}</span>
            </div>
        @endif
    </div>

    <!-- Items Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 25%;">Product</th>
                <th style="width: 10%;">Dimensions</th>
                <th style="width: 8%;" class="text-center">Quantity</th>
                <th style="width: 10%;" class="text-right">Unit Price</th>
                <th style="width: 12%;" class="text-right">Product Total</th>
                <th style="width: 15%;" class="text-right">Services</th>
                <th style="width: 10%;" class="text-right">Accessories</th>
                <th style="width: 10%;" class="text-right">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        {{-- PDFs must always use Romanian locale --}}
                        @php
                            $pdfLocale = config('locales.pdf_locale', 'ro');
                            $displayName = $item->getDisplayName($pdfLocale);
                        @endphp
                        <strong>{{ $displayName }}</strong>
                        @if($item->product && $item->product->code)
                            <br><small style="color: #666;">Code: {{ $item->product->code }}</small>
                        @endif
                        @if($item->custom_name && $item->product)
                            @php
                                $productNameRo = $item->product->getName($pdfLocale);
                            @endphp
                            <br><small style="color: #999; font-style: italic;">(Product: {{ $productNameRo }})</small>
                        @endif
                    </td>
                    <td>
                        @if($item->width_mm && $item->height_mm)
                            {{ $item->width_mm }} × {{ $item->height_mm }} mm
                            @if($item->surface_area_m2)
                                <br><small style="color: #666;">{{ number_format($item->surface_area_m2, 2) }} m²</small>
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">€{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">€{{ number_format($item->product_total ?? 0, 2) }}</td>
                    <td class="text-right">
                        @if($item->services->count() > 0)
                            <div class="item-details">
                                @foreach($item->services as $service)
                                    {{ $service->name }}: €{{ number_format($service->pivot->total ?? 0, 2) }}<br>
                                @endforeach
                            </div>
                            <strong>€{{ number_format($item->service_total ?? 0, 2) }}</strong>
                        @else
                            €0.00
                        @endif
                    </td>
                    <td class="text-right">
                        @if($item->accessories->count() > 0)
                            <div class="item-details">
                                @foreach($item->accessories as $accessory)
                                    {{ $accessory->name }}: €{{ number_format($accessory->pivot->total ?? 0, 2) }}<br>
                                @endforeach
                            </div>
                            <strong>€{{ number_format($item->accessory_total ?? 0, 2) }}</strong>
                        @else
                            €0.00
                        @endif
                    </td>
                    <td class="text-right"><strong>€{{ number_format($item->line_total ?? 0, 2) }}</strong></td>
                </tr>
                @if($item->notes)
                    <tr>
                        <td colspan="9" style="font-size: 10px; color: #666; padding-left: 20px;">
                            <em>Note: {{ $item->notes }}</em>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <!-- Summary Section -->
    <div class="summary">
        <table class="summary-table">
            <tr>
                <td>Subtotal (Products + Services + Accessories):</td>
                <td>€{{ number_format($quote->subtotal ?? 0, 2) }}</td>
            </tr>
            @if($quote->installation_cost > 0)
                <tr>
                    <td>Installation Cost (Manopera):</td>
                    <td>€{{ number_format($quote->installation_cost, 2) }}</td>
                </tr>
            @endif
            @if($quote->delivery_cost > 0)
                <tr>
                    <td>Delivery Cost 
                        @if($quote->delivery_distance_km)
                            ({{ number_format($quote->delivery_distance_km, 0) }} km)
                        @endif
                    :</td>
                    <td>€{{ number_format($quote->delivery_cost, 2) }}</td>
                </tr>
            @endif
            @if($quote->total_discount > 0)
                <tr>
                    <td>Discount 
                        @if($quote->discount_type === 'percentage')
                            ({{ number_format($quote->discount_value, 2) }}%)
                        @endif
                    :</td>
                    <td>-€{{ number_format($quote->total_discount, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td>VAT ({{ number_format($quote->vat_rate ?? 0, 2) }}%):</td>
                <td>€{{ number_format($quote->vat_amount ?? 0, 2) }}</td>
            </tr>
            <tr class="summary-total">
                <td>GRAND TOTAL:</td>
                <td>€{{ number_format($quote->total_amount ?? 0, 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- Notes Section -->
    @if($quote->notes)
        <div class="notes">
            <div class="notes-label">Additional Notes:</div>
            <div>{{ $quote->notes }}</div>
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This quote is valid until {{ $quote->valid_until ? $quote->valid_until->format('Y-m-d') : 'further notice' }}.</p>
        <p>Generated on {{ now()->format('Y-m-d H:i:s') }} by {{ $quote->user->name ?? 'System' }}</p>
        <p style="margin-top: 10px;">Thank you for your business!</p>
    </div>
</body>
</html>

