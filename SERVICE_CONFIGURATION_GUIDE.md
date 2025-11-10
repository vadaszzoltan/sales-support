# Service Configuration Guide

## Summary of Changes

All service configuration is now managed from **ONE place**: the Service form itself.

### ✅ What Was Done:

1. **Removed separate admin pages:**
   - Product-Service Compatibility (hidden from navigation)
   - Product-Service Pricing (hidden from navigation)

2. **Added to Service form:**
   - **Pricing Mode** selector (per m², per lm, per piece)
   - **Product Prices** section with inline management (Repeater)

3. **Created base services:**
   - 8 default services with correct pricing modes
   - Example prices for "Üveg" service (Float 4 = 19.7 €/m², Float 6 = 30.7 €/m²)

---

## How to Use the Service Form

### Step 1: Access Services
1. Go to `/admin/services`
2. You should see a table with all services
3. Look for the **"Pricing Mode"** column (it shows badges: "Per m²", "Per lm", "Per piece")

### Step 2: Create a New Service
1. Click **"New Service"** button
2. Fill in the **Service Information** section:
   - **Service Name** (required)
   - **Service Code** (optional)
   - **Pricing Mode** (required) - Choose one:
     - Per square meter (m²)
     - Per linear meter (lm)
     - Per piece
   - **Unit of Measure** (required)
   - **Description** (optional)
   - **Active** toggle

3. In the **Product Prices** section:
   - Click **"Add Product Price"** button
   - Select a **Product** from the dropdown
   - Enter the **Unit Price** (e.g., 19.7)
   - The helper text shows the unit based on your pricing mode
   - Add more products as needed
   - Remove products by clicking the delete icon

4. Click **"Create"** - Everything is saved!

### Step 3: Edit an Existing Service
1. Click **"Edit"** on any service in the table
2. You'll see:
   - **Service Information** section with current values
   - **Product Prices** section (may be collapsed) showing existing product prices
3. Make your changes:
   - Change pricing mode if needed
   - Add/remove/edit product prices
4. Click **"Save"** - All changes are saved!

---

## What You Should See

### In the Services List Table:
- **Name** column
- **Code** column (toggleable)
- **Pricing Mode** column (NEW!) - Shows badges:
  - Blue "Per m²" for square meter services
  - Yellow "Per lm" for linear meter services
  - Green "Per piece" for piece-based services
- **Unit** column (toggleable)
- **Active** column

### In the Service Form (Create/Edit):
1. **Service Information** section:
   - Service Name
   - Service Code
   - **Pricing Mode** (NEW!) - Dropdown with 3 options
   - Unit of Measure
   - Description
   - Active toggle

2. **Product Prices** section (NEW!):
   - Collapsible section
   - "Add Product Price" button
   - For each product price row:
     - Product dropdown
     - Unit Price input (with € prefix)
     - Helper text showing the unit (e.g., "Price per m²")
     - Delete button

---

## Troubleshooting

### If you don't see the changes:

1. **Hard refresh your browser:**
   - Mac: `Cmd + Shift + R`
   - Windows/Linux: `Ctrl + Shift + R`

2. **Clear browser cache:**
   - Open browser settings
   - Clear cache and cookies
   - Reload the page

3. **Check the URL:**
   - Make sure you're at `/admin/services`
   - Not at `/admin/product-service-pricing` or `/admin/product-service-compatibility`

4. **Verify the data:**
   Run this command to check if services have pricing_mode:
   ```bash
   ./vendor/bin/sail artisan tinker --execute="foreach (\App\Models\Service::all() as \$s) { echo \$s->name . ' -> ' . (\$s->pricing_mode ?? 'NULL') . PHP_EOL; }"
   ```

5. **Check browser console:**
   - Press F12 to open developer tools
   - Look for any JavaScript errors
   - Check the Network tab for failed requests

---

## Files Changed

1. **Migration:** `database/migrations/2025_11_06_183232_add_pricing_mode_to_services_table.php`
   - Adds `pricing_mode` column to `services` table

2. **Model:** `app/Models/Service.php`
   - Added `pricing_mode` to `$fillable`
   - Added constants: `PRICING_PER_SQM`, `PRICING_PER_LM`, `PRICING_PER_PIECE`

3. **Resource:** `app/Filament/Resources/ServiceResource.php`
   - Added "Pricing Mode" field
   - Added "Product Prices" section with Repeater
   - Added "Pricing Mode" column to table

4. **Pages:**
   - `app/Filament/Resources/ServiceResource/Pages/CreateService.php` - Handles saving product prices
   - `app/Filament/Resources/ServiceResource/Pages/EditService.php` - Syncs unit_type after save

5. **Hidden Resources:**
   - `app/Filament/Resources/ProductServiceCompatibilityResource.php` - Hidden from navigation
   - `app/Filament/Resources/ProductServicePricingResource.php` - Hidden from navigation

6. **Seeder:** `database/seeders/BaseServicesSeeder.php`
   - Creates 8 default services with correct pricing modes
   - Sets example prices for "Üveg" service

---

## Example: Creating a Service

1. Go to `/admin/services` → Click "New Service"

2. Fill in:
   - **Service Name:** "Cutting"
   - **Pricing Mode:** "Per piece"
   - **Unit of Measure:** "Piece (db)"

3. In "Product Prices" section:
   - Click "Add Product Price"
   - Select "Float 4" from Product dropdown
   - Enter "5.00" in Unit Price
   - Click "Add Product Price" again
   - Select "Float 6" from Product dropdown
   - Enter "7.50" in Unit Price

4. Click "Create"

5. Done! The service is created with both product prices saved.

---

## Example: Editing "Üveg" Service

1. Go to `/admin/services`
2. Find "Üveg" in the table
3. Click "Edit"
4. You should see:
   - **Pricing Mode:** "Per square meter (m²)" (already selected)
   - **Product Prices** section (expand it if collapsed)
   - Two existing product prices:
     - Float4: €19.70
     - Float6: €30.70
5. You can:
   - Add more products
   - Edit existing prices
   - Remove products
6. Click "Save" when done

---

## Quick Verification Checklist

- [ ] Go to `/admin/services` - Do you see the "Pricing Mode" column?
- [ ] Click "Edit" on "Üveg" - Do you see the "Pricing Mode" field?
- [ ] In the "Product Prices" section - Do you see existing product prices (Float4, Float6)?
- [ ] Click "Add Product Price" - Does a new row appear?
- [ ] Check the sidebar - Are "Product-Service Compatibility" and "Product-Service Pricing" missing?

If all checkboxes are ✅, everything is working correctly!

