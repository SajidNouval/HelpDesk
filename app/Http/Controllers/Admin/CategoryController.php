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

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::orderBy('name')->paginate(10);

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        Category::create($validated);

        return Redirect::route('admin.categories.index')
            ->with('success', 'Kategori baru berhasil ditambahkan.');
    }

    public function assignStaffCategories(Request $request, User $staff): RedirectResponse
    {
        if ($staff->role !== 'staff') {
            abort(404);
        }

        $validated = $request->validate([
            'category_ids' => ['required', 'array'],
            'category_ids.*' => ['exists:categories,id'],
        ]);

        // Hapus kategori lama
        $staff->staffProfiles()->delete();

        // Tambah kategori baru dengan is_busy default false
        foreach ($validated['category_ids'] as $categoryId) {
            StaffProfile::create([
                'user_id' => $staff->id,
                'category_id' => $categoryId,
                'is_busy' => false,
            ]);
        }

        return Redirect::route('admin.categories.index')
            ->with('success', 'Kategori staf berhasil diperbarui.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:categories,name,' . $category->id],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $category->update($validated);

        return Redirect::route('admin.categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Cek jika ada staff yang menggunakan kategori ini
        if ($category->staffProfiles()->exists()) {
            return Redirect::route('admin.categories.index')
                ->with('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh staf.');
        }

        $category->delete();

        return Redirect::route('admin.categories.index')
            ->with('success', 'Kategori berhasil dihapus.');
    }
}
