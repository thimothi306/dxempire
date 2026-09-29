<?php

namespace App\Http\Traits;

use App\Models\Employee;
use App\Models\SalesHierarchy;
use App\Models\User;

/**
 * Staff Users, Employees, and Hierarchy nodes can all point at the same real
 * person (linked via user_id) when created through the bundled flows, but
 * deactivating one has never touched the others — someone "removed" in one
 * module could still be logged in, or still counted in hierarchy/payroll
 * numbers, via a different one. This lets a delete/deactivate action ask
 * "this person also has X, Y — deactivate those too?" before doing anything,
 * rather than silently leaving the others active or silently cascading.
 */
trait ChecksLinkedRecords
{
    /** @return array<string, array{id: int, label: string}>|null */
    protected function findLinkedRecords(?int $userId, string $excludeType): ?array
    {
        if (!$userId) {
            return null;
        }

        $linked = [];

        if ($excludeType !== 'user') {
            $user = User::find($userId);
            if ($user && $user->is_active) {
                $linked['user'] = ['id' => $user->id, 'label' => "Staff User login ({$user->unique_code})"];
            }
        }

        if ($excludeType !== 'employee') {
            $employee = Employee::where('user_id', $userId)->where('is_active', true)->first();
            if ($employee) {
                $linked['employee'] = ['id' => $employee->id, 'label' => "Employee record ({$employee->employee_code})"];
            }
        }

        if ($excludeType !== 'hierarchy') {
            $hierarchy = SalesHierarchy::where('user_id', $userId)->where('is_active', true)->first();
            if ($hierarchy) {
                $linked['hierarchy'] = ['id' => $hierarchy->id, 'label' => "Hierarchy placement ({$hierarchy->tree_id})"];
            }
        }

        return empty($linked) ? null : $linked;
    }

    protected function deactivateLinkedRecords(?int $userId, string $excludeType): void
    {
        if (!$userId) {
            return;
        }

        if ($excludeType !== 'user') {
            User::where('id', $userId)->update(['is_active' => false]);
        }
        if ($excludeType !== 'employee') {
            Employee::where('user_id', $userId)->update(['is_active' => false]);
        }
        if ($excludeType !== 'hierarchy') {
            SalesHierarchy::where('user_id', $userId)->update(['is_active' => false]);
        }
    }

    protected function linkedConfirmationPayload(array $linked): array
    {
        $labels = collect($linked)->pluck('label')->implode(', ');

        return [
            'data'    => ['needs_confirmation' => true, 'linked' => $linked],
            'message' => "This person also has: {$labels}. Deactivate those too?",
        ];
    }
}
