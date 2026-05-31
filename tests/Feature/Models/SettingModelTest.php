<?php

namespace Tests\Feature\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_value_casts_to_array_round_trip(): void
    {
        Setting::create([
            'key' => 'whatsapp_template',
            'value' => ['greeting' => 'Hola', 'lines' => ['a', 'b']],
        ]);

        $setting = Setting::query()->where('key', 'whatsapp_template')->first();

        $this->assertIsArray($setting->value);
        $this->assertSame('Hola', $setting->value['greeting']);
        $this->assertSame(['a', 'b'], $setting->value['lines']);
    }

    public function test_scalar_value_round_trips_through_json_cast(): void
    {
        Setting::create([
            'key' => 'catalog_name',
            'value' => ['name' => 'My Shop'],
        ]);

        $setting = Setting::query()->where('key', 'catalog_name')->first();

        $this->assertSame(['name' => 'My Shop'], $setting->value);
    }

    public function test_model_increments_by_id(): void
    {
        $a = Setting::create(['key' => 'a', 'value' => [1]]);
        $b = Setting::create(['key' => 'b', 'value' => [2]]);

        $this->assertTrue($a->incrementing);
        $this->assertSame('int', $a->getKeyType());
        $this->assertGreaterThan($a->id, $b->id);
    }
}
