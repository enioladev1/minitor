<x-mail::layout>
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            <img
                src="{{ asset('images/logo.png') }}"
                alt="{{ config('app.name') }}"
                width="140"
                style="display: block; margin: 0 auto;"
            >
        </x-mail::header>
    </x-slot:header>

@if($isReopen)
# This issue is back

**{{ $projectName }}** marked this issue resolved, but it just happened again.
@else
# New issue detected

A new exception just occurred in **{{ $projectName }}**.
@endif

**{{ $issueClass }}**

@if($message)
{{ $message }}
@endif

Occurred {{ $occurredAt->diffForHumans() }}, {{ $occurrencesCount }} {{ Str::plural('occurrence', $occurrencesCount) }} total.

<x-mail::button :url="$issueUrl">
View issue
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}

    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
