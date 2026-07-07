<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Update stage default_role JSON arrays (legal_advisor → loan_advisor)
        $stages = DB::table('stages')->whereNotNull('default_role')->get();
        foreach ($stages as $stage) {
            $roles = json_decode($stage->default_role, true);
            if (is_array($roles) && in_array('legal_advisor', $roles)) {
                $roles = array_values(array_unique(
                    array_map(fn ($r) => $r === 'legal_advisor' ? 'loan_advisor' : $r, $roles)
                ));
                DB::table('stages')->where('id', $stage->id)->update([
                    'default_role' => json_encode($roles),
                ]);
            }
        }

        // Update sub_actions JSON
        $stagesWithSub = DB::table('stages')->whereNotNull('sub_actions')->get();
        foreach ($stagesWithSub as $stage) {
            $subActions = json_decode($stage->sub_actions, true);
            if (is_array($subActions)) {
                $changed = false;
                foreach ($subActions as &$sa) {
                    if (isset($sa['roles']) && in_array('legal_advisor', $sa['roles'])) {
                        $sa['roles'] = array_values(array_unique(
                            array_map(fn ($r) => $r === 'legal_advisor' ? 'loan_advisor' : $r, $sa['roles'])
                        ));
                        $changed = true;
                    }
                }
                if ($changed) {
                    DB::table('stages')->where('id', $stage->id)->update([
                        'sub_actions' => json_encode($subActions),
                    ]);
                }
            }
        }

        // Legacy task_role_permissions cleanup removed — table managed by unified roles system
    }

    public function down(): void
    {
        //
    }
};
