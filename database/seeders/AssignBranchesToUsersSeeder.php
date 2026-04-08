<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssignBranchesToUsersSeeder extends Seeder
{
    public function run(): void
    {
        $defaultBranch = Branch::first();
        if (! $defaultBranch) {
            $this->command->warn('No branches found. Skipping.');

            return;
        }

        $users = User::all();
        foreach ($users as $user) {
            // Assign default branch if user has no branches
            if ($user->branches()->count() === 0) {
                $user->branches()->attach($defaultBranch->id);
                $this->command->info("Assigned {$defaultBranch->name} to {$user->name}");
            }

            // Set default_branch_id if not set
            if (! $user->default_branch_id) {
                $user->update(['default_branch_id' => $defaultBranch->id]);
            }
        }

        $this->command->info('All users now have branch assignments.');
    }
}
