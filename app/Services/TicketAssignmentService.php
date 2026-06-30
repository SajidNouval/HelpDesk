<?php

namespace App\Services;

use App\Models\StaffProfile;
use App\Models\Ticket;
use App\Models\TicketLog;
use Illuminate\Support\Facades\DB;

/**
 * =========================================================================
 * TICKET ASSIGNMENT SERVICE - LAYANAN PENUGASAN TIKET
 * =========================================================================
 *
 * Service ini merangkum seluruh logika penugasan (assignment) tiket ke staff.
 * Dibuat untuk menghilangkan duplikasi kode di:
 *  - TicketController::assignTicketToAvailableStaff()
 *  - TicketController::assignReportToStaff()
 *  - Staff/TicketController::complete() (next ticket assignment)
 *  - Staff/TicketController::suspend() (next ticket assignment)
 *
 * Metode Utama:
 *  - assignLiveChat(Ticket): Assign tiket live chat ke staff tersedia
 *  - assignReport(Ticket): Assign laporan ke staff dengan waiting paling sedikit
 *  - assignNextWaiting(StaffProfile): Assign tiket waiting berikutnya ke staff
 */
class TicketAssignmentService
{
    /**
     * Menugaskan tiket live chat ke staff yang paling available di kategori yang sama.
     * Menggunakan load balancing: staff dengan tiket aktif paling sedikit diprioritaskan.
     * Berjalan dalam transaksi database dengan lockForUpdate untuk mencegah race condition.
     *
     * @param  Ticket  $ticket  Tiket yang akan di-assign
     * @return StaffProfile|null  Staff yang di-assign, atau null jika tidak ada
     */
    public function assignLiveChat(Ticket $ticket): ?StaffProfile
    {
        return DB::transaction(function () use ($ticket) {
            $staffProfiles = StaffProfile::select(['id', 'user_id', 'category_id', 'is_busy'])
                ->where('category_id', $ticket->category_id)
                ->where('is_busy', false)
                ->with([
                    'user' => function ($q) {
                        $q->select(['id', 'name'])
                          ->withCount([
                              'tickets as active_tickets' => function ($q2) {
                                  $q2->whereIn('status', ['assigned', 'progress']);
                              },
                              'tickets as waiting_count' => function ($q2) {
                                  $q2->where('status', 'waiting');
                              }
                          ]);
                    }
                ])
                ->lockForUpdate()
                ->get();

            if ($staffProfiles->isEmpty()) {
                return null;
            }

            $best = $staffProfiles
                ->map(function ($profile) {
                    return [
                        'profile'        => $profile,
                        'active_tickets' => $profile->user->active_tickets ?? 0,
                        'waiting_count'  => $profile->user->waiting_count ?? 0,
                    ];
                })
                ->sortBy(function ($item) {
                    return [$item['active_tickets'], $item['waiting_count'], $item['profile']->id];
                })
                ->first();

            if (!$best) {
                return null;
            }

            $bestStaff = $best['profile'];

            $ticket->update([
                'staff_id'    => $bestStaff->user_id,
                'status'      => 'assigned',
                'assigned_at' => now(),
            ]);

            $bestStaff->update(['is_busy' => true]);

            TicketLog::create([
                'ticket_id'   => $ticket->id,
                'action'      => 'assigned',
                'description' => 'Tiket di-assign ke staff: ' . $bestStaff->user->name .
                    ' (aktif: ' . $best['active_tickets'] .
                    ', waiting: ' . $best['waiting_count'] . ')',
            ]);

            return $bestStaff;
        });
    }

    /**
     * Menugaskan laporan ke staff dengan jumlah waiting tickets paling sedikit.
     *
     * @param  Ticket  $ticket  Tiket laporan yang akan di-assign
     * @return StaffProfile|null  Staff yang di-assign, atau null jika tidak ada
     */
    public function assignReport(Ticket $ticket): ?StaffProfile
    {
        $staffProfiles = StaffProfile::select(['id', 'user_id', 'category_id', 'is_busy'])
            ->where('category_id', $ticket->category_id)
            ->with([
                'user' => function ($q) {
                    $q->select(['id', 'name'])
                      ->withCount([
                          'tickets as waiting_count' => function ($q2) {
                              $q2->where('status', 'waiting');
                          }
                      ]);
                }
            ])
            ->get();

        if ($staffProfiles->isEmpty()) {
            return null;
        }

        $best = $staffProfiles
            ->map(function ($profile) {
                return [
                    'profile'       => $profile,
                    'waiting_count' => $profile->user->waiting_count ?? 0,
                ];
            })
            ->sortBy(function ($item) {
                return [$item['waiting_count'], $item['profile']->id];
            })
            ->first();

        if (!$best) {
            return null;
        }

        $bestStaff = $best['profile'];

        $ticket->update([
            'staff_id'    => $bestStaff->user_id,
            'assigned_at' => now(),
            'status'      => 'waiting',
        ]);

        TicketLog::create([
            'ticket_id'   => $ticket->id,
            'action'      => 'assigned',
            'description' => 'Laporan ditugaskan ke staf: ' . $bestStaff->user->name .
                ' (waiting load: ' . $best['waiting_count'] . ')',
        ]);

        return $bestStaff;
    }

    /**
     * Setelah staff menyelesaikan atau menangguhkan tiket, cari tiket waiting berikutnya
     * di kategori yang sama dan assign ke staff paling available.
     *
     * @param  StaffProfile  $completedStaffProfile  Staff profile yang baru saja selesai
     * @return Ticket|null  Tiket berikutnya yang di-assign, atau null jika tidak ada
     */
    public function assignNextWaiting(StaffProfile $completedStaffProfile): ?Ticket
    {
        $nextTicket = Ticket::where('category_id', $completedStaffProfile->category_id)
            ->where('status', 'waiting')
            ->whereNull('staff_id')
            ->oldest()
            ->first();

        if (!$nextTicket) {
            return null;
        }

        $availableStaff = StaffProfile::select(['id', 'user_id', 'category_id', 'is_busy'])
            ->where('category_id', $completedStaffProfile->category_id)
            ->where('is_busy', false)
            ->with([
                'user' => function ($q) {
                    $q->select(['id', 'name'])
                      ->withCount([
                          'tickets as tickets_count' => function ($q2) {
                              $q2->whereIn('status', ['assigned', 'progress', 'waiting']);
                          }
                      ]);
                }
            ])
            ->get()
            ->sortBy(function ($profile) {
                return $profile->user->tickets_count ?? 0;
            })
            ->first();

        if (!$availableStaff) {
            return null;
        }

        $nextTicket->update([
            'staff_id'    => $availableStaff->user_id,
            'status'      => 'assigned',
            'assigned_at' => now(),
        ]);

        $availableStaff->update(['is_busy' => true]);

        TicketLog::create([
            'ticket_id'   => $nextTicket->id,
            'action'      => 'assigned',
            'description' => 'Tiket di-assign ke staff: ' . $availableStaff->user->name . ' (antrian berikutnya)',
        ]);

        // Broadcast queue update for other tickets in this category
        self::broadcastQueueUpdateForCategory($completedStaffProfile->category_id);

        return $nextTicket;
    }

    /**
     * Memperbarui posisi antrean dan menyiarkannya secara real-time untuk seluruh tiket
     * live chat yang sedang menunggu (waiting) di kategori tertentu.
     *
     * @param  string  $categoryId  ID kategori
     * @return void
     */
    public static function broadcastQueueUpdateForCategory(string $categoryId): void
    {
        $waitingTickets = Ticket::select(['id', 'category_id', 'created_at'])
            ->where('type', 'livechat')
            ->where('category_id', $categoryId)
            ->where('status', 'waiting')
            ->whereNull('staff_id')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($waitingTickets as $index => $t) {
            $position = $index + 1;
            $estimatedMinutes = $position * 2;
            broadcast(new \App\Events\QueuePositionUpdated($t, $position, $estimatedMinutes));
        }
    }
}
