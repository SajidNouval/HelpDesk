<div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h2 style="color: #dc2626;">Tiket #{{ $ticket->id }} - Staff Tidak Dapat Menerima</h2>
    <p>Halo {{ $ticket->name }},</p>
    <p>Kami ingin memberitahu bahwa staff kami tidak dapat menerima tiket Anda saat ini.</p>
    
    <div style="background-color: #fee2e2; border-left: 4px solid #dc2626; padding: 16px; margin: 20px 0;">
        <p style="margin: 0; color: #991b1b;">
            <strong>Tiket #{{ $ticket->id }}</strong> telah ditutup karena staff tidak tersedia untuk menangani masalah Anda pada saat ini.
        </p>
    </div>
    
    <p>Kami mohon maaf atas ketidaknyamanannya. Silakan hubungi kami kembali atau buat tiket baru jika Anda memerlukan bantuan lebih lanjut.</p>
    
    <p style="color: #6b7280; font-size: 14px; margin-top: 30px;">
        Terima kasih,<br>
        Tim Helpdesk
    </p>
</div>
