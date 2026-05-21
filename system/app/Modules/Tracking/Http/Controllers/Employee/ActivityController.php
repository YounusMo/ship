<?php

declare(strict_types=1);

namespace App\Modules\Tracking\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Models\EmployeeActionLog;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __invoke(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        $rows = EmployeeActionLog::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($rows);
    }
}
