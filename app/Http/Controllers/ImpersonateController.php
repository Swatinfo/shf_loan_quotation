<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonateController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        if (! $request->user()->canImpersonate()) {
            abort(403);
        }

        $search = $request->get('search', '');

        $users = User::where('id', '!=', auth()->id())
            ->where('role', '!=', 'super_admin')
            ->where('is_active', true)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('role', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'role']);

        return response()->json($users);
    }
}
