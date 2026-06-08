<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
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
        $search = $request->query('q');
        $sort = $request->query('sort', 'created_asc');

        $usersQuery = User::withCount('articles')
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

        switch ($sort) {
            case 'created_asc':
                $usersQuery->orderBy('created_at', 'asc');
                break;
            case 'created_desc':
                $usersQuery->orderBy('created_at', 'desc');
                break;
            case 'name_asc':
                $usersQuery->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $usersQuery->orderBy('name', 'desc');
                break;
            default:
                $usersQuery->orderBy('created_at', 'asc');
        }

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
            'sort',
            'totalStaff',
            'totalAdmin',
            'totalStaffHelpdesk',
            'activeStaff',
            'topContributorCount'
        ));
    }

    public function create(): View
    {
        $categories = Category::orderBy('name')->get();
        return view('admin.users.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['staff'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['exists:categories,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => 'staff',
            'status' => $validated['status'],
            'password' => Hash::make($validated['password']),
        ]);

        // Sync kategori melalui StaffProfile
        if ($validated['role'] === 'staff' && ! empty($validated['categories'])) {
            foreach ($validated['categories'] as $categoryId) {
                StaffProfile::firstOrCreate([
                    'user_id' => $user->id,
                    'category_id' => $categoryId,
                ]);
            }
        }

        return Redirect::route('admin.users.index')
            ->with('success', 'Staf baru berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        $categories = Category::orderBy('name')->get();
        $assignedCategoryIds = $user->staffProfiles()->pluck('category_id')->toArray();
        return view('admin.users.edit', [
            'user' => $user->loadCount('articles'),
            'categories' => $categories,
            'assignedCategoryIds' => $assignedCategoryIds,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['exists:categories,id'],
        ]);

        $role = $user->role === 'admin' ? 'admin' : 'staff';
        $status = $user->role === 'admin' ? 'active' : $validated['status'];

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $role,
            'status' => $status,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Sync kategori melalui StaffProfile hanya jika tetap staff
        if ($role === 'staff') {
            $user->staffProfiles()->delete();
            if (! empty($validated['categories'])) {
                foreach ($validated['categories'] as $categoryId) {
                    StaffProfile::create([
                        'user_id' => $user->id,
                        'category_id' => $categoryId,
                    ]);
                }
            }
        }

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
