<?php

declare(strict_types=1);

namespace Modules\PriyasaCore\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Modules\PriyasaCore\Models\SystemSetting;

final class SettingsController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::query()->where('is_public', true)->orderBy('key')->get();
        $data = [];
        foreach ($settings as $setting) {
            $value = $setting->value;
            $data[$setting->key] = match ($setting->type) {
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $value,
                'number' => (float) $value,
                'json' => json_decode((string) $value, true),
                default => $value,
            };
        }
        return response()->json(['success'=>true,'data'=>$data]);
    }
}
