<?php

namespace App\Services\Ntb\Audit;

use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\Corridor;
use App\Models\Institution;
use App\Models\Location;
use App\Models\NtbCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogPresenter
{
    /** @var array<class-string<Model>, callable> */
    private const ENTITY_RESOLVERS = [
        Complaint::class => 'complaint_code',
        Institution::class => 'institution_name',
        User::class => 'email',
        NtbCategory::class => 'code',
        Corridor::class => 'corridor_code',
        Location::class => 'location_name',
    ];

    public function module(AuditLog $log): string
    {
        return Str::before($log->action, '.') ?: 'system';
    }

    public function entityShortType(?string $entityType): ?string
    {
        if (! $entityType) {
            return null;
        }

        return class_basename($entityType);
    }

    public function entityLabel(?string $entityType, ?int $entityId): ?string
    {
        if (! $entityType || ! $entityId || ! class_exists($entityType)) {
            return null;
        }

        $field = self::ENTITY_RESOLVERS[$entityType] ?? null;
        if (! $field) {
            return "#{$entityId}";
        }

        $value = $entityType::query()->whereKey($entityId)->value($field);

        return $value ? (string) $value : "#{$entityId}";
    }

    public function diff(?array $old, ?array $new): array
    {
        $old ??= [];
        $new ??= [];
        $keys = array_unique([...array_keys($old), ...array_keys($new)]);
        $changes = [];

        foreach ($keys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            if ($oldVal === $newVal) {
                continue;
            }
            $changes[] = [
                'field' => $key,
                'old' => $oldVal,
                'new' => $newVal,
            ];
        }

        return $changes;
    }

    public function serialize(AuditLog $log): array
    {
        return [
            'id' => $log->id,
            'action' => $log->action,
            'module' => $this->module($log),
            'entity_type' => $log->entity_type,
            'entity_short_type' => $this->entityShortType($log->entity_type),
            'entity_id' => $log->entity_id,
            'entity_label' => $this->entityLabel($log->entity_type, $log->entity_id),
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'diff' => $this->diff($log->old_values, $log->new_values),
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at,
            'user' => $log->user ? [
                'id' => $log->user->id,
                'name' => $log->user->name,
                'email' => $log->user->email,
            ] : null,
        ];
    }
}
