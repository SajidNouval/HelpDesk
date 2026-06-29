<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * =========================================================================
 * TICKET POLICY - OTORISASI AKSES TIKET
 * =========================================================================
 *
 * Policy ini merangkum semua aturan otorisasi untuk model Ticket.
 * Sebelumnya pengecekan dilakukan secara manual dan tersebar di banyak controller.
 *
 * Cara penggunaan di controller:
 *   $this->authorize('view', $ticket);
 *   $this->authorize('update', $ticket);
 *   Gate::allows('manage', $ticket);
 */
class TicketPolicy
{
    use HandlesAuthorization;

    /**
     * Memeriksa apakah user bisa melihat daftar tiket.
     * Admin dapat melihat semua, staff hanya tiket mereka sendiri.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    /**
     * Memeriksa apakah user bisa melihat detail tiket tertentu.
     * Admin dapat melihat semua tiket.
     * Staff hanya bisa lihat tiket yang di-assign ke mereka.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'staff' && $ticket->staff_id === $user->id;
    }

    /**
     * Memeriksa apakah user bisa mengupdate tiket.
     * Hanya staff yang memiliki tiket tersebut yang bisa update.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'staff' && $ticket->staff_id === $user->id;
    }

    /**
     * Memeriksa apakah user bisa mengupdate status tiket.
     * Hanya staff pemilik tiket yang boleh ubah status.
     */
    public function updateStatus(User $user, Ticket $ticket): bool
    {
        return $user->role === 'staff' && $ticket->staff_id === $user->id;
    }

    /**
     * Memeriksa apakah user bisa menolak tiket.
     * Hanya staff pemilik tiket yang boleh tolak, dan hanya saat assigned/waiting.
     */
    public function reject(User $user, Ticket $ticket): bool
    {
        return $user->role === 'staff'
            && $ticket->staff_id === $user->id
            && in_array($ticket->status, ['assigned', 'waiting']);
    }

    /**
     * Memeriksa apakah user bisa menyelesaikan tiket.
     * Hanya staff pemilik tiket yang boleh complete, saat progress/waiting.
     */
    public function complete(User $user, Ticket $ticket): bool
    {
        return $user->role === 'staff'
            && $ticket->staff_id === $user->id
            && in_array($ticket->status, ['progress', 'waiting']);
    }

    /**
     * Memeriksa apakah user bisa menangguhkan tiket.
     */
    public function suspend(User $user, Ticket $ticket): bool
    {
        return $user->role === 'staff'
            && $ticket->staff_id === $user->id
            && $ticket->status !== 'closed';
    }

    /**
     * Memeriksa apakah user bisa melakukan reassign tiket.
     * Hanya staff pemilik tiket yang boleh reassign.
     */
    public function reassign(User $user, Ticket $ticket): bool
    {
        return $user->role === 'staff' && $ticket->staff_id === $user->id;
    }

    /**
     * Memeriksa apakah user bisa menambah log ke tiket.
     */
    public function addLog(User $user, Ticket $ticket): bool
    {
        return $user->role === 'staff' && $ticket->staff_id === $user->id;
    }

    /**
     * Admin bisa melakukan semua operasi.
     * Ini dipanggil sebelum method lain jika user adalah admin.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null; // Lanjutkan ke method policy yang sesuai
    }
}
