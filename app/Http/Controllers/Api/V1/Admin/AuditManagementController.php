<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Ntb\Audit\AuditLogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditManagementController extends Controller
{
    private const MODULES = [
        'complaint', 'institution', 'category', 'corridor', 'location',
        'uam', 'workflow', 'report', 'system',
    ];

    public function __construct(private readonly AuditLogPresenter $presenter) {}

    public function stats(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        $base = AuditLog::query();

        return response()->json([
            'summary' => [
                'total' => (clone $base)->count(),
                'last_24h' => (clone $base)->where('created_at', '>=', now()->subDay())->count(),
                'last_7d' => (clone $base)->where('created_at', '>=', now()->subDays(7))->count(),
                'last_30d' => (clone $base)->where('created_at', '>=', now()->subDays(30))->count(),
                'unique_users' => (clone $base)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
                'unique_actions' => (clone $base)->distinct('action')->count('action'),
            ],
            'by_module' => AuditLog::query()
                ->select(DB::raw("SUBSTRING_INDEX(action, '.', 1) as module"), DB::raw('count(*) as count'))
                ->groupBy('module')
                ->orderByDesc('count')
                ->limit(12)
                ->get(),
            'top_actions' => AuditLog::query()
                ->select('action', DB::raw('count(*) as count'))
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(15)
                ->get(),
            'top_users' => AuditLog::query()
                ->whereNotNull('user_id')
                ->join('users', 'audit_logs.user_id', '=', 'users.id')
                ->select('users.id', 'users.name', 'users.email', DB::raw('count(*) as count'))
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'timeline' => AuditLog::query()
                ->where('created_at', '>=', now()->subDays(30))
                ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as count'))
                ->groupBy('day')
                ->orderBy('day')
                ->get(),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        return response()->json([
            'modules' => self::MODULES,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'entity_types' => AuditLog::query()
                ->whereNotNull('entity_type')
                ->distinct()
                ->orderBy('entity_type')
                ->pluck('entity_type')
                ->map(fn ($type) => [
                    'class' => $type,
                    'label' => class_basename($type),
                ])
                ->values(),
            'users' => User::query()
                ->whereIn('id', AuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        $query = $this->filteredQuery($request)
            ->with('user:id,name,email')
            ->orderByDesc('created_at');

        $perPage = min(max($request->integer('per_page', 50), 10), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(fn (AuditLog $log) => $this->presenter->serialize($log)),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        $log = AuditLog::query()->with('user:id,name,email,username')->findOrFail($id);
        $serialized = $this->presenter->serialize($log);

        $related = [];
        if ($log->entity_type && $log->entity_id) {
            $related = AuditLog::query()
                ->with('user:id,name,email')
                ->where('entity_type', $log->entity_type)
                ->where('entity_id', $log->entity_id)
                ->where('id', '!=', $log->id)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn (AuditLog $entry) => $this->presenter->serialize($entry));
        }

        return response()->json([
            'log' => $serialized,
            'related' => $related,
        ]);
    }

    public function entityTrail(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('audit.view'), 403);

        $data = $request->validate([
            'entity_type' => ['required', 'string', 'max:255'],
            'entity_id' => ['required', 'integer', 'min:1'],
        ]);

        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->where('entity_type', $data['entity_type'])
            ->where('entity_id', $data['entity_id'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (AuditLog $log) => $this->presenter->serialize($log));

        return response()->json([
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'entity_label' => $this->presenter->entityLabel($data['entity_type'], $data['entity_id']),
            'logs' => $logs,
        ]);
    }

    private function filteredQuery(Request $request)
    {
        $query = AuditLog::query();

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('old_values', 'like', "%{$search}%")
                    ->orWhere('new_values', 'like', "%{$search}%");
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($module = $request->string('module')->trim()->value()) {
            $query->where('action', 'like', "{$module}.%");
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->string('entity_type'));
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->integer('entity_id'));
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', '%'.$request->string('ip_address').'%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to'));
        }

        return $query;
    }
}
