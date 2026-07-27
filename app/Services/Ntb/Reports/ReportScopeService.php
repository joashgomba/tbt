<?php

namespace App\Services\Ntb\Reports;

use App\Models\Complaint;
use App\Models\ComplaintStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ReportScopeService
{
    public function scopedComplaintsQuery(Request $request): Builder
    {
        $query = Complaint::query();
        $user = $request->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->can('complaints.view_all')) {
            return $query;
        }

        if ($user->can('complaints.view_own')) {
            return $query->where('created_by', $user->id);
        }

        if ($user->institution_id) {
            return $query->where('assigned_institution_id', $user->institution_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function applyFilters(Builder $query, array $filters, ?Carbon $cutoff = null): Builder
    {
        $dateField = $filters['date_field'] ?? 'created_at';
        $allowedDateFields = ['created_at', 'closed_at', 'updated_at'];
        if (! in_array($dateField, $allowedDateFields, true)) {
            $dateField = 'created_at';
        }

        if (! empty($filters['period'])) {
            $query = $this->applyPeriodPreset($query, $filters['period'], $dateField);
        } elseif (! empty($filters['from_date']) && ! empty($filters['to_date'])) {
            $query->whereBetween($dateField, [
                Carbon::parse($filters['from_date'])->startOfDay(),
                Carbon::parse($filters['to_date'])->endOfDay(),
            ]);
        } elseif (! empty($filters['from_month']) && ! empty($filters['to_month'])) {
            $query->whereBetween($dateField, [
                Carbon::parse($filters['from_month'])->startOfMonth(),
                Carbon::parse($filters['to_month'])->endOfMonth(),
            ]);
        } elseif (! empty($filters['year'])) {
            $query->whereYear($dateField, (int) $filters['year']);
        } elseif (! empty($filters['month'])) {
            $parts = explode('-', $filters['month']);
            if (count($parts) === 2) {
                $query->whereYear($dateField, (int) $parts[0])
                    ->whereMonth($dateField, (int) $parts[1]);
            }
        } elseif (! empty($filters['quarter']) && ! empty($filters['year'])) {
            $q = (int) $filters['quarter'];
            $year = (int) $filters['year'];
            $startMonth = ($q - 1) * 3 + 1;
            $query->whereBetween($dateField, [
                Carbon::create($year, $startMonth)->startOfMonth(),
                Carbon::create($year, $startMonth + 2)->endOfMonth(),
            ]);
        }

        if ($cutoff) {
            $query->where($dateField, '<=', $cutoff);
        }

        if (! empty($filters['category_id'])) {
            $query->where('ntb_category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['corridor_id'])) {
            $query->where('corridor_id', (int) $filters['corridor_id']);
        }

        if (! empty($filters['institution_id'])) {
            $query->where('assigned_institution_id', (int) $filters['institution_id']);
        }

        if (! empty($filters['location_id'])) {
            $query->where('location_id', (int) $filters['location_id']);
        }

        if (! empty($filters['status_id'])) {
            $query->where('status_id', (int) $filters['status_id']);
        }

        if (! empty($filters['status_codes']) && is_array($filters['status_codes'])) {
            $statusIds = ComplaintStatus::query()->whereIn('code', $filters['status_codes'])->pluck('id');
            $query->whereIn('status_id', $statusIds);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['gender'])) {
            if ($filters['gender'] === 'unknown') {
                $query->where(function ($q) {
                    $q->whereNull('gender')->orWhereIn('gender', ['', 'NA', 'unknown', 'not_stated']);
                });
            } else {
                $query->where('gender', $filters['gender']);
            }
        }

        if (! empty($filters['submission_channel'])) {
            $query->where('submission_channel', $filters['submission_channel']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('complaint_code', 'like', $term)
                    ->orWhere('title', 'like', $term);
            });
        }

        if (! empty($filters['sla_condition'])) {
            $query = $this->applySlaFilter($query, $filters['sla_condition']);
        }

        if (! empty($filters['escalation_level'])) {
            $query->whereHas('escalations', fn ($q) => $q
                ->where('escalation_level', (int) $filters['escalation_level'])
                ->whereNull('resolution_date'));
        }

        if (! empty($filters['open_only'])) {
            $terminal = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');
            $query->whereNotIn('status_id', $terminal);
        }

        if (! empty($filters['closed_only'])) {
            $closed = ComplaintStatus::query()->whereIn('code', ['closed', 'archived', 'rejected'])->pluck('id');
            $query->whereIn('status_id', $closed);
        }

        return $query;
    }

    private function applyPeriodPreset(Builder $query, string $period, string $dateField): Builder
    {
        $now = now();

        return match ($period) {
            'today' => $query->whereDate($dateField, $now->toDateString()),
            'yesterday' => $query->whereDate($dateField, $now->copy()->subDay()->toDateString()),
            'this_week' => $query->whereBetween($dateField, [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]),
            'last_week' => $query->whereBetween($dateField, [
                $now->copy()->subWeek()->startOfWeek(),
                $now->copy()->subWeek()->endOfWeek(),
            ]),
            'this_month' => $query->whereBetween($dateField, [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]),
            'last_month' => $query->whereBetween($dateField, [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ]),
            'this_quarter' => $query->whereBetween($dateField, [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()]),
            'this_year' => $query->whereYear($dateField, $now->year),
            'last_year' => $query->whereYear($dateField, $now->year - 1),
            default => $query,
        };
    }

    private function applySlaFilter(Builder $query, string $condition): Builder
    {
        return match ($condition) {
            'breached' => $query->whereHas('slaTrackings', fn ($q) => $q->where('is_breached', true)),
            'within' => $query->whereHas('slaTrackings', fn ($q) => $q
                ->where('is_breached', false)
                ->whereNotNull('completed_at')),
            'approaching' => $query->whereHas('slaTrackings', fn ($q) => $q
                ->where('is_breached', false)
                ->whereNull('completed_at')
                ->where('deadline_at', '<=', now()->addHours(24))
                ->where('deadline_at', '>', now())),
            default => $query,
        };
    }

    public function canAccessDefinition($user, array $requiredPermissions): bool
    {
        if (empty($requiredPermissions)) {
            return $user?->can('reports.generate') ?? false;
        }

        foreach ($requiredPermissions as $perm) {
            if ($user?->can($perm)) {
                return true;
            }
        }

        return false;
    }
}
