<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Swift Boda &mdash; Coming Soon</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] antialiased min-h-screen flex items-center justify-center px-6 py-12">
        <main class="w-full max-w-lg">
            <div class="text-center mb-10">
                <span class="inline-flex items-center gap-2 rounded-full border border-[#e3e3e0] dark:border-[#3E3E3A] px-3 py-1 text-xs font-medium text-[#706f6c] dark:text-[#A1A09A] mb-6">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#f53003] dark:bg-[#FF4433]"></span>
                    Coming soon
                </span>
                <h1 class="text-3xl sm:text-4xl font-semibold tracking-tight mb-3">Swift Boda is on its way</h1>
                <p class="text-[#706f6c] dark:text-[#A1A09A] text-base leading-relaxed">
                    We're putting the finishing touches on rides and deliveries. Join the waiting list and we'll let you know the moment we launch.
                </p>
            </div>

            @if (session('waitlist_joined'))
                <div class="mb-6 rounded-lg border border-green-200 dark:border-green-900 bg-green-50 dark:bg-green-950/40 text-green-800 dark:text-green-300 text-sm px-4 py-3 text-center">
                    You're on the list! We'll be in touch soon.
                </div>
            @else
                <form method="POST" action="{{ route('waitlist.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="sr-only">Name</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="Name (optional)"
                            class="w-full rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] px-4 py-2.5 text-sm placeholder:text-[#A1A09A] focus:outline-none focus:ring-2 focus:ring-[#f53003] dark:focus:ring-[#FF4433]"
                        >
                        @error('name')
                            <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="sr-only">Email address</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="Email address"
                            required
                            class="w-full rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] px-4 py-2.5 text-sm placeholder:text-[#A1A09A] focus:outline-none focus:ring-2 focus:ring-[#f53003] dark:focus:ring-[#FF4433]"
                        >
                        @error('email')
                            <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="phone" class="sr-only">Phone number</label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            value="{{ old('phone') }}"
                            placeholder="Phone number (optional)"
                            class="w-full rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] px-4 py-2.5 text-sm placeholder:text-[#A1A09A] focus:outline-none focus:ring-2 focus:ring-[#f53003] dark:focus:ring-[#FF4433]"
                        >
                        @error('phone')
                            <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-[#1b1b18] dark:bg-[#eeeeec] text-white dark:text-[#1C1C1A] text-sm font-medium px-4 py-2.5 hover:bg-black dark:hover:bg-white transition-colors"
                    >
                        Join the waiting list
                    </button>
                </form>
            @endif

            <p class="mt-8 text-center text-xs text-[#706f6c] dark:text-[#A1A09A]">
                <a href="{{ route('privacy-policy') }}" class="underline underline-offset-2 hover:text-[#1b1b18] dark:hover:text-[#EDEDEC]">Privacy Policy</a>
            </p>
        </main>
    </body>
</html>
