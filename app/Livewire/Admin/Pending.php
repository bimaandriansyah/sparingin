<?php

namespace App\Livewire\Admin;

use App\Models\Registration;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Konfirmasi')]
class Pending extends Component
{
    /** cek = waiting for acceptance, lunas = accepted but not paid yet (e.g. cash after the match). */
    #[Url]
    public string $tab = 'cek';

    public function setStatus(int $registrationId, string $status): void
    {
        // ownedBy() scopes to this admin, so another admin's registration 404s.
        $error = Registration::ownedBy(auth()->user())->findOrFail($registrationId)->changeStatus($status);

        Flux::toast(variant: $error ? 'danger' : 'success', text: $error ?? ($status === 'accepted' ? 'Pendaftaran diterima.' : 'Pendaftaran ditolak.'));
    }

    public function setPaid(int $registrationId, bool $paid = true): void
    {
        Registration::ownedBy(auth()->user())->findOrFail($registrationId)->markPaid($paid);

        Flux::toast(variant: 'success', text: $paid ? 'Ditandai lunas.' : 'Tanda lunas dibatalkan.');
    }

    public function render()
    {
        $user = auth()->user();
        $query = $this->tab === 'lunas' ? Registration::unpaidFor($user) : Registration::pendingFor($user);

        return view('livewire.admin.pending', [
            'registrations' => $query->with('event.community', 'slot.team', 'paymentMethod', 'members.slot.team')->oldest()->get(),
            'pendingCount' => Registration::pendingFor($user)->count(),
            'unpaidCount' => Registration::unpaidFor($user)->count(),
        ]);
    }
}
