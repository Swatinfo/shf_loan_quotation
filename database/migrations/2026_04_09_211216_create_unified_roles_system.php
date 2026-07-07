<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Create roles table ──
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // ── 2. Create role_user pivot ──
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });

        // ── 3. Create role_permission pivot ──
        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        // ── 4. Seed default roles ──
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Full system access, bypasses all permissions'],
            ['name' => 'Admin', 'slug' => 'admin', 'description' => 'System administration, settings, user management'],
            ['name' => 'Branch Manager', 'slug' => 'branch_manager', 'description' => 'Branch-level management, quotations, loan stages'],
            ['name' => 'Business Development Head', 'slug' => 'bdh', 'description' => 'Same access as Branch Manager'],
            ['name' => 'Loan Advisor', 'slug' => 'loan_advisor', 'description' => 'Quotation creation, loan processing stages'],
            ['name' => 'Bank Employee', 'slug' => 'bank_employee', 'description' => 'Bank-side loan processing only'],
            ['name' => 'Office Employee', 'slug' => 'office_employee', 'description' => 'Office operations, loan stages, document handling'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insert(array_merge($role, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // ── 5. Add new permissions ──
        $newPermissions = [
            ['name' => 'Manage Customers', 'slug' => 'manage_customers', 'group' => 'Customers', 'description' => 'Create and edit customer records'],
            ['name' => 'View Customers', 'slug' => 'view_customers', 'group' => 'Customers', 'description' => 'View customer list and details'],
            ['name' => 'Impersonate Users', 'slug' => 'impersonate_users', 'group' => 'System', 'description' => 'Log in as another user'],
            ['name' => 'View Dashboard', 'slug' => 'view_dashboard', 'group' => 'System', 'description' => 'Access the dashboard'],
            ['name' => 'Manage Notifications', 'slug' => 'manage_notifications', 'group' => 'System', 'description' => 'View and manage notifications'],
            ['name' => 'Transfer Loan Stages', 'slug' => 'transfer_loan_stages', 'group' => 'Loans', 'description' => 'Transfer stage assignment to another user'],
            ['name' => 'Reject Loan', 'slug' => 'reject_loan', 'group' => 'Loans', 'description' => 'Reject a loan application'],
            ['name' => 'Change Loan Status', 'slug' => 'change_loan_status', 'group' => 'Loans', 'description' => 'Put loan on hold or cancel'],
            ['name' => 'View Loan Timeline', 'slug' => 'view_loan_timeline', 'group' => 'Loans', 'description' => 'View loan stage timeline history'],
            ['name' => 'Manage Disbursement', 'slug' => 'manage_disbursement', 'group' => 'Loans', 'description' => 'Process loan disbursement'],
            ['name' => 'Manage Valuation', 'slug' => 'manage_valuation', 'group' => 'Loans', 'description' => 'Fill and edit valuation details'],
            ['name' => 'Raise Query', 'slug' => 'raise_query', 'group' => 'Loans', 'description' => 'Raise queries on loan stages'],
            ['name' => 'Resolve Query', 'slug' => 'resolve_query', 'group' => 'Loans', 'description' => 'Resolve raised queries'],
        ];

        foreach ($newPermissions as $perm) {
            DB::table('permissions')->insert($perm);
        }

        // ── 6. Build role → permission mapping ──
        // Get all permission IDs by slug
        $perms = DB::table('permissions')->pluck('id', 'slug')->toArray();
        $roleIds = DB::table('roles')->pluck('id', 'slug')->toArray();

        // Define permissions per role
        $rolePermissions = [
            'super_admin' => array_values($perms), // All permissions

            'admin' => array_values(array_intersect_key($perms, array_flip([
                'view_settings', 'edit_company_info', 'edit_banks', 'edit_documents', 'edit_tenures',
                'edit_charges', 'edit_services', 'edit_gst',
                'create_quotation', 'generate_pdf', 'view_own_quotations', 'view_all_quotations',
                'delete_quotations', 'download_pdf',
                'view_users', 'create_users', 'edit_users', 'assign_roles',
                'change_own_password', 'view_activity_log', 'view_dashboard', 'manage_notifications',
                'convert_to_loan', 'view_loans', 'view_all_loans', 'create_loan', 'edit_loan',
                'delete_loan', 'manage_loan_documents', 'manage_loan_stages', 'add_remarks',
                'manage_workflow_config', 'upload_loan_documents', 'download_loan_documents',
                'delete_loan_files', 'manage_customers', 'view_customers',
                'transfer_loan_stages', 'reject_loan', 'change_loan_status',
                'view_loan_timeline', 'manage_disbursement', 'manage_valuation',
                'raise_query', 'resolve_query',
            ]))),

            'branch_manager' => array_values(array_intersect_key($perms, array_flip([
                'create_quotation', 'generate_pdf', 'view_own_quotations', 'view_all_quotations', 'download_pdf',
                'view_users', 'change_own_password', 'view_activity_log', 'view_dashboard', 'manage_notifications',
                'convert_to_loan', 'view_loans', 'view_all_loans', 'create_loan', 'edit_loan',
                'manage_loan_documents', 'manage_loan_stages', 'add_remarks',
                'upload_loan_documents', 'download_loan_documents', 'delete_loan_files',
                'manage_customers', 'view_customers',
                'transfer_loan_stages', 'reject_loan', 'change_loan_status',
                'view_loan_timeline', 'manage_disbursement', 'manage_valuation',
                'raise_query', 'resolve_query',
            ]))),

            'loan_advisor' => array_values(array_intersect_key($perms, array_flip([
                'create_quotation', 'generate_pdf', 'view_own_quotations', 'download_pdf',
                'change_own_password', 'view_dashboard', 'manage_notifications',
                'convert_to_loan', 'view_loans', 'create_loan', 'edit_loan',
                'manage_loan_documents', 'manage_loan_stages', 'add_remarks',
                'upload_loan_documents', 'download_loan_documents',
                'manage_customers', 'view_customers',
                'transfer_loan_stages', 'reject_loan', 'change_loan_status',
                'view_loan_timeline', 'manage_disbursement',
                'raise_query', 'resolve_query',
            ]))),

            'bank_employee' => array_values(array_intersect_key($perms, array_flip([
                'change_own_password', 'view_dashboard', 'manage_notifications',
                'view_loans', 'add_remarks', 'download_loan_documents',
                'view_customers', 'view_loan_timeline', 'raise_query',
            ]))),

            'office_employee' => array_values(array_intersect_key($perms, array_flip([
                'change_own_password', 'view_dashboard', 'manage_notifications',
                'view_loans', 'edit_loan',
                'manage_loan_documents', 'manage_loan_stages', 'add_remarks',
                'upload_loan_documents', 'download_loan_documents',
                'view_customers',
                'transfer_loan_stages', 'reject_loan', 'change_loan_status',
                'view_loan_timeline', 'manage_valuation',
                'raise_query',
            ]))),
        ];

        // BDH gets same as branch_manager
        $rolePermissions['bdh'] = $rolePermissions['branch_manager'];

        // Insert role_permission entries
        foreach ($rolePermissions as $roleSlug => $permissionIds) {
            $roleId = $roleIds[$roleSlug] ?? null;
            if (! $roleId) {
                continue;
            }
            foreach ($permissionIds as $permId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permId,
                ]);
            }
        }

        // ── 7. User → role_user pivot assignments are handled by the seeder ──
        // (Legacy migration code that read users.role / users.task_role removed)

        // ── 8. Remove skip_loan_stages permission ──
        $skipPermId = $perms['skip_loan_stages'] ?? null;
        if ($skipPermId) {
            DB::table('role_permission')->where('permission_id', $skipPermId)->delete();
            DB::table('role_permissions')->where('permission_id', $skipPermId)->delete();
            DB::table('task_role_permissions')->where('permission_id', $skipPermId)->delete();
            DB::table('user_permissions')->where('permission_id', $skipPermId)->delete();
            DB::table('permissions')->where('id', $skipPermId)->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');

        // Remove new permissions
        DB::table('permissions')->whereIn('slug', [
            'manage_customers', 'view_customers', 'impersonate_users',
            'view_dashboard', 'manage_notifications', 'transfer_loan_stages',
            'reject_loan', 'change_loan_status', 'view_loan_timeline',
            'manage_disbursement', 'manage_valuation', 'raise_query', 'resolve_query',
        ])->delete();

        // Restore skip_loan_stages permission
        DB::table('permissions')->insert([
            'name' => 'Skip Loan Stages',
            'slug' => 'skip_loan_stages',
            'group' => 'Loans',
            'description' => 'Skip stages in loan workflow',
        ]);
    }
};
