<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Notifications\ReceiptReady;
use App\Services\CheckoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NightwatchDemoController extends Controller
{
    public function __construct(protected CheckoutService $checkout) {}

    public function index(): View
    {
        return view('nightwatch', [
            'enabled' => (bool) config('nightwatch.enabled') && (bool) config('nightwatch.token'),
            'agent' => (string) config('nightwatch.ingest.uri'),
        ]);
    }

    /**
     * Send a receipt by mail.
     *
     * Nightwatch lists outgoing mail and notifications. Neither Jaeger nor Pulse
     * has any idea that mail exists.
     */
    public function receipt(): RedirectResponse
    {
        $order = Order::query()->latest('placed_at')->with('customer')->firstOrFail();

        Mail::raw(
            sprintf(
                "Thanks for your order %s.\n\nTotal: €%s",
                $order->reference,
                number_format($order->total_cents / 100, 2),
            ),
            fn (Message $message) => $message
                ->to($order->customer->email)
                ->subject('Your receipt'),
        );

        return back()->with('status', sprintf(
            'Mailed a receipt for %s to %s. MAIL_MAILER=log, so it went to the log — but Nightwatch saw the mail.',
            $order->reference,
            $order->customer->email,
        ));
    }

    /**
     * Send a notification, which Nightwatch also tracks.
     */
    public function notify(): RedirectResponse
    {
        $order = Order::query()->latest('placed_at')->with('customer')->firstOrFail();

        $order->customer->notify(new ReceiptReady($order->reference, $order->total_cents));

        return back()->with('status', sprintf(
            'Notified %s about %s.',
            $order->customer->email,
            $order->reference,
        ));
    }

    /**
     * Fail, catch it, report it, and return a 200 anyway.
     *
     * This is the Laravel-shaped error. The request does not blow up — the
     * customer sees a working page — but report() hands the exception to the
     * handler, and Nightwatch captures it with the source code of the line that
     * threw, not just a stack trace.
     */
    public function report(): RedirectResponse
    {
        $order = Order::query()->latest('placed_at')->with('items')->firstOrFail();

        $sku = (string) ($order->items->first()?->sku ?? 'SKU-000000');

        try {
            $this->checkout->reserve($sku, 9999);
        } catch (Throwable $exception) {
            report($exception);
        }

        return back()->with('status', sprintf(
            'Tried to reserve 9999 × %s, caught the failure and reported it — the request finished normally, no 500.',
            $sku,
        ));
    }
}
