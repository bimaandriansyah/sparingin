<?php

namespace App\Livewire\Admin;

use App\Models\Community;
use App\Models\Registration;
use App\Support\PublicImage;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Flux\Flux;
use Livewire\Component;

class CommunityShow extends Component
{
    use WithFileUploads;

    public $logo;

    public string $whatsapp = '';

    public Community $community;

    public string $name = '';

    public string $city = '';

    public string $description = '';

    public string $provider = '';

    public string $accountNumber = '';

    public string $accountName = '';

    public function mount(Community $community): void
    {
        abort_unless($community->user_id === auth()->id(), 403);

        $this->name = $community->name;
        $this->city = (string) $community->city;
        $this->description = (string) $community->description;
        $this->whatsapp = (string) $community->whatsapp;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => 'required|string|max:100',
            'city' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'whatsapp' => ['nullable', 'regex:/^(\+?62|0)8[0-9\s\-]{7,13}$/'],
            'logo' => 'nullable|image|max:2048',
        ], ['whatsapp.regex' => 'Format nomor WA tidak valid, contoh: 08123456789.']);

        $data['whatsapp'] = $this->whatsapp ? Registration::normalizePhone($this->whatsapp) : null;
        unset($data['logo']);

        if ($this->logo) {
            if ($this->community->logo_path) {
                Storage::disk('public')->delete($this->community->logo_path);
            }
            $data['logo_path'] = PublicImage::store($this->logo, 'logos');
            $this->reset('logo');
        }

        $this->community->update($data);
        Flux::toast(variant: 'success', text: 'Komunitas disimpan.');
    }

    public function addPaymentMethod(): void
    {
        $this->validate([
            'provider' => 'required|string|max:50',
            'accountNumber' => 'required|string|max:50',
            'accountName' => 'required|string|max:100',
        ]);

        $this->community->paymentMethods()->create([
            'provider' => $this->provider,
            'account_number' => $this->accountNumber,
            'account_name' => $this->accountName,
        ]);

        $this->reset('provider', 'accountNumber', 'accountName');
    }

    public function deletePaymentMethod(int $id): void
    {
        $this->community->paymentMethods()->whereKey($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.community-show', [
            'paymentMethods' => $this->community->paymentMethods,
            'events' => $this->community->events()->latest('starts_at')->get(),
        ])->title($this->community->name);
    }
}
