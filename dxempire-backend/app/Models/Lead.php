<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $fillable = [
        'source', 'contact_name', 'phone', 'business_name',
        'stage', 'assigned_to', 'last_contact_at', 'notes',
    ];

    protected $casts = [
        'last_contact_at' => 'datetime',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeFilter($query, $request)
    {
        return $query
            ->when($request->stage,       fn($q) => $q->where('stage', $request->stage))
            ->when($request->source,      fn($q) => $q->where('source', $request->source))
            ->when($request->assigned_to, fn($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->search,      fn($q) => $q->where(function ($q2) use ($request) {
                $q2->where('contact_name', 'like', '%' . $request->search . '%')
                   ->orWhere('business_name', 'like', '%' . $request->search . '%')
                   ->orWhere('phone', 'like', '%' . $request->search . '%');
            }));
    }
}
