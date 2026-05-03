<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('name')->paginate(10);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['exists:categories,id'],
        ]);

        $validated['password'] = Hash::make('password');

        $user = User::create($validated);

        // Save categories if provided
        if (!empty($validated['categories'])) {
            foreach ($validated['categories'] as $categoryId) {
                StaffProfile::create([
                    'user_id' => $user->id,
                    'category_id' => $categoryId,
                ]);
            }
        }

        return Redirect::route('admin.users.index')
            ->with('success', 'Pengguna baru berhasil dibuat. Password default: password');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'password' => ['nullable', 'string', 'min:6'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['exists:categories,id'],
        ]);

        if ($request->filled('password')) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Remove categories from user update data
        $categories = $validated['categories'] ?? [];
        unset($validated['categories']);

        $user->update($validated);

        // Update staff categories
        StaffProfile::where('user_id', $user->id)->delete();
        if (!empty($categories)) {
            foreach ($categories as $categoryId) {
                StaffProfile::create([
                    'user_id' => $user->id,
                    'category_id' => $categoryId,
                ]);
            }
        }

        return Redirect::route('admin.users.index')
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return Redirect::back()->withErrors(['delete' => 'Anda tidak bisa menghapus akun sendiri.']);
        }

        $user->delete();

        return Redirect::route('admin.users.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }
}
