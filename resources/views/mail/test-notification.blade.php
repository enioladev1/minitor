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

# It works

This is a test email from **{{ config('app.name') }}**. If you're reading
this, your notification provider is configured correctly and you'll get an
email like this whenever a new issue is detected in one of your projects.

Thanks,<br>
{{ config('app.name') }}

    <x-slot:footer>
        <x-mail::footer>
            © {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
