<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductServicePricing;
use App\Models\Service;
use Illuminate\Database\Seeder;

class BaseServicesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates default services with correct pricing modes and example prices.
     * This seeder is idempotent - running it multiple times won't create duplicates.
     */
    public function run(): void
    {
        // Define default services with their pricing modes
        $services = [
            [
                'name' => 'Kivágás',
                'code' => 'KIV',
                'pricing_mode' => Service::PRICING_PER_PIECE,
                'unit_of_measure' => 'db',
                'description' => 'Cutting service - charged per piece',
            ],
            [
                'name' => 'Fúrás',
                'code' => 'FUR',
                'pricing_mode' => Service::PRICING_PER_PIECE,
                'unit_of_measure' => 'db',
                'description' => 'Drilling service - charged per piece',
            ],
            [
                'name' => 'Fólia nyomtatás',
                'code' => 'FOL',
                'pricing_mode' => Service::PRICING_PER_SQM,
                'unit_of_measure' => 'm2',
                'description' => 'Foil printing service - charged per square meter',
            ],
            [
                'name' => 'Üveg',
                'code' => 'UVE',
                'pricing_mode' => Service::PRICING_PER_SQM,
                'unit_of_measure' => 'm2',
                'description' => 'Glass service - charged per square meter',
            ],
            [
                'name' => 'Csiszolás',
                'code' => 'CSI',
                'pricing_mode' => Service::PRICING_PER_LM,
                'unit_of_measure' => 'm',
                'description' => 'Polishing service - charged per linear meter',
            ],
            [
                'name' => 'Edzés',
                'code' => 'EDZ',
                'pricing_mode' => Service::PRICING_PER_SQM,
                'unit_of_measure' => 'm2',
                'description' => 'Tempering service - charged per square meter',
            ],
            [
                'name' => 'Folie Sablat',
                'code' => 'FSA',
                'pricing_mode' => Service::PRICING_PER_SQM,
                'unit_of_measure' => 'm2',
                'description' => 'Foil template service - charged per square meter',
            ],
            [
                'name' => 'Vopsit',
                'code' => 'VOP',
                'pricing_mode' => Service::PRICING_PER_SQM,
                'unit_of_measure' => 'm2',
                'description' => 'Vopsit service - charged per square meter',
            ],
        ];

        // Create or update services
        foreach ($services as $serviceData) {
            $service = Service::updateOrCreate(
                ['name' => $serviceData['name']], // Find by name
                [
                    'code' => $serviceData['code'],
                    'pricing_mode' => $serviceData['pricing_mode'],
                    'unit_of_measure' => $serviceData['unit_of_measure'],
                    'description' => $serviceData['description'],
                    'is_active' => true,
                ]
            );

            $this->command->info("Service '{$service->name}' created/updated with pricing mode: {$service->pricing_mode}");
        }

        // Set example prices for "Üveg" service if products exist
        $uvegService = Service::where('name', 'Üveg')->first();
        
        if ($uvegService) {
            // Find Float 4 product
            $float4 = Product::where('name', 'Float 4')->orWhere('name', 'Float4')->first();
            if ($float4) {
                ProductServicePricing::updateOrCreate(
                    [
                        'product_id' => $float4->id,
                        'service_id' => $uvegService->id,
                    ],
                    [
                        'price_per_unit' => 19.7,
                        'unit_type' => 'sqm', // Maps to PRICING_PER_SQM
                    ]
                );
                $this->command->info("Set Üveg price for Float 4: 19.7 €/m²");
            }

            // Find Float 6 product
            $float6 = Product::where('name', 'Float 6')->orWhere('name', 'Float6')->first();
            if ($float6) {
                ProductServicePricing::updateOrCreate(
                    [
                        'product_id' => $float6->id,
                        'service_id' => $uvegService->id,
                    ],
                    [
                        'price_per_unit' => 30.7,
                        'unit_type' => 'sqm', // Maps to PRICING_PER_SQM
                    ]
                );
                $this->command->info("Set Üveg price for Float 6: 30.7 €/m²");
            }
        }

        $this->command->info('Base services seeder completed successfully!');
    }
}
