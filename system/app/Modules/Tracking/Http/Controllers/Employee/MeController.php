<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Models\Branch;
use App\Modules\Tracking\Models\BranchStaff;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $staff = BranchStaff::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        $branchIds = $staff->pluck('branch_id')->all();
        $branches = Branch::query()->whereIn('id', $branchIds)->get()->keyBy('id');

        return response()->json([
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'branches' => $staff->map(fn (BranchStaff $s) => [
                'branch'    => $branches->get($s->branch_id),
                'role'      => $s->role->value,
                'is_active' => (bool) $s->is_active,
            ])->values(),
        ]);
    }
}
