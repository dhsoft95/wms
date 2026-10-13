<?php
declare(strict_types=1);
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
    $this->svc     = new PrintJobService(new LabelRenderer());
    $this->printer = Printer::factory()->create(['kind' => PrinterKind::PdfBrowser->value]);
    $this->tpl     = LabelTemplate::factory()->create(['width_mm'=>100,'height_mm'=>150,'dpi'=>203,'elements'=>[]]);
    $this->wh      = \App\Models\Warehouse::factory()->create();
});

it('first print succeeds without reprint permission', function () {
    $user = \App\Models\User::factory()->create();
    $lpn  = Lpn::factory()->create(['warehouse_id'=>$this->wh->id,'print_count'=>0]);
    $job  = $this->svc->print($this->tpl, $lpn, $this->printer, 1, $user);
    expect($job->is_reprint)->toBeFalse()->and(LabelPrintJob::count())->toBe(1);
});

it('first print increments LPN print_count', function () {
    $user = \App\Models\User::factory()->create();
    $lpn  = Lpn::factory()->create(['warehouse_id'=>$this->wh->id,'print_count'=>0]);
    $this->svc->print($this->tpl, $lpn, $this->printer, 1, $user);
    expect($lpn->fresh()->print_count)->toBe(1);
});

it('first print sets printed_at', function () {
    $user = \App\Models\User::factory()->create();
    $lpn  = Lpn::factory()->create(['warehouse_id'=>$this->wh->id,'print_count'=>0,'printed_at'=>null]);
    $this->svc->print($this->tpl, $lpn, $this->printer, 1, $user);
    expect($lpn->fresh()->printed_at)->not->toBeNull();
});

it('second print without permission throws AuthorizationException', function () {
    $user = \App\Models\User::factory()->create();
    $lpn  = Lpn::factory()->create(['warehouse_id'=>$this->wh->id,'print_count'=>1]);
    $rc   = \App\Models\ReasonCode::factory()->create(['applies_to'=>'reprint']);
    expect(fn() => $this->svc->print($this->tpl, $lpn, $this->printer, 1, $user, (string)$rc->id))
        ->toThrow(AuthorizationException::class);
});

it('second print with permission and reason code succeeds', function () {
    Permission::findOrCreate('labels.reprint', 'web');
    $user = \App\Models\User::factory()->create();
    $user->givePermissionTo('labels.reprint');
    $lpn  = Lpn::factory()->create(['warehouse_id'=>$this->wh->id,'print_count'=>1]);
    $rc   = \App\Models\ReasonCode::factory()->create(['applies_to'=>'reprint']);
    $job  = $this->svc->print($this->tpl, $lpn, $this->printer, 1, $user, (string)$rc->id, 'note');
    expect($job->is_reprint)->toBeTrue();
});

it('second print without reason code throws InvalidArgumentException', function () {
    Permission::findOrCreate('labels.reprint', 'web');
    $user = \App\Models\User::factory()->create();
    $user->givePermissionTo('labels.reprint');
    $lpn  = Lpn::factory()->create(['warehouse_id'=>$this->wh->id,'print_count'=>1]);
    expect(fn() => $this->svc->print($this->tpl, $lpn, $this->printer, 1, $user))
        ->toThrow(\InvalidArgumentException::class);
});
