<?php
namespace Database\Factories;
use App\Enums\LabelTemplateType;
use Illuminate\Database\Eloquent\Factories\Factory;
class LabelTemplateFactory extends Factory {
    public function definition(): array {
        return ['name'=>$this->faker->words(3,true),'type'=>LabelTemplateType::PalletLpn->value,'width_mm'=>100,'height_mm'=>150,'dpi'=>203,'elements'=>[],'is_default'=>false,'is_active'=>true];
    }
}
