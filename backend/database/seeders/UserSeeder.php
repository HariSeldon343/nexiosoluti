<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $demoTenant = Tenant::where('slug', 'demo')->first();
        $testTenant = Tenant::where('slug', 'test')->first();

        // Super Admin per Demo Tenant
        $superAdmin = User::create([
            'id' => Str::uuid(),
            'tenant_id' => $demoTenant->id,
            'name' => 'Super Admin',
            'email' => 'admin@nexiosolution.local',
            'password' => Hash::make('Admin@123'),
            'email_verified_at' => now(),
            'is_active' => true,
            'settings' => [
                'language' => 'it',
                'timezone' => 'Europe/Rome',
                'notifications' => [
                    'email' => true,
                    'push' => true,
                    'sms' => false,
                ],
            ],
        ]);
        $superAdmin->assignRole('super-admin');

        // Admin per Demo Tenant
        $admin = User::create([
            'id' => Str::uuid(),
            'tenant_id' => $demoTenant->id,
            'name' => 'Admin User',
            'email' => 'admin@demo.local',
            'password' => Hash::make('Admin@123'),
            'email_verified_at' => now(),
            'is_active' => true,
            'settings' => [
                'language' => 'it',
                'timezone' => 'Europe/Rome',
                'notifications' => [
                    'email' => true,
                    'push' => true,
                    'sms' => false,
                ],
            ],
        ]);
        $admin->assignRole('admin');

        // Manager per Demo Tenant
        $manager = User::create([
            'id' => Str::uuid(),
            'tenant_id' => $demoTenant->id,
            'name' => 'Manager User',
            'email' => 'manager@demo.local',
            'password' => Hash::make('Manager@123'),
            'email_verified_at' => now(),
            'is_active' => true,
            'settings' => [
                'language' => 'it',
                'timezone' => 'Europe/Rome',
                'notifications' => [
                    'email' => true,
                    'push' => false,
                    'sms' => false,
                ],
            ],
        ]);
        $manager->assignRole('manager');

        // Employee per Demo Tenant
        $employee = User::create([
            'id' => Str::uuid(),
            'tenant_id' => $demoTenant->id,
            'name' => 'Employee User',
            'email' => 'employee@demo.local',
            'password' => Hash::make('Employee@123'),
            'email_verified_at' => now(),
            'is_active' => true,
            'settings' => [
                'language' => 'it',
                'timezone' => 'Europe/Rome',
                'notifications' => [
                    'email' => true,
                    'push' => false,
                    'sms' => false,
                ],
            ],
        ]);
        $employee->assignRole('employee');

        // Admin per Test Tenant
        if ($testTenant) {
            $testAdmin = User::create([
                'id' => Str::uuid(),
                'tenant_id' => $testTenant->id,
                'name' => 'Test Admin',
                'email' => 'admin@test.local',
                'password' => Hash::make('Test@123'),
                'email_verified_at' => now(),
                'is_active' => true,
                'settings' => [
                    'language' => 'en',
                    'timezone' => 'UTC',
                    'notifications' => [
                        'email' => true,
                        'push' => false,
                        'sms' => false,
                    ],
                ],
            ]);
            $testAdmin->assignRole('admin');

            // Test User
            $testUser = User::create([
                'id' => Str::uuid(),
                'tenant_id' => $testTenant->id,
                'name' => 'Test User',
                'email' => 'user@test.local',
                'password' => Hash::make('User@123'),
                'email_verified_at' => now(),
                'is_active' => true,
                'settings' => [
                    'language' => 'en',
                    'timezone' => 'UTC',
                    'notifications' => [
                        'email' => true,
                        'push' => false,
                        'sms' => false,
                    ],
                ],
            ]);
            $testUser->assignRole('employee');
        }
    }
}