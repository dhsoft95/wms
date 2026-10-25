<?php

use Illuminate\Support\Facades\Blade;

// ─── Tone rendering ───────────────────────────────────────────────────────────

it('renders the correct BEM modifier for each tone', function (string $tone) {
    $html = Blade::render(
        '<x-ui.kpi-card label="Test" value="0" :tone="$tone" />',
        compact('tone')
    );

    expect($html)->toContain("kpi-card--{$tone}");
})->with(['brand', 'success', 'warning', 'danger', 'neutral']);

it('defaults to brand tone when no tone is supplied', function () {
    $html = Blade::render('<x-ui.kpi-card label="Test" value="0" />');

    expect($html)->toContain('kpi-card--brand');
});

// ─── Value and label ──────────────────────────────────────────────────────────

it('renders the label and value', function () {
    $html = Blade::render(
        '<x-ui.kpi-card label="Stock on Hand" value="1,234" />'
    );

    expect($html)
        ->toContain('Stock on Hand')
        ->toContain('1,234');
});

it('renders the hint text when provided', function () {
    $html = Blade::render(
        '<x-ui.kpi-card label="Test" value="5" hint="SKUs below min" />'
    );

    expect($html)->toContain('SKUs below min');
});

// ─── Link (href) ──────────────────────────────────────────────────────────────

it('renders as an anchor with kpi-card--link when href is provided', function () {
    $html = Blade::render(
        '<x-ui.kpi-card label="Test" value="0" href="/inventory/stock" />'
    );

    expect($html)
        ->toContain('<a ')
        ->toContain('href="/inventory/stock"')
        ->toContain('kpi-card--link');
});

it('renders as a div without kpi-card--link when no href is provided', function () {
    $html = Blade::render('<x-ui.kpi-card label="Test" value="0" />');

    expect($html)
        ->not->toContain('<a ')
        ->not->toContain('kpi-card--link')
        ->toContain('<div ');
});

// ─── Sparkline ────────────────────────────────────────────────────────────────

it('renders sparkline elements when spark data is provided', function () {
    $html = Blade::render(
        '<x-ui.kpi-card label="Test" value="0" :spark="$spark" />',
        ['spark' => [10, 20, 15, 30, 25, 40, 35]]
    );

    expect($html)
        ->toContain('kpi-spark__line')
        ->toContain('kpi-spark__dot');
});

it('renders no sparkline when spark data is absent', function () {
    $html = Blade::render('<x-ui.kpi-card label="Test" value="0" />');

    expect($html)->not->toContain('kpi-spark__line');
});

it('renders no sparkline when spark array has fewer than two points', function () {
    $html = Blade::render(
        '<x-ui.kpi-card label="Test" value="0" :spark="$spark" />',
        ['spark' => [42]]
    );

    expect($html)->not->toContain('kpi-spark__line');
});

// ─── Loading state ────────────────────────────────────────────────────────────

it('renders skeleton and is-loading class when loading is true', function () {
    $html = Blade::render(
        '<x-ui.kpi-card label="Test" value="0" :loading="true" />'
    );

    expect($html)
        ->toContain('is-loading')
        ->toContain('kpi-skeleton');
});

it('does not render skeleton when loading is false', function () {
    $html = Blade::render(
        '<x-ui.kpi-card label="Test" value="99" :loading="false" />'
    );

    expect($html)
        ->not->toContain('kpi-skeleton')
        ->toContain('99');
});

// ─── Trend indicator ──────────────────────────────────────────────────────────

it('renders the up trend indicator', function () {
    $html = Blade::render(
        '<x-ui.kpi-card label="Test" value="0" trend="up" />'
    );

    expect($html)->toContain('kpi-trend--up');
});

it('renders the down trend indicator', function () {
    $html = Blade::render(
        '<x-ui.kpi-card label="Test" value="0" trend="down" />'
    );

    expect($html)->toContain('kpi-trend--down');
});

// ─── Bottom bar ───────────────────────────────────────────────────────────────

it('always renders the bottom bar element', function () {
    $html = Blade::render('<x-ui.kpi-card label="Test" value="0" />');

    expect($html)->toContain('kpi-card__bar');
});
