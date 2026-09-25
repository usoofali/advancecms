<?php

namespace App\Livewire\Hub;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class HubLogin extends Component
{
    public string $username = '';

    public string $password = '';

    protected array $rules = [
        'username' => 'required|string',
        'password' => 'required|string',
    ];

    public function mount(): void
    {
        if (session('hub_authenticated') === true) {
            $this->redirectRoute('hub.analytics', navigate: true);
        }
    }

    public function login(): void
    {
        $this->validate();

        $expectedUsername = (string) config('gateway.hub.admin.username', 'admin');
        $expectedPassword = (string) config('gateway.hub.admin.password', 'password');

        if (
            hash_equals($expectedUsername, $this->username) &&
            hash_equals($expectedPassword, $this->password)
        ) {
            session(['hub_authenticated' => true]);
            $this->redirectRoute('hub.analytics', navigate: true);

            return;
        }

        $this->addError('username', 'Invalid Hub Administrator username or password.');
    }

    public function render(): View
    {
        return view('livewire.hub.hub-login');
    }
}
