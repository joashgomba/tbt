<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MobileApiToken extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return ! $this->expires_at || $this->expires_at->isFuture();
    }

    /** @return array{token: MobileApiToken, plain_text: string} */
    public static function issue(User $user, string $deviceName, ?int $ttlDays = null): array
    {
        $plain = Str::random(64);
        $ttlDays ??= (int) config('ntb.mobile_token_ttl_days', 90);

        $token = self::query()->create([
            'user_id' => $user->id,
            'name' => Str::limit($deviceName, 120),
            'token_hash' => hash('sha256', $plain),
            'abilities' => 'mobile:complaints',
            'expires_at' => now()->addDays(max(1, $ttlDays)),
        ]);

        return ['token' => $token, 'plain_text' => $plain];
    }

    public static function findByPlainText(string $plain): ?self
    {
        return self::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plain))
            ->first();
    }
}
