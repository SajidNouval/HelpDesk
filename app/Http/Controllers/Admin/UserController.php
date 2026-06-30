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

/**
 * =============================================================================
 * ADMIN USER CONTROLLER - PENGELOLAAN PENGGUNA ADMIN
 * =============================================================================
 * 
 * Controller ini bertanggung jawab untuk mengelola pengguna sistem (khususnya
 * staff) dari panel admin. Admin dapat membuat, mengedit, menghapus, dan
 * menugaskan kategori ke staff.
 * 
 * Fitur Utama:
 * - CRUD pengguna (create, read, update, delete)
 * - Pencarian dan sorting pengguna
 * - Penugasan kategori ke staff
 * - Manajemen role (admin/staff) dan status (active/inactive)
 * - Validasi self-delete prevention
 * 
 * Model Terkait:
 * - User: Model pengguna
 * - Category: Kategori untuk penugasan staff
 * - StaffProfile: Relasi staff ke kategori
 */
class UserController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE INDEX - DAFTAR PENGGUNA
     * =========================================================================
     * 
     * Fungsi: Menampilkan daftar semua pengguna.
     * 
     * Alur Proses:
     * 1. Bangun query dasar dengan hitung jumlah artikel
     * 2. Terapkan filter pencarian (name, email)
     * 3. Terapkan sorting berdasarkan parameter
     * 4. Pagination 10 item per halaman
     * 5. Hitung statistik pengguna (total, admin, staff, active)
     * 6. Hitung top contributor
     * 7. Kembalikan view dengan data lengkap
     * 
     * Query yang Digunakan:
     * - User::withCount('articles'): Hitung artikel per user
     * - when($search, ...): Filter name atau email
     * - orderBy(): Sorting
     * - paginate(10): Pagination
     * 
     * Output:
     * - View 'admin.users.index'
     */
    public function index(Request $request): View
    {
        $search = $request->query('q');
        $sort = $request->query('sort', 'created_asc');

        $usersQuery = User::select(['id', 'name', 'email', 'role', 'status', 'created_at'])
            ->withCount('articles')
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

        $userStats = User::selectRaw("
            count(*) as total,
            count(case when role = 'admin' then 1 end) as admins,
            count(case when role = 'staff' then 1 end) as staff,
            count(case when role = 'staff' and status = 'active' then 1 end) as active_staff
        ")->first();
        $totalStaff = $userStats->total;
        $totalAdmin = $userStats->admins;
        $totalStaffHelpdesk = $userStats->staff;
        $activeStaff = $userStats->active_staff;

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

    /**
     * =========================================================================
     * 2. METODE CREATE - FORM TAMBAH STAFF
     * =========================================================================
     * 
     * Fungsi: Menampilkan form untuk membuat staff baru.
     * 
     * Output:
     * - View 'admin.users.create' dengan daftar kategori
     */
    public function create(): View
    {
        $categories = Category::select(['id', 'name'])->orderBy('name')->get();
        return view('admin.users.create', compact('categories'));
    }

    /**
     * =========================================================================
     * 3. METODE STORE - SIMPAN STAFF BARU
     * =========================================================================
     * 
     * Fungsi: Menyimpan staff baru ke database.
     * 
     * Alur Proses:
     * 1. Validasi input (name, email, role, status, password, categories)
     * 2. Buat user dengan role = 'staff'
     * 3. Hash password
     * 4. Buat StaffProfile untuk setiap kategori yang dipilih
     * 5. Redirect dengan pesan sukses
     * 
     * Query yang Digunakan:
     * - User::create([...]): Insert user baru
     * - StaffProfile::firstOrCreate([...]): Buat relasi kategori
     * 
     * Output:
     * - Redirect ke route('admin.users.index')
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:50', 'unique:users,email'],
            'role' => ['required', Rule::in(['staff'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
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

        if ($validated['role'] === 'staff' && ! empty($validated['categories'])) {
            $data = [];
            $now = now();
            foreach ($validated['categories'] as $categoryId) {
                $data[] = [
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'user_id' => $user->id,
                    'category_id' => $categoryId,
                    'is_busy' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (!empty($data)) {
                StaffProfile::insert($data);

                // Cek antrean tiket waiting untuk kategori yang baru ditugaskan ke staf ini
                $assignmentService = resolve(\App\Services\TicketAssignmentService::class);
                $newProfiles = StaffProfile::where('user_id', $user->id)
                    ->whereIn('category_id', $validated['categories'])
                    ->get();
                    
                foreach ($newProfiles as $profile) {
                    $profile->refresh();
                    if (!$profile->is_busy) {
                        $assignmentService->assignNextWaiting($profile);
                    }
                }
            }
        }

        return $this->safeRedirect('admin.users.index')
            ->with('success', 'Staf baru berhasil dibuat.');
    }

    /**
     * =========================================================================
     * 4. METODE EDIT - FORM EDIT STAFF
     * =========================================================================
     * 
     * Fungsi: Menampilkan form edit staff.
     * 
     * Output:
     * - View 'admin.users.edit' dengan data staff dan kategori
     */
    public function edit(User $user): View
    {
        $categories = Category::select(['id', 'name'])->orderBy('name')->get();
        $assignedCategoryIds = $user->staffProfiles()->pluck('category_id')->toArray();
        return view('admin.users.edit', [
            'user' => $user->loadCount('articles'),
            'categories' => $categories,
            'assignedCategoryIds' => $assignedCategoryIds,
        ]);
    }

    /**
     * =========================================================================
     * 5. METODE UPDATE - PERBARUI STAFF
     * =========================================================================
     * 
     * Fungsi: Memperbarui data staff.
     * 
     * Alur Proses:
     * 1. Validasi input
     * 2. Tentukan role (admin tetap admin, staff bisa diubah)
     * 3. Update data user
     * 4. Jika ada password baru, hash dan update
     * 5. Sync kategori (hapus lama, buat baru)
     * 6. Redirect dengan pesan sukses
     * 
     * Query yang Digunakan:
     * - $user->update([...]): Update user
     * - $user->staffProfiles()->delete(): Hapus relasi lama
     * - StaffProfile::create([...]): Buat relasi baru
     * 
     * Output:
     * - Redirect ke route('admin.users.index')
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:50', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'staff'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', 'string', 'min:8', 'max:255', 'confirmed'],
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

        if ($role === 'staff') {
            $user->staffProfiles()->delete();
            if (! empty($validated['categories'])) {
                $data = [];
                $now = now();
                foreach ($validated['categories'] as $categoryId) {
                    $data[] = [
                        'id' => (string) \Illuminate\Support\Str::ulid(),
                        'user_id' => $user->id,
                        'category_id' => $categoryId,
                        'is_busy' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                if (!empty($data)) {
                    StaffProfile::insert($data);

                    // Cek antrean tiket waiting untuk kategori yang baru ditugaskan ke staf ini
                    $assignmentService = resolve(\App\Services\TicketAssignmentService::class);
                    $newProfiles = StaffProfile::where('user_id', $user->id)
                        ->whereIn('category_id', $validated['categories'])
                        ->get();
                        
                    foreach ($newProfiles as $profile) {
                        $profile->refresh();
                        if (!$profile->is_busy) {
                            $assignmentService->assignNextWaiting($profile);
                        }
                    }
                }
            }
        }

        return $this->safeRedirect('admin.users.index')
            ->with('success', 'Data staf berhasil diperbarui.');
    }

    /**
     * =========================================================================
     * 6. METODE DESTROY - HAPUS PENGGUNA
     * =========================================================================
     * 
     * Fungsi: Menghapus pengguna dari database.
     * 
     * Alur Proses:
     * 1. Cek apakah user mencoba menghapus diri sendiri
     * 2. Jika ya, batalkan dengan error
     * 3. Jika tidak, hapus user
     * 4. Redirect dengan pesan sukses
     * 
     * Query yang Digunakan:
     * - $user->delete(): Hapus user
     * 
     * Output:
     * - Redirect ke route('admin.users.index')
     */
    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return Redirect::back()->withErrors(['delete' => 'Anda tidak bisa menghapus akun sendiri.']);
        }

        $user->delete();

        return $this->safeRedirect('admin.users.index')
            ->with('success', 'Pengguna berhasil dihapus.');
    }
}