<?php

namespace App\Livewire\Public;

use App\Models\Registration;
use App\Models\Event;
use App\Support\PublicImage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Booking wizard: 1 Data diri → 2 Pilih posisi → 3 Metode iuran. */
#[Layout('layouts::public')]
class JoinEvent extends Component
{
    use WithFileUploads;

    public const STEPS = [1 => 'Data Diri', 2 => 'Pilih Posisi', 3 => 'Metode Iuran'];

    public Event $event;

    public int $step = 1;

    public string $name = '';

    public string $phone = '';

    public $photo;

    public int $team = 0;

    public ?int $slotId = null;

    /** 'cash' or a community payment method id (as string). */
    public ?string $payment = null;

    public $proof;

    public function mount(): void
    {
        if (! $this->event->isOpen()) {
            $this->redirectRoute('event.public', $this->event, navigate: true);
        }
    }

    public function pick(int $slotId): void
    {
        $this->slotId = $slotId;
        $this->resetErrorBag('slotId');
    }

    public function next(): void
    {
        $this->validateStep($this->step);
        $this->step = min(3, $this->step + 1);
    }

    public function back(): void
    {
        if ($this->step === 1) {
            $this->redirectRoute('event.public', $this->event, navigate: true);

            return;
        }
        $this->step--;
    }

    private function validateStep(int $step): void
    {
        match ($step) {
            1 => $this->validate([
                'name' => 'required|string|max:60',
                'phone' => ['required', 'string', 'regex:/^(\+?62|0)8[0-9\s\-]{7,13}$/'],
                'photo' => 'nullable|image|max:4096',
            ], [
                'phone.regex' => 'Format nomor WA tidak valid, contoh: 08123456789.',
                'photo.image' => 'Foto harus berupa gambar.',
            ], ['name' => 'nama panggilan', 'phone' => 'nomor WhatsApp', 'photo' => 'foto']),
            2 => $this->validate(['slotId' => 'required|integer'], ['slotId.required' => 'Pilih posisi dulu di lapangan.']),
            3 => $this->validate([
                'payment' => $this->needsPayment() ? 'required|string' : 'nullable',
                'proof' => $this->needsProof() ? 'required|image|max:5120' : 'nullable',
            ], [
                'payment.required' => 'Pilih metode iuran.',
                'proof.required' => 'Upload bukti transfer.',
                'proof.image' => 'Bukti harus berupa gambar.',
                'proof.max' => 'Ukuran bukti maksimal 5MB.',
            ]),
        };
    }

    private function pickedSlot()
    {
        return $this->slotId ? $this->event->slots()->with('team')->find($this->slotId) : null;
    }

    /** Free positions skip payment entirely. */
    private function needsPayment(): bool
    {
        return ($this->pickedSlot()?->price ?? 0) > 0;
    }

    /** Proof is only needed for transfers; cash is paid at the venue. */
    private function needsProof(): bool
    {
        return $this->needsPayment() && $this->payment !== 'cash';
    }

    public function register(): void
    {
        foreach ([1, 2, 3] as $step) {
            try {
                $this->validateStep($step);
            } catch (ValidationException $e) {
                $this->step = $step;
                throw $e;
            }
        }

        $key = 'register:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['proof' => 'Terlalu banyak percobaan, coba lagi beberapa menit lagi.']);
        }
        RateLimiter::hit($key, 600);

        $slot = $this->event->slots()->findOrFail($this->slotId);
        $type = $this->needsPayment() ? ($this->payment === 'cash' ? 'cash' : 'transfer') : null;
        $method = $type === 'transfer' ? $this->event->community->paymentMethods()->findOrFail((int) $this->payment) : null;
        $proofPath = $type === 'transfer' ? $this->proof->store('proofs') : null; // private disk
        $photoPath = $this->photo ? PublicImage::store($this->photo, 'player-photos') : null;

        try {
            $registration = $this->event->register($slot, $this->name, $this->phone, $proofPath, $photoPath, $method?->id, $type);
        } catch (ValidationException $e) {
            $proofPath && Storage::delete($proofPath);
            $photoPath && Storage::disk('public')->delete($photoPath);
            // Position taken meanwhile → back to step 2; duplicate phone → step 1.
            $this->step = isset($e->errors()['phone']) ? 1 : 2;
            throw $e;
        }

        $this->redirectRoute('registration.status', $registration->token);
    }

    public function render()
    {
        $teams = $this->event->teams;
        $this->team = min(max($this->team, 0), max($teams->count() - 1, 0));
        $current = $teams[$this->team] ?? null;

        return view('livewire.public.join-event', [
            'teams' => $teams,
            'positions' => $current ? $current->slots()->with(['registrations' => fn ($q) => $q->whereIn('status', Registration::HOLDING)])->get() : collect(),
            'picked' => $this->pickedSlot(),
            'paymentMethods' => $this->event->community->paymentMethods,
        ])->title('Bergabung · '.$this->event->title);
    }
}
