<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * =============================================================================
 * ADMIN CATEGORY CONTROLLER - PENGELOLAAN KATEGORI
 * =============================================================================
 * 
 * Controller ini bertanggung jawab untuk mengelola kategori artikel dalam
 * sistem helpdesk. Admin dapat membuat, mengedit, menghapus, dan menugaskan
 * staff ke kategori tertentu.
 * 
 * Fitur Utama:
 * - CRUD kategori (create, read, update, delete)
 * - Pencarian dan sorting kategori
 * - Penugasan staff ke kategori
 * - Validasi hapus kategori (cek staff yang menggunakan)
 * 
 * Model Terkait:
 * - Category: Model kategori
 * - StaffProfile: Relasi staff ke kategori
 * - User: Model staff
 */
class CategoryController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE INDEX - DAFTAR KATEGORI
     * =========================================================================
     * 
     * Fungsi: Menampilkan daftar semua kategori.
     * 
     * Alur Proses:
     * 1. Bangun query dasar dengan hitung jumlah artikel
     * 2. Terapkan filter pencarian jika ada
     * 3. Terapkan sorting berdasarkan parameter
     * 4. Pagination 10 item per halaman
     * 5. Kembalikan view dengan data kategori
     * 
     * Query yang Digunakan:
     * - Category::withCount('articles'): Hitung jumlah artikel per kategori
     * - where('name', 'like', ...): Filter nama kategori
     * - orWhere('description', 'like', ...): Filter deskripsi
     * - orderBy(): Sorting berdasarkan parameter
     * - paginate(10): Pagination
     * 
     * Output:
     * - View 'admin.categories.index'
     */
    public function index(): View
    {
        $query = Category::select(['id', 'name', 'description', 'updated_at'])
            ->withCount('articles');

        if (request('q')) {
            $query->where('name', 'like', '%' . request('q') . '%')
                  ->orWhere('description', 'like', '%' . request('q') . '%');
        }

        $sort = request('sort', 'updated_desc');
        
        switch ($sort) {
            case 'name_asc':
                $query->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('name', 'desc');
                break;
            case 'updated_asc':
                $query->orderBy('updated_at', 'asc');
                break;
            case 'updated_desc':
            default:
                $query->orderBy('updated_at', 'desc');
                break;
        }

        $categories = $query->paginate(10)->withQueryString();

        return view('admin.categories.index', compact('categories'));
    }

    /**
     * =========================================================================
     * 2. METODE CREATE - FORM TAMBAH KATEGORI
     * =========================================================================
     * 
     * Fungsi: Menampilkan form untuk membuat kategori baru.
     * 
     * Output:
     * - View 'admin.categories.create'
     */
    public function create(): View
    {
        return view('admin.categories.create');
    }

    /**
     * =========================================================================
     * 3. METODE STORE - SIMPAN KATEGORI BARU
     * =========================================================================
     * 
     * Fungsi: Menyimpan kategori baru ke database.
     * 
     * Alur Proses:
     * 1. Validasi input: name (wajib, unik), description (opsional)
     * 2. Buat record kategori baru
     * 3. Redirect ke index dengan pesan sukses
     * 
     * Query yang Digunakan:
     * - Category::create($validated): Insert kategori baru
     * 
     * Output:
     * - Redirect ke route('admin.categories.index')
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
            'description' => ['nullable', 'string'],
        ]);

        Category::create($validated);

        return $this->safeRedirect('admin.categories.index')
            ->with('success', 'Kategori berhasil ditambahkan.');
    }

    /**
     * =========================================================================
     * 4. METODE ASSIGN STAFF CATEGORIES - TUGASKAN STAFF KE KATEGORI
     * =========================================================================
     * 
     * Fungsi: Menugaskan staff ke kategori tertentu.
     * 
     * Alur Proses:
     * 1. Validasi role staff
     * 2. Validasi category_ids
     * 3. Hapus semua kategori lama staff
     * 4. Buat record StaffProfile baru untuk setiap kategori
     * 5. Set is_busy = false sebagai default
     * 6. Redirect dengan pesan sukses
     * 
     * Query yang Digunakan:
     * - $staff->staffProfiles()->delete(): Hapus relasi lama
     * - StaffProfile::create(): Buat relasi baru
     * 
     * Output:
     * - Redirect ke route('admin.categories.index')
     */
    public function assignStaffCategories(Request $request, User $staff): RedirectResponse
    {
        if ($staff->role !== 'staff') {
            abort(404);
        }

        $validated = $request->validate([
            'category_ids' => ['required', 'array'],
            'category_ids.*' => ['exists:categories,id'],
        ]);

        $staff->staffProfiles()->delete();

        $data = [];
        $now = now();
        foreach ($validated['category_ids'] as $categoryId) {
            $data[] = [
                'id' => (string) \Illuminate\Support\Str::ulid(),
                'user_id' => $staff->id,
                'category_id' => $categoryId,
                'is_busy' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($data)) {
            StaffProfile::insert($data);
        }

        return $this->safeRedirect('admin.categories.index')
            ->with('success', 'Kategori staf berhasil diperbarui.');
    }

    /**
     * =========================================================================
     * 5. METODE EDIT - FORM EDIT KATEGORI
     * =========================================================================
     * 
     * Fungsi: Menampilkan form edit kategori.
     * 
     * Output:
     * - View 'admin.categories.edit'
     */
    public function edit(Category $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    /**
     * =========================================================================
     * 6. METODE UPDATE - PERBARUI KATEGORI
     * =========================================================================
     * 
     * Fungsi: Memperbarui data kategori.
     * 
     * Alur Proses:
     * 1. Validasi input dengan pengecualian ID kategori saat ini
     * 2. Update record kategori
     * 3. Redirect dengan pesan sukses
     * 
     * Query yang Digunakan:
     * - $category->update($validated): Update kategori
     * 
     * Output:
     * - Redirect ke route('admin.categories.index')
     */
    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,' . $category->id],
            'description' => ['nullable', 'string'],
        ]);

        $category->update($validated);

        return $this->safeRedirect('admin.categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    /**
     * =========================================================================
     * 7. METODE DESTROY - HAPUS KATEGORI
     * =========================================================================
     * 
     * Fungsi: Menghapus kategori dari database.
     * 
     * Alur Proses:
     * 1. Cek apakah ada staff yang menggunakan kategori ini
     * 2. Jika ada, batalkan penghapusan dengan pesan error
     * 3. Jika tidak, hapus kategori
     * 4. Redirect dengan pesan sukses
     * 
     * Query yang Digunakan:
     * - $category->staffProfiles()->exists(): Cek relasi staff
     * - $category->delete(): Hapus kategori
     * 
     * Output:
     * - Redirect ke route('admin.categories.index')
     */
    public function destroy(Category $category): RedirectResponse
    {
        if ($category->staffProfiles()->exists()) {
            return $this->safeRedirect('admin.categories.index')
                ->with('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh staf.');
        }

        $category->delete();

        return $this->safeRedirect('admin.categories.index')
            ->with('success', 'Kategori berhasil dihapus.');
    }
}