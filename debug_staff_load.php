<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\StaffProfile;
use App\Models\Ticket;
foreach (StaffProfile::with('user')->get() as $p) {
    echo 'profile=' . $p->id . ' user=' . $p->user->name . ' busy=' . ($p->is_busy ? '1' : '0') . ' category=' . $p->category_id . PHP_EOL;
    echo 'active=' . Ticket::where('staff_id', $p->user_id)->whereIn('status', ['assigned','progress'])->count() . ' waiting=' . Ticket::where('staff_id', $p->user_id)->where('status', 'waiting')->count() . PHP_EOL;
}
