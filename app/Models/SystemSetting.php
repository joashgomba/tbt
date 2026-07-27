<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'label_en', 'label_fr'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();

        if (! $row) {
            return $default;
        }

        return match ($row->type) {
            'integer' => (int) $row->value,
            'boolean' => filter_var($row->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($row->value, true),
            default => $row->value,
        };
    }

    public static function setValue(string $key, mixed $value, string $type = 'string'): self
    {
        $stored = is_array($value) ? json_encode($value) : (string) $value;

        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type],
        );
    }
}
