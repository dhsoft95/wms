<?php

namespace App\Livewire\Admin\Partners;

use App\Actions\Foundation\UpdatePartnerAction;
use App\Models\Partner;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Edit extends Component
{
    public Partner $partner;

    public string $code = '';
    public string $name = '';
    /** @var array<string> */
    public array $types = [];
    public string $tin = '';
    public string $vrn = '';
    public string $contact_person = '';
    public string $phone = '';
    public string $email = '';
    public string $address = '';
    public string $city = '';
    public string $country = '';

    public function mount(Partner $partner): void
    {
        $this->partner = $partner;

        $this->code           = $partner->code;
        $this->name           = $partner->name;
        $this->types          = is_array($partner->type) ? $partner->type : ($partner->type?->toArray() ?? []);
        $this->tin            = $partner->tin ?? '';
        $this->vrn            = $partner->vrn ?? '';
        $this->contact_person = $partner->contact_person ?? '';
        $this->phone          = $partner->phone ?? '';
        $this->email          = $partner->email ?? '';
        $this->address        = $partner->address ?? '';
        $this->city           = $partner->city ?? '';
        $this->country        = $partner->country ?? '';
    }

    protected function rules(): array
    {
        return [
            'code'           => ['required', 'string', 'max:50', Rule::unique('partners', 'code')->ignore($this->partner->id)],
            'name'           => ['required', 'string', 'max:255'],
            'types'          => ['required', 'array', 'min:1'],
            'types.*'        => ['string', Rule::in(['supplier', 'shipper', 'consignee', 'customer', 'shipping_line', 'transporter'])],
            'tin'            => ['nullable', 'string', 'max:50'],
            'vrn'            => ['nullable', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:255'],
            'address'        => ['nullable', 'string'],
            'city'           => ['nullable', 'string', 'max:100'],
            'country'        => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function messages(): array
    {
        return [
            'code.required'  => 'A partner code is required.',
            'code.unique'    => 'This partner code is already taken.',
            'name.required'  => 'A partner name is required.',
            'types.required' => 'Please select at least one partner type.',
            'types.min'      => 'Please select at least one partner type.',
            'email.email'    => 'Please enter a valid email address.',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        UpdatePartnerAction::execute($this->partner, $data);

        $this->dispatch('toast', message: $this->partner->name . ' updated successfully.', type: 'success');

        $this->redirect(route('admin.partners.show', $this->partner), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.partners.edit')
            ->layout('components.layouts.app', [
                'title'           => 'Edit Partner',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
