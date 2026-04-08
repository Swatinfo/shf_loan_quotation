<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotesApiController extends Controller
{
    /**
     * GET: Retrieve saved additional notes.
     */
    public function get()
    {
        $notes = '';
        try {
            $row = DB::table('app_settings')
                ->where('setting_key', 'additional_notes')
                ->first();
            if ($row) {
                $notes = $row->setting_value ?? '';
            }
        } catch (\Exception $e) {
            // DB unavailable, return empty
        }

        return response()->json(['success' => true, 'notes' => $notes]);
    }

    /**
     * POST: Save additional notes.
     */
    public function save(Request $request)
    {
        $notes = trim($request->input('notes', ''));

        try {
            DB::table('app_settings')->updateOrInsert(
                ['setting_key' => 'additional_notes'],
                ['setting_value' => $notes, 'updated_at' => now()]
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to save notes: ' . $e->getMessage()], 500);
        }
    }
}
