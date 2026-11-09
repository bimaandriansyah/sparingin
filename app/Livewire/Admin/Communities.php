<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Komunitas')]
class Communities extends Component
{
    public function render()
    {
        return view('livewire.admin.communities', [
            'communities' => Auth::user()->communities()->withCount('events')->latest()->get(),
        ]);
    }
}
