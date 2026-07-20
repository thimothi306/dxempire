<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingsController extends Controller
{
    use ApiResponse;

    private const EDITABLE_KEYS = [
        'grade_price_rules',
        'low_stock_threshold',
        'logistics_provider',
        'whatsapp_provider',
        'company_name',
        'company_address',
        'company_gst',
        'company_phone',
        'company_email',
    ];

    public function index(): JsonResponse
    {
        $settings = Setting::orderBy('key')->get(['key', 'value', 'updated_at']);

        return $this->success($settings->map(fn($s) => [
            'key'        => $s->key,
            'value'      => $this->parseValue($s->value),
            'raw'        => $s->value,
            'editable'   => in_array($s->key, self::EDITABLE_KEYS),
            'updated_at' => $s->updated_at,
        ]));
    }

    public function show(string $key): JsonResponse
    {
        $setting = Setting::where('key', $key)->first();

        if (!$setting) {
            return $this->error("Setting '{$key}' not found.", 404);
        }

        return $this->success([
            'key'      => $setting->key,
            'value'    => $this->parseValue($setting->value),
            'raw'      => $setting->value,
            'editable' => in_array($key, self::EDITABLE_KEYS),
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        if (!in_array($key, self::EDITABLE_KEYS)) {
            return $this->error("Setting '{$key}' is not editable via API.", 403);
        }

        $request->validate([
            'value' => ['required'],
        ]);

        $value = is_array($request->value)
            ? json_encode($request->value)
            : (string) $request->value;

        Setting::updateOrCreate(['key' => $key], ['value' => $value]);

        // Bust cached value
        Cache::forget("setting:{$key}");

        return $this->success([
            'key'   => $key,
            'value' => $this->parseValue($value),
        ], "Setting '{$key}' updated.");
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'settings'       => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'in:' . implode(',', self::EDITABLE_KEYS)],
            'settings.*.value' => ['required'],
        ]);

        foreach ($request->settings as $item) {
            $key   = $item['key'];
            $value = is_array($item['value'])
                ? json_encode($item['value'])
                : (string) $item['value'];

            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            Cache::forget("setting:{$key}");
        }

        return $this->success(null, count($request->settings) . ' setting(s) updated.');
    }

    private function parseValue(string $value): mixed
    {
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
