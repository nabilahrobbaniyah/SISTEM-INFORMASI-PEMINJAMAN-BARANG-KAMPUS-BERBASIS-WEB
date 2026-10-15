<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'identity_number' => ['required', 'string', 'regex:/^(?:\d{8}|\d{12})$/', 'unique:users,identity_number'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $domains = config('campus.email_domains', []);
        $emailDomain = mb_strtolower(substr(strrchr($data['email'], '@'), 1));

        if (! in_array($emailDomain, $domains, true)) {
            throw ValidationException::withMessages([
                'email' => 'Gunakan alamat email resmi kampus untuk mendaftar.',
            ]);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'identity_number' => $data['identity_number'],
            'password' => Hash::make($data['password']),
            'role' => 'peminjam',
            'is_active' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
