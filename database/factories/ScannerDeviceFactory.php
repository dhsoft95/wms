<?php
namespace Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
class ScannerDeviceFactory extends Factory {
    private static int $n = 500;
    public function definition(): array {
        return ['code'=>'SCN-'.(self::$n++),'name'=>$this->faker->words(2,true),'device_uid'=>(string)Str::uuid(),'is_active'=>true];
    }
}
