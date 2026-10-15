<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public const ROLES = ['admin', 'petugas', 'peminjam'];

    public function index(Request $request): View
    {
        $search = $request->input('q');
        $role = $request->input('role');

        $users = User::query()
            ->when($search, function ($query, $value) {
                $query->where(function ($w) use ($value) {
                    $w->where('name', 'like', "%{$value}%")
                        ->orWhere('email', 'like', "%{$value}%")
                        ->orWhere('identity_number', 'like', "%{$value}%");
                });
            })
            ->when($role, fn ($query, $value) => $query->where('role', $value))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => self::ROLES]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User(['is_active' => true, 'role' => 'peminjam']),
            'roles' => self::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'identity_number' => ['nullable', 'regex:/^(?:\d{8}|\d{12})$/', 'unique:users,identity_number'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user = User::create([
            ...$data,
            'password' => Str::random(64),
            'role' => 'peminjam',
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->sendEmailVerificationNotification();
        $resetStatus = Password::sendResetLink(['email' => $user->email]);
        AuditLog::record('pengguna', "Menambah akun {$user->email} (peminjam).");

        if ($resetStatus !== Password::RESET_LINK_SENT) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Akun dibuat, tetapi tautan pengaturan password gagal dikirim. Silakan kirim ulang proses reset password.');
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Akun peminjam dibuat. Tautan verifikasi email dan pengaturan password telah dikirim.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user, 'roles' => self::ROLES]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'identity_number' => ['nullable', 'regex:/^(?:\d{8}|\d{12})$/', Rule::unique('users', 'identity_number')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        [$user, $emailChanged] = DB::transaction(function () use ($data, $request, $user): array {
            $adminUsers = User::query()
                ->where('role', 'admin')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'is_active']);
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $active = $request->boolean('is_active');

            if ($target->id === $request->user()->id && ! $active) {
                throw ValidationException::withMessages([
                    'is_active' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
                ]);
            }

            if ($target->role === 'admin' && $target->is_active && ! $active) {
                if ($adminUsers->where('is_active', true)->count() <= 1) {
                    throw ValidationException::withMessages([
                        'is_active' => 'Admin aktif terakhir tidak dapat dinonaktifkan.',
                    ]);
                }
            }

            $emailChanged = $target->email !== $data['email'];
            $target->fill($data);
            $target->is_active = $active;
            if ($emailChanged) {
                $target->email_verified_at = null;
            }
            $target->save();

            return [$target, $emailChanged];
        });

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        AuditLog::record('pengguna', "Mengubah data akun {$user->email}.");

        return redirect()->route('admin.users.index')->with('success', 'Data akun berhasil diperbarui.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(self::ROLES)],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'current_password' => ['required', 'string'],
        ], [
            'reason.required' => 'Alasan perubahan role wajib diisi.',
            'reason.min' => 'Alasan perubahan role terlalu singkat.',
            'current_password.required' => 'Password admin diperlukan untuk autentikasi ulang.',
        ]);
        $actor = $request->user();
        $oldRole = $user->role;

        if (! Hash::check($data['current_password'], $actor->password)) {
            AuditLog::recordRoleChange(
                $actor->id,
                $user->id,
                $oldRole,
                $data['role'],
                $data['reason'],
                'gagal_autentikasi'
            );

            throw ValidationException::withMessages([
                'current_password' => 'Password admin tidak sesuai.',
            ]);
        }

        if ($user->id === $actor->id) {
            AuditLog::recordRoleChange($actor->id, $user->id, $oldRole, $data['role'], $data['reason'], 'ditolak');

            throw ValidationException::withMessages([
                'role' => 'Anda tidak dapat mengubah role akun sendiri.',
            ]);
        }

        if ($oldRole === $data['role']) {
            throw ValidationException::withMessages([
                'role' => 'Pilih role yang berbeda dari role saat ini.',
            ]);
        }

        $allowed = DB::transaction(function () use ($actor, $data, $user): bool {
            $adminUsers = User::query()
                ->where('role', 'admin')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'is_active']);
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $oldRole = $target->role;

            if ($target->role === 'admin' && $target->is_active && $data['role'] !== 'admin'
                && $adminUsers->where('is_active', true)->count() <= 1) {
                return false;
            }

            $target->role = $data['role'];
            $target->save();

            AuditLog::recordRoleChange(
                $actor->id,
                $target->id,
                $oldRole,
                $target->role,
                $data['reason'],
                'berhasil'
            );

            return true;
        });

        if (! $allowed) {
            AuditLog::recordRoleChange(
                $actor->id,
                $user->id,
                $oldRole,
                $data['role'],
                $data['reason'],
                'ditolak_admin_terakhir'
            );

            throw ValidationException::withMessages([
                'role' => 'Role admin aktif terakhir tidak dapat diturunkan.',
            ]);
        }

        return redirect()->route('admin.users.edit', $user)->with('success', 'Role berhasil diperbarui.');
    }

    public function toggle(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        }

        $active = DB::transaction(function () use ($user): ?bool {
            $adminUsers = User::query()
                ->where('role', 'admin')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'is_active']);
            $target = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($target->role === 'admin' && $target->is_active) {
                if ($adminUsers->where('is_active', true)->count() <= 1) {
                    return null;
                }
            }

            $target->is_active = ! $target->is_active;
            $target->save();

            return $target->is_active;
        });

        if ($active === null) {
            return back()->with('error', 'Admin aktif terakhir tidak dapat dinonaktifkan.');
        }

        $user->is_active = $active;
        AuditLog::record('pengguna', ($active ? 'Mengaktifkan' : 'Menonaktifkan')." akun {$user->email}.");

        return back()->with('success', $active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }
}
