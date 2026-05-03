<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tickets = App\Models\Ticket::with('messages')->get();
echo "Total tickets: " . $tickets->count() . PHP_EOL . PHP_EOL;

foreach ($tickets as $ticket) {
    echo "Ticket #" . $ticket->id . " - " . $ticket->subject . PHP_EOL;
    echo "Messages: " . $ticket->messages->count() . PHP_EOL;

    if ($ticket->messages->count() > 0) {
        foreach ($ticket->messages as $msg) {
            echo "  " . $msg->sender_type . ': ' . $msg->message . PHP_EOL;
        }
    }
    echo PHP_EOL;
}