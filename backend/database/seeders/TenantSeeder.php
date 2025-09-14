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
            'id' => Str::uuid(),
            'slug' => 'demo',
            'name' => 'Demo Tenant',
            'domain' => 'demo.localhost',
            'database' => 'nexiosolution',
            'config' => [
                'max_users' => 50,
                'max_storage' => 10737418240, // 10GB
                'features' => [
                    'calendar' => true,
                    'tasks' => true,
                    'files' => true,
                    'chat' => true,
                    'video_calls' => true,
                    'caldav' => true,
                    'two_factor' => true,
                ],
                'theme' => [
                    'primary_color' => '#1976d2',
                    'logo_url' => null,
                ],
            ],
            'is_active' => true,
            'trial_ends_at' => now()->addDays(30),
        ]);

        // Test tenant
        Tenant::create([
            'id' => Str::uuid(),
            'slug' => 'test',
            'name' => 'Test Company',
            'domain' => 'test.localhost',
            'database' => 'nexiosolution',
            'config' => [
                'max_users' => 10,
                'max_storage' => 1073741824, // 1GB
                'features' => [
                    'calendar' => true,
                    'tasks' => true,
                    'files' => true,
                    'chat' => false,
                    'video_calls' => false,
                    'caldav' => false,
                    'two_factor' => false,
                ],
                'theme' => [
                    'primary_color' => '#4caf50',
                    'logo_url' => null,
                ],
            ],
            'is_active' => true,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}