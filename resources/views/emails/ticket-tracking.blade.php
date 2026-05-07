<div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h2 style="color: #111827;">Tiket #{{ $ticket->id }} Telah Diverifikasi</h2>
    <p>Terima kasih, tiket Anda sudah berhasil diverifikasi dan diproses.</p>
    <p>Klik link berikut untuk melihat status, log progress, dan riwayat pesan:</p>
    <p><a href="{{ $trackingUrl }}" style="color: #2563eb; text-decoration: none;">{{ $trackingUrl }}</a></p>
    <p>Jika staff sudah tersedia, Anda juga akan menerima pesan lebih lanjut di sesi live chat.</p>
</div>
