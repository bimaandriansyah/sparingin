<?php

use App\Livewire\Admin\Communities;
use App\Livewire\Admin\CommunityCreate;
use App\Livewire\Admin\CommunityShow;
use App\Livewire\Admin\EventForm;
use App\Livewire\Admin\Events;
use App\Livewire\Admin\EventShow;
use App\Livewire\Admin\Pending;
use App\Livewire\Public\CommunityPage;
use App\Livewire\Public\EventPage;
use App\Livewire\Public\Home;
use App\Livewire\Public\EventPlayers;
use App\Livewire\Public\JoinEvent;
use App\Livewire\Public\RegistrationStatus;
use App\Models\Registration;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::livewire('/', Home::class)->name('home');

// Public (players, no account)
Route::livewire('c/{community:slug}', CommunityPage::class)->name('community.public');
Route::livewire('e/{event}', EventPage::class)->name('event.public');
Route::livewire('e/{event}/pemain', EventPlayers::class)->name('event.players');
Route::livewire('e/{event}/gabung', JoinEvent::class)->name('event.join');
Route::livewire('r/{token}', RegistrationStatus::class)->name('registration.status');

// Admin
Route::middleware(['auth'])->group(function () {
    Route::livewire('dashboard', Communities::class)->name('dashboard');
    Route::livewire('admin/events', Events::class)->name('admin.events.index');
    Route::livewire('admin/konfirmasi', Pending::class)->name('admin.pending');
    Route::livewire('admin/communities/create', CommunityCreate::class)->name('admin.communities.create');
    Route::livewire('admin/communities/{community}', CommunityShow::class)->name('admin.communities.show');
    Route::livewire('admin/communities/{community}/events/create', EventForm::class)->name('admin.events.create');
    Route::livewire('admin/events/{event}', EventShow::class)->name('admin.events.show');
    Route::livewire('admin/events/{event}/edit', EventForm::class)->name('admin.events.edit');

    Route::get('admin/registrations/{registration}/proof', function (Registration $registration) {
        abort_unless($registration->event->community->user_id === auth()->id(), 403);
        abort_unless($registration->proof_path, 404); // free positions have no proof

        return Storage::response($registration->proof_path);
    })->name('admin.registrations.proof');
});

require __DIR__.'/settings.php';
