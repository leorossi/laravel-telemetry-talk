<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;

class CheckoutService
{
    public function __construct(protected ExternalInventoryService $inventory) {}

    /**
     * Reserve stock for one line of an order.
     *
     * This is a failure a real checkout has to expect: the inventory service
     * answers with a number, and sometimes the number is not enough.
     *
     * The /nightwatch page asks for an absurd quantity so this always trips. The
     * point is that the throw happens here, in a service, two frames below the
     * controller — which is what makes the stack worth reading in Nightwatch.
     *
     * @return array<string, mixed>
     */
    public function reserve(string $sku, int $quantity): array
    {
        $stock = $this->inventory->stockFor($sku);

        $onHand = (int) ($stock['on_hand'] ?? 0);

        if ($onHand < $quantity) {
            throw new InsufficientStockException(sprintf(
                '%s: asked for %d, only %d on hand.',
                $sku,
                $quantity,
                $onHand,
            ));
        }

        return $stock;
    }
}
