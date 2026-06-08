<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditService
{
    public function log(string $event, Model $subject, ?User $actor = null, array $old = [], array $new = []): void
    {
        AuditLog::create([
            'tenant_id' => $subject->tenant_id ?? ($actor?->tenant_id),
            'user_id' => $actor?->id,
            'auditable_type' => $subject->getMorphClass(),
            'auditable_id' => $subject->id,
            'event' => $event,
            'old_values' => empty($old) ? null : $old,
            'new_values' => empty($new) ? null : $new,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
