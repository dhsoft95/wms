<?php

namespace App\Livewire\Admin\Partners;

use App\Actions\Foundation\CreatePartnerAction;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Create extends Component
{
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

    protected function rules(): array
    {
        return [
            'code'           => ['required', 'string', 'max:50', Rule::unique('partners', 'code')],
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
            'code.required'    => 'A partner code is required.',
            'code.unique'      => 'This partner code is already taken.',
            'name.required'    => 'A partner name is required.',
            'types.required'   => 'Please select at least one partner type.',
            'types.min'        => 'Please select at least one partner type.',
            'email.email'      => 'Please enter a valid email address.',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        CreatePartnerAction::execute($data);

        $this->dispatch('toast', message: 'Partner created successfully.', type: 'success');

        $this->redirect(route('admin.partners.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.partners.create')
            ->layout('components.layouts.app', [
                'title'           => 'New Partner',
                'breadcrumbGroup' => 'Master Data',
            ]);
    }
}
