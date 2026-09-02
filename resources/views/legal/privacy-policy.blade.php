<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Privacy Policy &mdash; Swift Boda</title>
        <meta name="description" content="How Swift Boda collects, uses, shares, and protects personal information.">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#FDFDFC] px-6 py-10 text-[#1b1b18] antialiased dark:bg-[#0a0a0a] dark:text-[#EDEDEC] sm:py-16">
        @php($privacyEmail = \App\Models\Configuration::get('privacy_contact_email', config('mail.from.address')))

        <main class="mx-auto w-full max-w-3xl">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-medium text-[#706f6c] transition-colors hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:text-[#EDEDEC]">
                <span aria-hidden="true">&larr;</span>
                Back to Swift Boda
            </a>

            <header class="mt-10 border-b border-[#e3e3e0] pb-10 dark:border-[#3E3E3A]">
                <p class="text-sm font-medium text-[#f53003] dark:text-[#FF4433]">Swift Boda</p>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight sm:text-5xl">Privacy Policy</h1>
                <p class="mt-5 max-w-2xl text-base leading-7 text-[#706f6c] dark:text-[#A1A09A]">
                    This policy explains how Swift Boda handles personal information when you use our customer and rider apps, website, and related services.
                </p>
                <p class="mt-5 text-sm text-[#706f6c] dark:text-[#A1A09A]">Last updated: 2 September 2026</p>
            </header>

            <article class="mt-10 space-y-10 text-[15px] leading-7 text-[#4a4945] dark:text-[#C8C7C1]">
                <section>
                    <h2 class="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">1. Who we are and this policy</h2>
                    <p class="mt-3">
                        Swift Boda provides ride-hailing, delivery, rider, wallet, and support services in Uganda. In this policy, “Swift Boda”, “we”, “us”, and “our” refer to the operator of the Swift Boda services. We process personal information in accordance with applicable law, including Uganda’s Data Protection and Privacy Act, 2019 and its regulations.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">2. Information we collect</h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5 marker:text-[#f53003]">
                        <li><strong>Account and identity information:</strong> name, phone number, email address, profile photo, referral information, and account status.</li>
                        <li><strong>Location and trip information:</strong> pickup and drop-off locations, addresses, rider location updates, trip route-related data, requested times, trip status, and trip history.</li>
                        <li><strong>Delivery information:</strong> recipient name and phone number, package description, size or weight, signature requirement, and delivery proof photo where provided.</li>
                        <li><strong>Rider and vehicle information:</strong> rider reference, date of birth, gender, licence and national-ID details, KYC documents, vehicle details, insurance information, availability, and operating location.</li>
                        <li><strong>Payment and wallet information:</strong> wallet balance, transaction records, mobile-money number, payment method, gateway and network references, and withdrawal details. We do not store payment-card numbers in this application.</li>
                        <li><strong>Device and technical information:</strong> device identifier and type, app version, active-device status, and push-notification token.</li>
                        <li><strong>Support and safety information:</strong> support tickets, messages, attachments, ratings, cancellation reasons, saved places, and emergency-contact details.</li>
                        <li><strong>Website information:</strong> waitlist name, email address, and optional phone number, together with essential session and security data needed to operate the website.</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">3. How we use information</h2>
                    <p class="mt-3">We use personal information to:</p>
                    <ul class="mt-3 list-disc space-y-2 pl-5 marker:text-[#f53003]">
                        <li>create and secure accounts, verify OTP logins, and provide customer and rider services;</li>
                        <li>match customers with eligible nearby riders, operate rides and deliveries, and provide trip updates;</li>
                        <li>calculate fares, administer promotions, process wallet activity, prevent duplicate payments, and maintain transaction records;</li>
                        <li>review rider KYC and vehicle information, support safety, investigate incidents, and prevent fraud or misuse;</li>
                        <li>send service communications and push notifications, including trip and payment updates;</li>
                        <li>respond to support requests, improve our services, and meet legal, regulatory, accounting, and audit obligations.</li>
                    </ul>
                    <p class="mt-3">We process information where necessary to provide the service you request, comply with legal obligations, protect legitimate safety and operational interests, or where you have given consent when consent is required.</p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">4. When we share information</h2>
                    <p class="mt-3">We share only the information reasonably needed for the relevant purpose. This can include:</p>
                    <ul class="mt-3 list-disc space-y-2 pl-5 marker:text-[#f53003]">
                        <li><strong>Customers and riders:</strong> names, phone numbers, trip or delivery details, location, and relevant vehicle or rider information to complete a booking safely.</li>
                        <li><strong>Service providers:</strong> SMS, push-notification, cloud-storage, payment, and mobile-money providers that help us deliver the service.</li>
                        <li><strong>Authorities and professional advisers:</strong> where required by law, to protect rights, safety, or property, or to investigate suspected fraud or unlawful activity.</li>
                        <li><strong>Business transfers:</strong> in connection with a merger, financing, acquisition, or sale of assets, subject to applicable law.</li>
                    </ul>
                    <p class="mt-3">We do not sell personal information.</p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">5. Retention, security, and international processing</h2>
                    <p class="mt-3">
                        We retain information only for as long as reasonably necessary for the purposes in this policy, including service delivery, safety, dispute resolution, legal compliance, and record keeping. Retention periods may differ by data type and legal requirement.
                    </p>
                    <p class="mt-3">
                        We use reasonable technical and organisational safeguards designed to protect personal information, including access controls, authentication, and security monitoring. No system is completely secure, so please protect your account, device, and OTP codes. Our providers may process information outside Uganda; where this occurs, we take steps intended to ensure appropriate safeguards consistent with applicable law.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">6. Your choices and rights</h2>
                    <p class="mt-3">
                        Subject to applicable law, you may request access to, correction of, deletion of, or information about the processing of your personal information. You may also object to or request restriction of certain processing where applicable. You can update profile, saved-place, emergency-contact, device, and notification information through the app where those controls are available.
                    </p>
                    <p class="mt-3">
                        You may manage push notifications through your device settings. Some information is required to provide rides, deliveries, wallet services, or account security; limiting that information may prevent us from providing those features.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">7. Children</h2>
                    <p class="mt-3">Swift Boda is not intended for children who cannot lawfully use the service. If you believe a child has provided us personal information without appropriate authority, please contact us so we can review the request.</p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">8. Changes to this policy</h2>
                    <p class="mt-3">We may update this policy as our services or legal obligations change. We will publish the revised version here and update the “Last updated” date. Where required, we will provide additional notice or request consent.</p>
                </section>

                <section class="rounded-xl border border-[#e3e3e0] bg-white p-6 dark:border-[#3E3E3A] dark:bg-[#161615]">
                    <h2 class="text-xl font-semibold text-[#1b1b18] dark:text-[#EDEDEC]">9. Contact and complaints</h2>
                    <p class="mt-3">
                        For privacy questions or to exercise your rights, contact us at
                        <a class="font-medium text-[#c52a02] underline underline-offset-2 dark:text-[#FF6B5D]" href="mailto:{{ $privacyEmail }}">{{ $privacyEmail }}</a>
                        or use the Support Tickets section of the Swift Boda app. We may ask for information needed to verify your identity before acting on a request.
                    </p>
                    <p class="mt-3">
                        You may also have the right to raise a concern with Uganda’s Personal Data Protection Office.
                    </p>
                </section>
            </article>

            <footer class="mt-12 border-t border-[#e3e3e0] pt-6 text-sm text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]">
                &copy; {{ now()->year }} Swift Boda. All rights reserved.
            </footer>
        </main>
    </body>
</html>
