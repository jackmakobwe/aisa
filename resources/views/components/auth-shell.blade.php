{{-- Two-panel frame for the sign-in and sign-up pages: why an account helps (left), the form (right). --}}
<div class="container-page py-10 sm:py-14">
    <div class="mx-auto grid max-w-5xl overflow-hidden rounded-[1.5rem] border border-line bg-white shadow-[0_20px_50px_-24px_rgba(34,40,43,0.25)] lg:grid-cols-[0.9fr_1.1fr]">
        <aside class="relative overflow-hidden bg-forest p-7 text-white sm:p-10">
            <span class="pointer-events-none absolute -right-16 -bottom-16 h-56 w-56 rounded-full bg-white/5" aria-hidden="true"></span>
            <span class="badge bg-white/15 text-white"><i data-lucide="heart-handshake" class="h-3.5 w-3.5"></i>{{ __('account.panel_badge') }}</span>
            <p class="mt-4 font-serif text-3xl leading-tight text-white">{{ __('account.panel_title') }}</p>
            <ul class="mt-7 space-y-4">
                @foreach (__('account.panel_points') as $point)
                    <li class="flex gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white/15">
                            <i data-lucide="{{ $point['icon'] }}" class="h-4 w-4"></i>
                        </span>
                        <span class="pt-1.5 text-white/90">{{ $point['text'] }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-8 border-t border-white/20 pt-5 text-sm text-white/90">
                <p>{{ __('account.panel_anonymous') }}</p>
                <a href="{{ route('home') }}#start" class="mt-2 inline-flex items-center gap-1 font-semibold text-white focus-visible:outline-white">
                    {{ __('account.start_anonymous') }}<i data-lucide="arrow-right" class="h-4 w-4"></i>
                </a>
            </div>
        </aside>

        <div class="p-6 sm:p-10">
            {{ $slot }}
        </div>
    </div>
</div>
