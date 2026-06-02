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
    public function index(Request $request): View
    {
        $search = $request->query('search');

        $usersQuery = User::withCount('articles')
            ->orderBy('name')
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        $users = $usersQuery->paginate(10)->withQueryString();

        $totalStaff = User::count();
        $totalAdmin = User::where('role', 'admin')->count();
        $totalStaffHelpdesk = User::where('role', 'staff')->count();
        $activeStaff = User::where('role', 'staff')->where('status', 'active')->count();
        $topContributorCount = User::where('role', 'staff')
            ->withCount('articles')
            ->orderByDesc('articles_count')
            ->value('articles_count') ?? 0;

        return view('admin.users.index', compact(
            'users',
            'search',
            'totalStaff',
            'totalAdmin',
            'totalStaffHelpdesk',
            'activeStaff',
            'topContributorCount'
        ));
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
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'status' => $validated['status'],
            'password' => Hash::make($validated['password']),
        ]);

        return Redirect::route('admin.users.index')
            ->with('success', 'Staf baru berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user->loadCount('articles')]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $user->update($validated);

        return Redirect::route('admin.users.index')
            ->with('success', 'Data staf berhasil diperbarui.');
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
