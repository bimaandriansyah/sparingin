<?php

namespace App\Livewire\Public;

use App\Models\Registration;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::public')]
#[Title('Status Pendaftaran')]
class RegistrationStatus extends Component
{
    public Registration $registration;

    public function mount(string $token): void
    {
        $this->registration = Registration::where('token', $token)->with('event.community', 'slot.team', 'members.slot.team')->firstOrFail();
    }

    public function render()
    {
        $this->registration->refresh()->load('members.slot.team');

        return view('livewire.public.registration-status');
    }
}
