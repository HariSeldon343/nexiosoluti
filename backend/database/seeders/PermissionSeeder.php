<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User management
            'user.view',
            'user.create',
            'user.edit',
            'user.delete',
            'user.export',

            // Company management
            'company.view',
            'company.create',
            'company.edit',
            'company.delete',

            // Calendar management
            'calendar.view',
            'calendar.create',
            'calendar.edit',
            'calendar.delete',
            'calendar.share',

            // Event management
            'event.view',
            'event.create',
            'event.edit',
            'event.delete',
            'event.invite',

            // Task management
            'task.view',
            'task.create',
            'task.edit',
            'task.delete',
            'task.assign',
            'task.complete',

            // File management
            'file.view',
            'file.upload',
            'file.download',
            'file.edit',
            'file.delete',
            'file.share',
            'file.version',

            // Folder management
            'folder.view',
            'folder.create',
            'folder.edit',
            'folder.delete',
            'folder.share',

            // Chat management
            'chat.view',
            'chat.send',
            'chat.create_channel',
            'chat.edit_channel',
            'chat.delete_channel',
            'chat.delete_message',

            // Notification management
            'notification.view',
            'notification.send',
            'notification.manage',

            // Report management
            'report.view',
            'report.create',
            'report.export',

            // Settings management
            'settings.view',
            'settings.edit',
            'settings.tenant',

            // Audit log
            'audit.view',
            'audit.export',

            // Role & Permission management
            'role.view',
            'role.create',
            'role.edit',
            'role.delete',
            'permission.view',
            'permission.assign',

            // Tenant management (super-admin only)
            'tenant.view',
            'tenant.create',
            'tenant.edit',
            'tenant.delete',
            'tenant.suspend',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }

        // Create roles and assign permissions

        // Super Admin - all permissions
        $superAdmin = Role::create(['name' => 'super-admin', 'guard_name' => 'api']);
        $superAdmin->givePermissionTo(Permission::all());

        // Admin - all permissions except tenant management
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        $admin->givePermissionTo(
            Permission::where('name', 'not like', 'tenant.%')->get()
        );

        // Manager - limited management permissions
        $manager = Role::create(['name' => 'manager', 'guard_name' => 'api']);
        $manager->givePermissionTo([
            // Users (view and edit only)
            'user.view',
            'user.edit',
            'user.export',

            // Full company access
            'company.view',
            'company.edit',

            // Full calendar access
            'calendar.view',
            'calendar.create',
            'calendar.edit',
            'calendar.delete',
            'calendar.share',

            // Full event access
            'event.view',
            'event.create',
            'event.edit',
            'event.delete',
            'event.invite',

            // Full task access
            'task.view',
            'task.create',
            'task.edit',
            'task.delete',
            'task.assign',
            'task.complete',

            // Full file access
            'file.view',
            'file.upload',
            'file.download',
            'file.edit',
            'file.delete',
            'file.share',
            'file.version',

            // Full folder access
            'folder.view',
            'folder.create',
            'folder.edit',
            'folder.delete',
            'folder.share',

            // Chat access
            'chat.view',
            'chat.send',
            'chat.create_channel',
            'chat.edit_channel',

            // Notifications
            'notification.view',
            'notification.send',

            // Reports
            'report.view',
            'report.create',
            'report.export',

            // Settings (view only)
            'settings.view',

            // Audit (view only)
            'audit.view',
        ]);

        // Employee - basic permissions
        $employee = Role::create(['name' => 'employee', 'guard_name' => 'api']);
        $employee->givePermissionTo([
            // Users (view only)
            'user.view',

            // Company (view only)
            'company.view',

            // Calendar (view and create own)
            'calendar.view',
            'calendar.create',
            'calendar.edit',

            // Events (full access to own)
            'event.view',
            'event.create',
            'event.edit',

            // Tasks (view, create, complete own)
            'task.view',
            'task.create',
            'task.edit',
            'task.complete',

            // Files (basic operations)
            'file.view',
            'file.upload',
            'file.download',
            'file.edit',

            // Folders (view and create)
            'folder.view',
            'folder.create',

            // Chat (basic)
            'chat.view',
            'chat.send',

            // Notifications (view own)
            'notification.view',

            // Reports (view only)
            'report.view',
        ]);

        // Guest - minimal permissions
        $guest = Role::create(['name' => 'guest', 'guard_name' => 'api']);
        $guest->givePermissionTo([
            'company.view',
            'calendar.view',
            'event.view',
            'task.view',
            'file.view',
            'file.download',
            'folder.view',
            'chat.view',
        ]);
    }
}