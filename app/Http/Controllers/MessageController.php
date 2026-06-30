<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * =============================================================================
 * MESSAGE CONTROLLER - PENGELOLAAN PESAN TIKET
 * =============================================================================
 * 
 * Controller ini bertanggung jawab untuk mengelola pengiriman dan tampilan
 * pesan dalam sistem tiket helpdesk. Controller ini menangani komunikasi
 * antara guest/customer dan staff melalui fitur live chat pada tiket.
 * 
 * Fitur Utama:
 * - Pengiriman pesan baru ke tiket
 * - Tampilan daftar pesan dalam tiket
 * - Validasi akses berdasarkan status tiket dan kepemilikan
 * - Broadcast real-time pesan melalui WebSocket
 * 
 * Model Terkait:
 * - Message: Model pesan dalam tiket
 * - Ticket: Model tiket
 * 
 * Event Terkait:
 * - MessageSent: Event untuk broadcast real-time pesan
 */
class MessageController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE STORE - KIRIM PESAN BARU
     * =========================================================================
     * 
     * Fungsi: Menyimpan pesan baru ke dalam tiket dan broadcast ke penerima.
     * 
     * Alur Proses:
     * 1. Validasi input: ticket_id dan message
     * 2. Ambil data tiket berdasarkan ticket_id
     * 3. Cek otorisasi pengguna:
     *    - Staff: boleh kirim jika tiket bukan status closed
     *    - Owner: boleh kirim jika tiket bukan status closed/waiting
     *    - Guest: boleh kirim jika tiket bukan status closed
     * 4. Cek status tiket:
     *    - Status 'waiting': tidak boleh chat sama sekali
     *    - Status 'assigned', 'progress': boleh chat
     *    - Status 'closed': tidak boleh chat
     * 5. Tentukan sender_type:
     *    - Jika request dari staff: sender_type = 'staff'
     *    - Jika request dari guest: sender_type = 'guest'
     * 6. Buat record message baru
     * 7. Load relasi sender dan tambahkan sender_name
     * 8. Broadcast event MessageSent untuk real-time update
     * 9. Kembalikan response JSON dengan data pesan
     * 
     * Query yang Digunakan:
     * - Ticket::findOrFail($ticket_id): Ambil tiket atau error 404
     * - Message::create(): Insert pesan baru
     * - $message->load('sender'): Load relasi sender (user)
     * - broadcast(new MessageSent($message)): Kirim event WebSocket
     * 
     * Output:
     * - JSON response dengan status 201 (Created) berisi data message
     * - Error 403 (Forbidden) jika tidak authorized
     */
    public function store(Request $request)
    {
        $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'message' => 'required|string',
        ]);

        $ticket = Ticket::select(['id', 'email', 'status'])->findOrFail($request->ticket_id);

        // Cek otorisasi pengguna
        $myTickets = session()->get('my_tickets', []);
        $guestTicketId = session('guest_ticket_id');
        $isStaff = Auth::check() && Auth::user()->role === 'staff';
        $isOwner = in_array($ticket->id, $myTickets) ||
                   $guestTicketId == $ticket->id ||
                   ($request->query('email') && $request->query('email') === $ticket->email);

        if (!$isStaff && !$isOwner) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$isStaff && in_array($ticket->status, ['assigned', 'waiting', 'closed'])) {
            return response()->json(['error' => 'Tiket sedang tidak terhubung atau sudah ditutup.'], 403);
        }

        // Untuk tiket waiting, tidak boleh chat sama sekali
        if ($ticket->status === 'waiting') {
            return response()->json(['error' => 'Tiket sedang dalam status waiting dan chat tidak diizinkan.'], 403);
        }

        // Tentukan sender type dan id
        $senderType = 'guest'; // Default ke guest
        $senderId = null;

        // Cek apakah sender_type diberikan secara eksplisit dalam request
        if ($request->has('sender_type') && in_array($request->sender_type, ['guest', 'customer'])) {
            $senderType = $request->sender_type;
        }
        // Jika tidak, cek apakah user adalah staff yang terautentikasi
        elseif (Auth::check() && Auth::user()->role === 'staff') {
            $senderType = 'staff';
            $senderId = Auth::id();
        }

        $message = Message::create([
            'ticket_id' => $ticket->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $request->message,
            'is_read' => false,
        ]);

        // Load relasi sender dan tambahkan sender name
        $message->load('sender:id,name');
        if ($senderType === 'staff') {
            $message->sender_name = $message->sender?->name ?? 'Staff';
        } else {
            $message->sender_name = 'Guest';
        }

        // Broadcast pesan
        broadcast(new MessageSent($message));

        return response()->json($message, 201);
    }

    /**
     * =========================================================================
     * 2. METODE INDEX - DAFTAR PESAN TIKET
     * =========================================================================
     * 
     * Fungsi: Menampilkan semua pesan dalam sebuah tiket.
     * 
     * Alur Proses:
     * 1. Ambil data tiket berdasarkan ticketId
     * 2. Cek otorisasi:
     *    - Staff: boleh akses semua tiket
     *    - Owner: boleh akses tiket mereka sendiri
     *    - Guest: boleh akses jika tiket bukan closed
     * 3. Query semua pesan dalam tiket dengan urutan ascending (oldest first)
     * 4. Load relasi sender untuk setiap pesan
     * 5. Tambahkan sender_name berdasarkan sender_type:
     *    - 'staff': nama staff dari relasi sender
     *    - 'guest'/'customer': 'Guest'
     *    - lainnya: 'System'
     * 6. Kembalikan response JSON dengan daftar pesan
     * 
     * Query yang Digunakan:
     * - Ticket::findOrFail($ticketId): Ambil tiket atau error 404
     * - Message::with('sender')->where('ticket_id', $ticketId): 
     *   Ambil semua pesan dengan relasi sender
     * - orderBy('created_at', 'asc'): Urutkan dari yang terlama
     * 
     * Output:
     * - JSON response berisi array pesan dengan struktur:
     *   - id: ID pesan
     *   - sender_type: Tipe pengirim (staff, guest, customer)
     *   - sender_name: Nama pengirim
     *   - message: Isi pesan
     *   - created_at: Waktu pembuatan
     */
    public function index(Request $request, $ticketId)
    {
        $ticket = Ticket::select(['id', 'email', 'status'])->findOrFail($ticketId);
        $myTickets = session()->get('my_tickets', []);
        $guestTicketId = session('guest_ticket_id');

        // Cek apakah authorized: staff atau pemilik tiket
        $isStaff = Auth::check() && Auth::user()->role === 'staff';
        $isOwner = in_array($ticket->id, $myTickets) ||
                   $guestTicketId == $ticket->id ||
                   ($request->query('email') && $request->query('email') === $ticket->email);

        if (!$isStaff && !$isOwner) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Untuk guest, izinkan akses ke pesan tiket untuk semua status aktif
        // Hanya blokir ketika tiket sudah fully closed
        if (!$isStaff && $ticket->status === 'closed') {
            return response()->json(['error' => 'Tiket sudah ditutup.'], 403);
        }

        $messages = Message::select(['id', 'ticket_id', 'sender_type', 'sender_id', 'message', 'created_at'])
            ->with('sender:id,name')
            ->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Tambahkan sender name ke setiap pesan
        $messages = $messages->map(function ($message) {
            if ($message->sender_type === 'staff') {
                $message->sender_name = $message->sender?->name ?? 'Staff';
            } elseif (in_array($message->sender_type, ['guest', 'customer'])) {
                $message->sender_name = 'Guest';
            } else {
                $message->sender_name = 'System';
            }
            return $message;
        });

        return response()->json($messages);
    }
}