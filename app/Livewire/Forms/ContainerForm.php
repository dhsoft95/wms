<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Enums\ContainerSizeType;
use App\Models\Container;
use App\Services\Foundation\DocumentNumber;
use App\Services\Foundation\ISO6346Validator;
use Livewire\Form;

class ContainerForm extends Form
{
    public string  $container_no             = '';
    public string  $seal_no                  = '';
    public string  $size_type                = '40GP';
    public string  $bl_no                    = '';
    public string  $vessel                   = '';
    public string  $voyage                   = '';
    public ?int    $shipper_partner_id        = null;
    public ?int    $consignee_partner_id      = null;
    public ?int    $shipping_line_partner_id  = null;
    public string  $eta                      = '';
    public ?int    $free_days                = null;
    public string  $notes                    = '';
    public bool    $override_number          = false;
    public string  $override_reason          = '';

    protected function rules(): array
    {
        return [
            'container_no'              => ['required', 'string', 'min:11', 'max:11'],
            'size_type'                 => ['required', 'string'],
            'seal_no'                   => ['nullable', 'string', 'max:50'],
            'bl_no'                     => ['nullable', 'string', 'max:50'],
            'vessel'                    => ['nullable', 'string', 'max:100'],
            'voyage'                    => ['nullable', 'string', 'max:20'],
            'shipper_partner_id'        => ['nullable', 'integer', 'exists:partners,id'],
            'consignee_partner_id'      => ['nullable', 'integer', 'exists:partners,id'],
            'shipping_line_partner_id'  => ['nullable', 'integer', 'exists:partners,id'],
            'eta'                       => ['nullable', 'date'],
            'free_days'                 => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes'                     => ['nullable', 'string'],
            'override_reason'           => ['required_if:override_number,true', 'nullable', 'string'],
        ];
    }

    public function fillFrom(Container $container): void
    {
        $this->container_no             = $container->container_no;
        $this->seal_no                  = $container->seal_no ?? '';
        $this->size_type                = $container->size_type->value;
        $this->bl_no                    = $container->bl_no ?? '';
        $this->vessel                   = $container->vessel ?? '';
        $this->voyage                   = $container->voyage ?? '';
        $this->shipper_partner_id       = $container->shipper_partner_id;
        $this->consignee_partner_id     = $container->consignee_partner_id;
        $this->shipping_line_partner_id = $container->shipping_line_partner_id;
        $this->eta                      = $container->eta?->format('Y-m-d') ?? '';
        $this->free_days                = $container->free_days;
        $this->notes                    = $container->notes ?? '';
    }

    public function validateContainerNo(): bool
    {
        $no = ISO6346Validator::normalize($this->container_no);
        if (ISO6346Validator::isValid($no)) {
            $this->container_no = $no;
            return true;
        }
        if (!$this->override_number) {
            $this->addError('container_no', 'Invalid ISO 6346 container number. Tick "Override" to use a non-standard number.');
            return false;
        }
        $this->container_no = $no;
        return true;
    }

    public function store(int $createdBy): Container
    {
        $this->validate();
        if (!$this->validateContainerNo()) {
            throw new \RuntimeException('Container number validation failed.');
        }

        return Container::create([
            'number'                    => DocumentNumber::next('CTR'),
            'container_no'              => $this->container_no,
            'seal_no'                   => $this->seal_no ?: null,
            'size_type'                 => $this->size_type,
            'bl_no'                     => $this->bl_no ?: null,
            'vessel'                    => $this->vessel ?: null,
            'voyage'                    => $this->voyage ?: null,
            'shipper_partner_id'        => $this->shipper_partner_id,
            'consignee_partner_id'      => $this->consignee_partner_id,
            'shipping_line_partner_id'  => $this->shipping_line_partner_id,
            'eta'                       => $this->eta ?: null,
            'free_days'                 => $this->free_days,
            'notes'                     => $this->notes ?: null,
            'status'                    => 'expected',
            'created_by'                => $createdBy,
        ]);
    }

    public function update(Container $container): void
    {
        $this->validate();

        if ($container->containerNoEditable()) {
            if (!$this->validateContainerNo()) {
                throw new \RuntimeException('Container number validation failed.');
            }
            $container->container_no = $this->container_no;
        }

        $container->fill([
            'seal_no'                   => $this->seal_no ?: null,
            'size_type'                 => $this->size_type,
            'bl_no'                     => $this->bl_no ?: null,
            'vessel'                    => $this->vessel ?: null,
            'voyage'                    => $this->voyage ?: null,
            'shipper_partner_id'        => $this->shipper_partner_id,
            'consignee_partner_id'      => $this->consignee_partner_id,
            'shipping_line_partner_id'  => $this->shipping_line_partner_id,
            'eta'                       => $this->eta ?: null,
            'free_days'                 => $this->free_days,
            'notes'                     => $this->notes ?: null,
        ]);
        $container->save();
    }
}
