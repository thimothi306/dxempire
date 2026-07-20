<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class LowStockAlert
{
    use Dispatchable;

    public string $category;
    public int $currentCount;
    public int $threshold;

    public function __construct(string $category, int $currentCount, int $threshold)
    {
        $this->category     = $category;
        $this->currentCount = $currentCount;
        $this->threshold    = $threshold;
    }
}
