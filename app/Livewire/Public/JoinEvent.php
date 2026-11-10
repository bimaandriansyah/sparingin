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

    /** Picked positions in pick order; the first is the booker's own. Max one team's worth. */
    public array $slotIds = [];

    /** Friends' names keyed by slot id (the booker's name is $name). */
    public array $names = [];

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
        $this->resetErrorBag('slotIds');

        if (in_array($slotId, $this->slotIds, true)) {
            $this->slotIds = array_values(array_diff($this->slotIds, [$slotId]));
            unset($this->names[$slotId]);

            return;
        }

        if (count($this->slotIds) >= $this->event->players_per_team) {
            $this->addError('slotIds', "Maksimal {$this->event->players_per_team} posisi (1 tim) per pendaftaran.");

            return;
        }

        $this->slotIds[] = $slotId;
    }

    /** Take every position of the current team (only offered while the team is still empty). */
    public function pickTeam(): void
    {
        $team = $this->event->teams[$this->team] ?? null;
        abort_unless($team, 404);
        $ids = $team->slots()->pluck('id')->all();
        $this->slotIds = $ids;
        $this->names = array_intersect_key($this->names, array_flip($ids));
        $this->resetErrorBag('slotIds');
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
            2 => $this->validate([
                'slotIds' => 'required|array|min:1|max:'.$this->event->players_per_team,
                ...collect($this->slotIds)->slice(1)->mapWithKeys(fn ($id) => ["names.$id" => 'required|string|max:60'])->all(),
            ], [
                'slotIds.required' => 'Pilih posisi dulu di lapangan.',
                'slotIds.max' => 'Maksimal :max posisi (1 tim) per pendaftaran.',
                'names.*.required' => 'Nama pemain wajib diisi.',
            ]),
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

    /** Picked slots in pick order (booker first). */
    private function pickedSlots()
    {
        $slots = $this->event->slots()->with('team')->whereIn('id', $this->slotIds)->get()->keyBy('id');

        return collect($this->slotIds)->filter(fn ($id) => $slots->has($id))->map(fn ($id) => $slots[$id])->values();
    }

    /** Free bookings skip payment entirely. */
    private function needsPayment(): bool
    {
        return $this->event->quote($this->pickedSlots())['total'] > 0;
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

        $players = [];
        foreach ($this->slotIds as $i => $id) {
            $players[(int) $id] = $i === 0 ? $this->name : trim($this->names[$id]);
        }
        $type = $this->needsPayment() ? ($this->payment === 'cash' ? 'cash' : 'transfer') : null;
        $method = $type === 'transfer' ? $this->event->community->paymentMethods()->findOrFail((int) $this->payment) : null;
        $proofPath = $type === 'transfer' ? $this->proof->store('proofs') : null; // private disk
        $photoPath = $this->photo ? PublicImage::store($this->photo, 'player-photos') : null;

        try {
            $registration = $this->event->book($players, $this->phone, $proofPath, $photoPath, $method?->id, $type);
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
            'positions' => $positions = $current ? $current->slots()->with(['registrations' => fn ($q) => $q->whereIn('status', Registration::HOLDING)])->get() : collect(),
            'teamEmpty' => $positions->isNotEmpty() && $positions->every(fn ($p) => $p->registrations->isEmpty()),
            'picked' => $picked = $this->pickedSlots(),
            'quote' => $this->event->quote($picked),
            'paymentMethods' => $this->event->community->paymentMethods,
        ])->title('Bergabung · '.$this->event->title);
    }
}
