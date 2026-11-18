<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A domain event, not a telemetry event.
 *
 * The app does not know Pulse exists. It says "an order was placed" and moves
 * on; the recorder in App\Pulse\Recorders\Orders decides what that means for
 * the dashboard. That is the whole trick behind custom Pulse cards.
 */
class OrderPlaced
{
    use Dispatchable;

    public function __construct(public Order $order) {}
}
