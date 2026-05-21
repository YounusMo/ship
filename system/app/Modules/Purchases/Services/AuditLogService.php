<?php

declare(strict_types=1);

namespace App\Modules\Purchases\Services;

use App\Modules\Purchases\Models\PurchaseAuditLog;
use Illuminate\Support\Facades\Auth;

/**
 * تسجيل التغييرات الحساسة في الموديول
 *
 * @see CLAUDE.md Section 10 - التحقق والتدقيق
 */
class AuditLogService
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function log(
        string $entityType,
        string $entityId,
        string $action,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
        ?string $notes = null,
    ): PurchaseAuditLog {
        $changes = $this->calculateChanges($oldValues, $newValues);

        return PurchaseAuditLog::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'changes' => $changes ?: null,
            'performed_by_id' => Auth::id(),
            'user_role' => $this->getCurrentUserRole(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'reason' => $reason,
            'notes' => $notes,
            'performed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function calculateChanges(array $old, array $new): array
    {
        $changes = [];
        foreach ($new as $key => $newValue) {
            $oldValue = $old[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
            }
        }
        return $changes;
    }

    private function getCurrentUserRole(): ?string
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }
        // Adapter حسب نظام الـ roles في ShipFlow
        // مثلاً: return $user->roles->first()?->name;
        return method_exists($user, 'role') ? (string) $user->role : null;
    }
}
