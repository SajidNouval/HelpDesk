<section class="space-y-4">
    <header>
        <h2 class="text-lg font-semibold text-gray-900">
            Hapus Akun
        </h2>
        <p class="mt-1 text-sm text-gray-500">
            Setelah akun dihapus, semua data dan resource akan dihapus permanen. Unduh data Anda terlebih dahulu jika diperlukan.
        </p>
    </header>

    <div class="pt-4 border-t border-gray-200">
        <button
            type="button"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
            class="h-10 px-4 rounded-xl border border-red-600 text-red-600 hover:bg-red-50 text-sm font-medium transition"
        >
            Hapus Akun Saya
        </button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-semibold text-gray-900">
                Konfirmasi Hapus Akun
            </h2>

            <p class="mt-2 text-sm text-gray-600">
                Tindakan ini tidak dapat dibatalkan. Semua data akun Anda akan dihapus permanen. Masukkan kata sandi untuk mengonfirmasi.
            </p>

            <div class="mt-6">
                <label for="password_delete" class="block text-sm font-medium text-gray-700 mb-2 sr-only">
                    Kata Sandi
                </label>
                <input
                    id="password_delete"
                    name="password"
                    type="password"
                    placeholder="Masukkan kata sandi"
                    class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                >
                @if ($errors->userDeletion->get('password'))
                    <p class="mt-1 text-sm text-red-600">{{ $errors->userDeletion->first('password') }}</p>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    x-on:click="$dispatch('close')"
                    class="h-10 px-4 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium transition"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    class="h-10 px-4 rounded-xl border border-red-600 bg-red-600 text-white hover:bg-red-700 text-sm font-medium transition"
                >
                    Hapus Akun Permanen
                </button>
            </div>
        </form>
    </x-modal>
</section>
