<?php
declare(strict_types=1);
use App\Enums\LabelTemplateType;
use App\Enums\PrinterKind;
use App\Models\Inventory\Lpn;
use App\Models\LabelPrintJob;
use App\Models\LabelTemplate;
use App\Models\Printer;
use App\Services\Labels\LabelRenderer;
use App\Services\Labels\PrintJobService;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Permission;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // PDF bytes can't be stored in the MySQL cache table (binary encoding issue in tests)
    config(['cache.default' => 'array']);

    $this->svc     = new PrintJobService(new LabelRenderer());
    $this->printer = Printer::factory()->create(['kind' => PrinterKind::PdfBrowser->value]);
    $this->tpl     = LabelTemplate::factory()->create([
        'type'      => LabelTemplateType::PalletLpn->value,
        'width_mm'  => 100,
        'height_mm' => 150,
        'dpi'       => 203,
        'elements'  => [],
        'is_default'=> true,
        'is_active' => true,
    ]);
    $this->wh = \App\Models\Warehouse::factory()->create();
});

it('print() creates one LabelPrintJob per printable', function () {
    $user = \App\Models\User::factory()->create();
    Permission::findOrCreate('labels.print', 'web');
    $user->givePermissionTo('labels.print');

    $lpn1 = Lpn::factory()->create(['warehouse_id' => $this->wh->id, 'print_count' => 0]);
    $lpn2 = Lpn::factory()->create(['warehouse_id' => $this->wh->id, 'print_count' => 0]);

    $this->svc->print(
        type:       LabelTemplateType::PalletLpn,
        printables: collect([$lpn1, $lpn2]),
        printer:    $this->printer,
        copies:     1,
        user:       $user,
        tpl:        $this->tpl,
    );

    expect(LabelPrintJob::count())->toBe(2);
});

it('first print succeeds without reprint permission', function () {
    $user = \App\Models\User::factory()->create();
    Permission::findOrCreate('labels.print', 'web');
    $user->givePermissionTo('labels.print');

    $lpn = Lpn::factory()->create(['warehouse_id' => $this->wh->id, 'print_count' => 0]);

    $result = $this->svc->print(
        type:       LabelTemplateType::PalletLpn,
        printables: collect([$lpn]),
        printer:    $this->printer,
        copies:     1,
        user:       $user,
        tpl:        $this->tpl,
    );

    $job = LabelPrintJob::first();
    expect($job->is_reprint)->toBeFalse()
        ->and($result->outputType)->toBe('pdf');
});

it('first print increments LPN print_count', function () {
    $user = \App\Models\User::factory()->create();
    Permission::findOrCreate('labels.print', 'web');
    $user->givePermissionTo('labels.print');

    $lpn = Lpn::factory()->create(['warehouse_id' => $this->wh->id, 'print_count' => 0]);

    $this->svc->print(
        type:       LabelTemplateType::PalletLpn,
        printables: collect([$lpn]),
        printer:    $this->printer,
        copies:     1,
        user:       $user,
        tpl:        $this->tpl,
    );

    expect($lpn->fresh()->print_count)->toBe(1);
});

it('LPN second print requires reason code', function () {
    $user = \App\Models\User::factory()->create();
    Permission::findOrCreate('labels.print', 'web');
    Permission::findOrCreate('labels.reprint', 'web');
    $user->givePermissionTo('labels.print', 'labels.reprint');

    $lpn = Lpn::factory()->create(['warehouse_id' => $this->wh->id, 'print_count' => 1]);

    expect(fn () => $this->svc->print(
        type:       LabelTemplateType::PalletLpn,
        printables: collect([$lpn]),
        printer:    $this->printer,
        copies:     1,
        user:       $user,
        tpl:        $this->tpl,
    ))->toThrow(\InvalidArgumentException::class);
});

it('second print without reprint permission throws AuthorizationException', function () {
    $user = \App\Models\User::factory()->create();
    Permission::findOrCreate('labels.print', 'web');
    $user->givePermissionTo('labels.print');

    $lpn = Lpn::factory()->create(['warehouse_id' => $this->wh->id, 'print_count' => 1]);
    $rc  = \App\Models\ReasonCode::factory()->create(['applies_to' => 'reprint']);

    expect(fn () => $this->svc->print(
        type:         LabelTemplateType::PalletLpn,
        printables:   collect([$lpn]),
        printer:      $this->printer,
        copies:       1,
        user:         $user,
        tpl:          $this->tpl,
        reasonCodeId: (string) $rc->id,
    ))->toThrow(AuthorizationException::class);
});

it('second print with permission and reason code succeeds', function () {
    Permission::findOrCreate('labels.print', 'web');
    Permission::findOrCreate('labels.reprint', 'web');
    $user = \App\Models\User::factory()->create();
    $user->givePermissionTo('labels.print', 'labels.reprint');

    $lpn = Lpn::factory()->create(['warehouse_id' => $this->wh->id, 'print_count' => 1]);
    $rc  = \App\Models\ReasonCode::factory()->create(['applies_to' => 'reprint']);

    $this->svc->print(
        type:         LabelTemplateType::PalletLpn,
        printables:   collect([$lpn]),
        printer:      $this->printer,
        copies:       1,
        user:         $user,
        tpl:          $this->tpl,
        reasonCodeId: (string) $rc->id,
        note:         'lost original',
    );

    $job = LabelPrintJob::first();
    expect($job->is_reprint)->toBeTrue()
        ->and($job->reprint_note)->toBe('lost original');
});
