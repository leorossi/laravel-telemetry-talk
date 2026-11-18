@extends('layouts.app')

@section('title', 'Nightwatch, in action')

@section('content')
    <h1>Nightwatch, in action</h1>
    <p class="lede">
        Three buttons, three things that <b>only Nightwatch</b> can show you. Mail and notifications are invisible to
        Jaeger and to Pulse, and the third button fails <i>without</i> breaking the page.
    </p>

    <div class="card" style="margin-bottom: 1.5rem;">
        @if ($enabled)
            <span class="pill good">enabled</span>
            the app is sending to the agent at <code>{{ $agent }}</code>.
        @else
            <span class="pill warn">off</span>
            set <code>NIGHTWATCH_ENABLED=true</code> and a <code>NIGHTWATCH_TOKEN</code>, and make sure the agent is up.
        @endif
    </div>

    <h2>The three things</h2>
    <div class="grid">
        <div class="card">
            <h3>1 &mdash; Mail</h3>
            <p>
                Mails a receipt for the last order. <code>MAIL_MAILER=log</code>, so nothing leaves the machine — but
                Nightwatch records the mail anyway.
            </p>
            <form method="POST" action="{{ route('demo.nightwatch.receipt') }}">
                @csrf
                <button type="submit" class="btn primary">Send a receipt</button>
            </form>
        </div>

        <div class="card">
            <h3>2 &mdash; Notifications</h3>
            <p>
                A real Laravel notification, over the mail channel. Same story: the other two lenses have no idea it
                happened.
            </p>
            <form method="POST" action="{{ route('demo.nightwatch.notify') }}">
                @csrf
                <button type="submit" class="btn primary">Notify the customer</button>
            </form>
        </div>

        <div class="card">
            <h3>3 &mdash; An error that does not break the page</h3>
            <p>
                Asks the inventory service for 9999 units, which always fails. The exception is caught and handed to
                <code>report()</code>, so the request finishes normally — <b>no 500, no error page</b>.
            </p>
            <form method="POST" action="{{ route('demo.nightwatch.report') }}">
                @csrf
                <button type="submit" class="btn primary">Report a handled error</button>
            </form>
        </div>
    </div>

    <h2>What to look for on the dashboard</h2>
    <div class="card">
        <ul style="margin: 0; padding-left: 1.1rem;">
            <li>
                <b>Mail</b> and <b>notifications</b> — a tab no other tool in this talk has.
            </li>
            <li>
                The reported error, with <b>the source code of the line that threw</b>. Not a stack trace: the code.
                That is the difference between knowing <i>where</i> and knowing <i>what</i>.
            </li>
            <li>
                The request finished normally anyway — an error in your monitoring does not have to be an outage for
                your customer. (For the opposite case, <code>/fail</code> throws for real.)
            </li>
            <li>
                For the side by side: hit <a href="{{ route('demo.slow') }}">/slow</a>, then read its timeline here next
                to the waterfall in Jaeger. Same request, two views.
            </li>
        </ul>
    </div>

    <p style="color: var(--muted); font-size: 0.85rem; margin-top: 1.5rem;">
        The agent runs as its own container (<code>telemetry-demo-nightwatch</code>), the same shape as an OTel
        collector: the app posts to a local hop with a <code>0.5 s</code> timeout, and the agent owns the connection to
        Laravel. Dashboard:
        <a href="https://nightwatch.laravel.com/dashboard" target="_blank" rel="noreferrer">nightwatch.laravel.com/dashboard</a>.
    </p>
@endsection
