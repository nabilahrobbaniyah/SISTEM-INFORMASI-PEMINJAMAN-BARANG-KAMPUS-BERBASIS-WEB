<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('q');
        $action = $request->input('action');

        $logs = AuditLog::with(['user', 'targetUser'])
            ->when($search, function ($query, $value) {
                $query->where(function ($w) use ($value) {
                    $w->where('description', 'like', "%{$value}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$value}%"));
                });
            })
            ->when($action, fn ($query, $value) => $query->where('action', $value))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
