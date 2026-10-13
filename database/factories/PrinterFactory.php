<?php
namespace Database\Factories;
use App\Enums\PrinterKind;
use Illuminate\Database\Eloquent\Factories\Factory;
class PrinterFactory extends Factory {
    public function definition(): array {
        return ['name'=>$this->faker->words(2,true),'kind'=>PrinterKind::PdfBrowser->value,'dpi'=>203,'is_active'=>true];
    }
}
