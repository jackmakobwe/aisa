{{-- Outside production, explains where emails go when they are only written to the log. --}}
@if (! app()->isProduction() && in_array(config('mail.default'), ['log', 'array'], true))
    <p {{ $attributes->class('alert-warning text-sm') }}>
        <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0 text-amber"></i>
        <span>{{ __('account.dev_mail_log') }}</span>
    </p>
@endif
