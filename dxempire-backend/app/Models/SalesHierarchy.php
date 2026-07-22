<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesHierarchy extends Model
{
    protected $table = 'sales_hierarchy';

    protected $fillable = [
        'tree_id', 'name', 'phone', 'email',
        'hierarchy_role', 'parent_id',
        'state', 'area', 'district',
        'user_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    // "Unique Code" in the admin UI is the same thing as tree_id — expose
    // it under both names so the frontend's existing `unique_code` column
    // and lookup-by-code flows work without a schema change.
    protected $appends = ['unique_code'];

    public function getUniqueCodeAttribute(): ?string
    {
        return $this->tree_id;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SalesHierarchy::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(SalesHierarchy::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dealers(): HasMany
    {
        return $this->hasMany(Dealer::class, 'assigned_salesman_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function generateTreeId(string $role): string
    {
        $prefix = match ($role) {
            'ceo'              => 'CEO',
            'state_manager'    => 'SM',
            'area_manager'     => 'AM',
            'district_manager' => 'DM',
            'salesman'         => 'SL',
            default            => 'XX',
        };

        $last = self::where('hierarchy_role', $role)
            ->orderByDesc('id')
            ->first();

        $next = $last ? ((int) substr($last->tree_id, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    // Get all descendants recursively
    public function allDescendants(): \Illuminate\Support\Collection
    {
        $result = collect();
        foreach ($this->children as $child) {
            $result->push($child);
            $result = $result->merge($child->allDescendants());
        }
        return $result;
    }
}
