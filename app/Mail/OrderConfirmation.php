<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your order #{$this->order->number()} is confirmed",
        );
    }

    public function content(): Content
    {
        $this->order->loadMissing(['user', 'items']);

        return new Content(
            markdown: 'mail.orders.confirmation',
            with: [
                'order' => $this->order,
                'url' => route('orders.show', $this->order),
            ],
        );
    }
}
