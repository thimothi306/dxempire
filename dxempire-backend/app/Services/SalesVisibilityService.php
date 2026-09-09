<?php

namespace App\Services;

use App\Models\Dealer;
use App\Models\SalesHierarchy;
use App\Models\User;

/**
 * Restricts a sales-hierarchy user (Salesman/DM/AM/SM/CEO) to their own
 * data plus their subordinates' — instead of every sales-permission holder
 * seeing the whole company's leads/dealers/revenue regardless of level.
 *
 * Returns null (not an empty array) to mean "no restriction" — used for
 * super_admin and for roles outside the sales hierarchy entirely (accounts,
 * hr_manager, etc.), who legitimately need the unscoped, company-wide view
 * this system already gave everyone.
 */
class SalesVisibilityService
{
    /**
     * User IDs this user is allowed to see: themselves plus every
     * subordinate in their org-chart subtree (User.parent_unique_code,
     * same tree Mobile\HierarchyController already walks).
     */
    public function visibleUserIds(User $user): ?array
    {
        if ($user->hasRole('super_admin')) {
            return null;
        }

        // Not part of the sales hierarchy at all (accounts, hr_manager,
        // qc_engineer, etc.) — scoping doesn't apply, leave them unscoped.
        if (!SalesHierarchy::where('user_id', $user->id)->exists()) {
            return null;
        }

        $ids = [$user->id];
        $this->collectSubordinateIds($user, $ids);

        return $ids;
    }

    private function collectSubordinateIds(User $user, array &$ids): void
    {
        // Must select unique_code too — subordinates() is keyed off it
        // (parent_unique_code -> unique_code), so a row missing it here
        // silently finds zero subordinates of its own on the next
        // recursion, truncating the walk after the first level.
        $directSubs = $user->subordinates()->get(['id', 'unique_code']);

        foreach ($directSubs as $sub) {
            $ids[] = $sub->id;
            $this->collectSubordinateIds($sub, $ids);
        }
    }

    /**
     * Dealer IDs attributable to this user's visible salesman set, via
     * SalesHierarchy.user_id -> Dealer.assigned_salesman_id. Null means
     * no restriction (see every dealer, including unassigned ones).
     */
    public function visibleDealerIds(User $user): ?array
    {
        $userIds = $this->visibleUserIds($user);

        if ($userIds === null) {
            return null;
        }

        $nodeIds = SalesHierarchy::whereIn('user_id', $userIds)->pluck('id');

        return Dealer::whereIn('assigned_salesman_id', $nodeIds)->pluck('id')->all();
    }
}
