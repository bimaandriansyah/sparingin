<?php

namespace App\Livewire\Admin;

use App\Models\Community;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Buat komunitas')]
class CommunityCreate extends Component
{
    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('nullable|string|max:100')]
    public string $city = '';

    public function create(): void
    {
        $this->validate();

        $slug = Str::slug($this->name);
        if (Community::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        $community = Auth::user()->communities()->create([
            'name' => $this->name,
            'slug' => $slug,
            'city' => $this->city ?: null,
        ]);

        $this->redirectRoute('admin.communities.show', $community, navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.community-create');
    }
}
