<x-mail::message>
# Thanks for your order, {{ $order->user->name }}!

We've received your payment for order **#{{ $order->number() }}**.

<x-mail::table>
| Item | Qty | Total |
| :--- | :-: | ----: |
@foreach ($order->items as $item)
| {{ $item->name }} | {{ $item->quantity }} | {{ $item->lineTotal()->format() }} |
@endforeach
| **Total** | | **{{ $order->total()->format() }}** |
</x-mail::table>

Paid with {{ $order->card_brand }} ending in {{ $order->card_last4 }}.

<x-mail::button :url="$url">
View your order
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
