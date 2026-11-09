<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\Registration;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Pengaturan profil')]
class Profile extends Component
{
    use ProfileValidationRules;

    public string $name = '';

    public string $phone = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->phone = '0'.substr(Auth::user()->phone, 2); // show the way people type it
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();
        $this->phone = Registration::normalizePhone($this->phone);

        $validated = $this->validate($this->profileRules($user->id), [
            'phone.regex' => 'Format nomor HP tidak valid, contoh: 08123456789.',
            'phone.unique' => 'Nomor HP ini sudah dipakai akun lain.',
        ]);

        $user->fill($validated)->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }
}
