<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Demo tenant
        Tenant::create([
            'name' => 'Demo Tenant',
            'subdomain' => 'demo',
            'domain' => 'demo.localhost',
            'primary_color' => '#1976d2',
            'secondary_color' => '#1565c0',
            'settings' => [
                'features' => [
                    'calendar' => true,
                    'tasks' => true,
                    'files' => true,
                    'chat' => true,
                    'video_calls' => true,
                    'caldav' => true,
                    'two_factor' => true,
                ],
            ],
            'max_users' => 50,
            'max_storage_mb' => 10240, // 10GB
            'is_active' => true,
            'subscription_expires_at' => now()->addDays(30),
            'subscription_plan' => 'premium',
            'contact_email' => 'demo@nexiosolution.com',
        ]);

        // Test tenant
        Tenant::create([
            'name' => 'Test Company',
            'subdomain' => 'test',
            'domain' => 'test.localhost',
            'primary_color' => '#4caf50',
            'secondary_color' => '#388e3c',
            'settings' => [
                'features' => [
                    'calendar' => true,
                    'tasks' => true,
                    'files' => true,
                    'chat' => false,
                    'video_calls' => false,
                    'caldav' => false,
                    'two_factor' => false,
                ],
            ],
            'max_users' => 10,
            'max_storage_mb' => 1024, // 1GB
            'is_active' => true,
            'subscription_expires_at' => now()->addDays(14),
            'subscription_plan' => 'basic',
            'contact_email' => 'test@nexiosolution.com',
        ]);
    }
}