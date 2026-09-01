<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Drive Log — every practice drive, signed and certified</title>
    <meta name="description" content="A practice-driving log for Virginia learner's permits. Time drives by text message, track night hours by actual sunset, and collect a signature from the adult who was in the car.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|dancing-script:600" rel="stylesheet">
    @if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">

    <header class="mx-auto flex w-full max-w-6xl items-baseline gap-3 px-6 pt-8">
        <span class="font-extrabold tracking-tight text-emerald-800 text-xl dark:text-emerald-400">Drive Log</span>
        <span class="hidden text-sm text-stone-500 sm:inline dark:text-stone-400">for Virginia learner's permits</span>
    </header>

    <main class="mx-auto grid w-full max-w-6xl gap-14 px-6 py-14 lg:grid-cols-[1.1fr_1fr] lg:items-center lg:gap-20 lg:py-24">

        <div class="flex max-w-xl flex-col gap-8">
            <h1 class="text-4xl font-extrabold leading-[1.1] tracking-tight text-balance sm:text-5xl">
                Every practice drive,
                <span class="relative inline-block font-signature text-5xl font-semibold text-emerald-700 sm:text-6xl dark:text-emerald-400">signed
                    <svg class="absolute -bottom-2 left-0 w-full" viewBox="0 0 120 8" fill="none" aria-hidden="true"><path d="M2 6C30 2 80 1 118 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" opacity="0.5"/></svg>
                </span>
                and certified.
            </h1>

            <p class="text-lg leading-relaxed text-stone-600 dark:text-stone-300">
                Virginia asks for 45 hours behind the wheel, 15 of them after sunset, certified under penalty of perjury.
                Drive Log keeps that log for your family — one drive at a time, signed by the adult who was actually in the car.
            </p>

            <ul class="flex flex-col gap-5">
                <li class="flex gap-4">
                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 font-mono text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300" aria-hidden="true">GO</span>
                    <div>
                        <p class="font-semibold">Time drives by text message</p>
                        <p class="text-sm text-stone-600 dark:text-stone-400">Text <span class="font-mono text-xs font-semibold">BEGIN</span> when you pull out and <span class="font-mono text-xs font-semibold">DONE</span> when you park. No app to open at a stop sign.</p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300" aria-hidden="true">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v2M5.6 5.6l1.4 1.4M3 12h2M18.4 5.6 17 7M21 12h-2M4 17h16M7 21h10"/><path d="M8 17a4 4 0 0 1 8 0"/></svg>
                    </span>
                    <div>
                        <p class="font-semibold">Night hours from actual sunset</p>
                        <p class="text-sm text-stone-600 dark:text-stone-400">A 6pm December drive counts toward your 15 night hours; a 6pm June drive doesn't. Drive Log knows the difference.</p>
                    </div>
                </li>
                <li class="flex gap-4">
                    <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 font-signature text-lg text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300" aria-hidden="true">S</span>
                    <div>
                        <p class="font-semibold">Signed while it's fresh</p>
                        <p class="text-sm text-stone-600 dark:text-stone-400">When a drive ends, the supervising adult gets one text and signs in one tap. The report is ready whenever the DMV asks.</p>
                    </div>
                </li>
            </ul>
        </div>

        {{-- Opt-in / sign-in form. One form serves signup and login per FR-1.1; posting is wired in SPEC-002. --}}
        <div class="rounded-2xl border border-stone-200 bg-white p-7 shadow-sm sm:p-9 dark:border-stone-800 dark:bg-stone-900">
            <h2 class="text-xl font-bold tracking-tight">Get started, or sign back in</h2>
            <p class="mt-1 mb-6 text-sm text-stone-600 dark:text-stone-400">Drive Log works over text message — no passwords, ever.</p>

            <form method="POST" action="#" class="flex flex-col gap-5">
                @csrf
                <div>
                    <label for="phone" class="mb-1.5 block text-sm font-semibold">Mobile phone number<span aria-hidden="true">*</span></label>
                    <input id="phone" name="phone" type="tel" autocomplete="tel" inputmode="tel" required placeholder="(555) 123-4567"
                        class="w-full rounded-lg border border-stone-300 bg-stone-50 px-4 py-3 text-base placeholder:text-stone-400 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/25 dark:border-stone-700 dark:bg-stone-950 dark:placeholder:text-stone-500">
                </div>

                <label for="sms-consent" class="flex cursor-pointer gap-3">
                    <input id="sms-consent" name="sms_consent" type="checkbox" required
                        class="mt-0.5 size-4 shrink-0 cursor-pointer accent-emerald-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600">
                    <span class="text-[13px] leading-relaxed text-stone-700 dark:text-stone-300">
                        Yes, I'd like to receive automated text messages from Drive Log at the number above:
                        sign-in links, drive-timer confirmations and check-ins, and signature requests for drives I supervise.
                    </span>
                </label>

                <button type="submit"
                    class="w-full rounded-lg bg-emerald-700 py-3 text-base font-bold text-white transition hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600 dark:bg-emerald-600 dark:text-emerald-950 dark:hover:bg-emerald-500">
                    Text me my sign-in link
                </button>

                <div class="flex flex-col gap-2 border-t border-stone-200 pt-4 text-xs leading-relaxed text-stone-500 dark:border-stone-800 dark:text-stone-400">
                    <p><strong class="font-semibold text-stone-700 dark:text-stone-300">Message frequency:</strong> varies with use — typically 2 to 4 messages per practice drive you record or supervise.</p>
                    <p><strong class="font-semibold text-stone-700 dark:text-stone-300">Standard rates:</strong> message and data rates may apply depending on your mobile plan.</p>
                    <p><strong class="font-semibold text-stone-700 dark:text-stone-300">Help &amp; stop:</strong> reply <span class="font-mono">HELP</span> for help or <span class="font-mono">STOP</span> to cancel at any time. By providing your number and checking the box above, you agree to receive text messages from Drive Log. Consent is not a condition of purchase.</p>
                    <p>
                        <a href="#" class="font-medium text-emerald-700 underline underline-offset-2 dark:text-emerald-400">Terms of Service</a>
                        <span aria-hidden="true"> · </span>
                        <a href="#" class="font-medium text-emerald-700 underline underline-offset-2 dark:text-emerald-400">Privacy Policy</a>
                    </p>
                </div>
            </form>
        </div>
    </main>

    <footer class="mx-auto w-full max-w-6xl px-6 pb-10">
        <p class="border-t border-stone-200 pt-6 text-xs text-stone-500 dark:border-stone-800 dark:text-stone-400">
            Drive Log · We only text numbers that ask us to. Reply <span class="font-mono">STOP</span> to any message to opt out.
        </p>
    </footer>
</body>
</html>
