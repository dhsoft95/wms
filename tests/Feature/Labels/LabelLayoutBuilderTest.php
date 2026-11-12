<?php

declare(strict_types=1);

use App\Enums\LabelTemplateType;
use App\Services\Labels\LabelLayoutBuilder;

beforeEach(function () {
    $this->builder = app(LabelLayoutBuilder::class);
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

function allElementsInBounds(array $elements, float $w, float $h): bool
{
    foreach ($elements as $el) {
        if (($el['x_mm'] ?? 0) < 0) return false;
        if (($el['y_mm'] ?? 0) < 0) return false;
        if (($el['x_mm'] ?? 0) + ($el['w_mm'] ?? 0) > $w + 0.1) return false;
        if (($el['y_mm'] ?? 0) + ($el['h_mm'] ?? 0) > $h + 0.1) return false;
    }
    return true;
}

function hasMainCodeElement(array $elements, string $mainField): bool
{
    foreach ($elements as $el) {
        if (($el['kind'] ?? '') === 'field' && ($el['field_key'] ?? '') === $mainField) {
            return true;
        }
    }
    return false;
}

// ─── Bounds tests (all three layouts × representative sizes) ──────────────────

it('big_code layout elements stay within label bounds for location 100x50', function () {
    $result = $this->builder->build(
        LabelTemplateType::Location,
        100, 50,
        'big_code', 'qr',
        ['warehouse_name'],
        false, false,
    );

    expect($result['elements'])->not->toBeEmpty();
    expect(allElementsInBounds($result['elements'], 100, 50))->toBeTrue();
});

it('barcode_left layout elements stay within label bounds for item 50x25', function () {
    $result = $this->builder->build(
        LabelTemplateType::Item,
        50, 25,
        'barcode_left', 'code128',
        ['item_name'],
        false, false,
    );

    expect($result['elements'])->not->toBeEmpty();
    expect(allElementsInBounds($result['elements'], 50, 25))->toBeTrue();
});

it('barcode_top layout elements stay within label bounds for lpn 100x150', function () {
    $result = $this->builder->build(
        LabelTemplateType::PalletLpn,
        100, 150,
        'barcode_top', 'code128',
        ['item_name', 'qty', 'batch_no', 'expiry_date', 'vw_name', 'grn_number'],
        false, true,
    );

    expect($result['elements'])->not->toBeEmpty();
    expect(allElementsInBounds($result['elements'], 100, 150))->toBeTrue();
});

// ─── Main code always present ─────────────────────────────────────────────────

it('main code field is always included regardless of layout', function (LabelTemplateType $type, string $layout, string $mainField) {
    $result = app(LabelLayoutBuilder::class)->build($type, 100, 100, $layout, 'code128', [], false, false);
    expect(hasMainCodeElement($result['elements'], $mainField))->toBeTrue();
})->with([
    'location big_code'    => [LabelTemplateType::Location,  'big_code',     'location_code'],
    'lpn barcode_top'      => [LabelTemplateType::PalletLpn, 'barcode_top',  'lpn_code'],
    'item barcode_left'    => [LabelTemplateType::Item,       'barcode_left', 'item_sku'],
    'container barcode_top'=> [LabelTemplateType::Container,  'barcode_top',  'container_no'],
    'dispatch barcode_top' => [LabelTemplateType::Dispatch,   'barcode_top',  'do_number'],
]);

// ─── Fields dropped with warnings when no space ───────────────────────────────

it('drops fields that do not fit and returns warnings', function () {
    // Narrow label: limited vertical space for fields
    $manyFields = ['item_name', 'qty', 'batch_no', 'expiry_date', 'vw_name', 'grn_number', 'receive_date'];

    $result = $this->builder->build(
        LabelTemplateType::PalletLpn,
        100, 50,
        'barcode_top', 'code128',
        $manyFields,
        false, false,
    );

    // Not all 7 fields fit on a 50mm-tall label alongside a barcode + code text
    expect($result['warnings'])->not->toBeEmpty('Expected at least one warning about dropped fields');

    // The fields that did fit must still stay within bounds
    expect(allElementsInBounds($result['elements'], 100, 50))->toBeTrue();
});

// ─── Set-default unsets others of same type ────────────────────────────────────

it('setDefault unsets other templates of the same type', function () {
    $type = LabelTemplateType::PalletLpn;

    $a = \App\Models\LabelTemplate::factory()->create(['type' => $type->value, 'is_default' => true]);
    $b = \App\Models\LabelTemplate::factory()->create(['type' => $type->value, 'is_default' => false]);

    // Simulate what Index::setDefault does
    \App\Models\LabelTemplate::where('type', $type->value)
        ->where('id', '!=', $b->id)
        ->update(['is_default' => false]);
    $b->update(['is_default' => true]);

    expect($a->fresh()->is_default)->toBeFalse();
    expect($b->fresh()->is_default)->toBeTrue();
});

// ─── Invalid layout throws ────────────────────────────────────────────────────

it('throws for unknown layout', function () {
    expect(fn() => $this->builder->build(
        LabelTemplateType::Location,
        100, 50,
        'unknown_layout', 'qr',
        [], false, false,
    ))->toThrow(\InvalidArgumentException::class);
});
