<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Tenant;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $demoTenant = Tenant::where('subdomain', 'demo')->first();
        $testTenant = Tenant::where('subdomain', 'test')->first();

        // Company principale per Demo Tenant
        Company::create([
            'id' => Str::uuid(),
            'tenant_id' => $demoTenant->id,
            'name' => 'NexioSolution Demo Company',
            'code' => 'DEMO001',
            'tax_code' => 'IT12345678901',
            'vat_number' => 'IT12345678901',
            'email' => 'info@demo.nexiosolution.local',
            'phone' => '+39 02 12345678',
            'website' => 'https://demo.nexiosolution.local',
            'address' => 'Via Demo 1',
            'city' => 'Milano',
            'postal_code' => '20121',
            'province' => 'MI',
            'country' => 'IT',
            'settings' => [
                'fiscal_year_start' => '01-01',
                'currency' => 'EUR',
                'date_format' => 'DD/MM/YYYY',
                'time_format' => 'HH:mm',
                'week_start' => 1, // Monday
                'working_hours' => [
                    'monday' => ['09:00', '18:00'],
                    'tuesday' => ['09:00', '18:00'],
                    'wednesday' => ['09:00', '18:00'],
                    'thursday' => ['09:00', '18:00'],
                    'friday' => ['09:00', '18:00'],
                    'saturday' => null,
                    'sunday' => null,
                ],
                'holidays' => [
                    '01-01', // Capodanno
                    '06-01', // Epifania
                    '25-04', // Liberazione
                    '01-05', // Festa del Lavoro
                    '02-06', // Festa della Repubblica
                    '15-08', // Ferragosto
                    '01-11', // Ognissanti
                    '08-12', // Immacolata
                    '25-12', // Natale
                    '26-12', // Santo Stefano
                ],
            ],
            'is_active' => true,
        ]);

        // Company secondaria per Demo Tenant
        Company::create([
            'id' => Str::uuid(),
            'tenant_id' => $demoTenant->id,
            'name' => 'Demo Branch Office',
            'code' => 'DEMO002',
            'tax_code' => 'IT98765432109',
            'vat_number' => 'IT98765432109',
            'email' => 'branch@demo.nexiosolution.local',
            'phone' => '+39 06 98765432',
            'address' => 'Via Branch 10',
            'city' => 'Roma',
            'postal_code' => '00100',
            'province' => 'RM',
            'country' => 'IT',
            'settings' => [
                'fiscal_year_start' => '01-01',
                'currency' => 'EUR',
                'date_format' => 'DD/MM/YYYY',
                'time_format' => 'HH:mm',
                'week_start' => 1,
                'working_hours' => [
                    'monday' => ['09:00', '17:00'],
                    'tuesday' => ['09:00', '17:00'],
                    'wednesday' => ['09:00', '17:00'],
                    'thursday' => ['09:00', '17:00'],
                    'friday' => ['09:00', '17:00'],
                    'saturday' => null,
                    'sunday' => null,
                ],
            ],
            'is_active' => true,
        ]);

        // Company per Test Tenant
        if ($testTenant) {
            Company::create([
                'id' => Str::uuid(),
                'tenant_id' => $testTenant->id,
                'name' => 'Test Company Ltd',
                'code' => 'TEST001',
                'tax_code' => 'GB123456789',
                'vat_number' => 'GB123456789',
                'email' => 'info@test.local',
                'phone' => '+44 20 12345678',
                'address' => '123 Test Street',
                'city' => 'London',
                'postal_code' => 'SW1A 1AA',
                'province' => 'Greater London',
                'country' => 'GB',
                'settings' => [
                    'fiscal_year_start' => '04-01',
                    'currency' => 'GBP',
                    'date_format' => 'DD/MM/YYYY',
                    'time_format' => 'HH:mm',
                    'week_start' => 1,
                    'working_hours' => [
                        'monday' => ['09:00', '17:30'],
                        'tuesday' => ['09:00', '17:30'],
                        'wednesday' => ['09:00', '17:30'],
                        'thursday' => ['09:00', '17:30'],
                        'friday' => ['09:00', '17:30'],
                        'saturday' => null,
                        'sunday' => null,
                    ],
                ],
                'is_active' => true,
            ]);
        }
    }
}