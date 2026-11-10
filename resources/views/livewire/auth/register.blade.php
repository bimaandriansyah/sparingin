<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6" x-data="ajaxForm" x-on:submit.prevent="submit" x-on:pageshow.window="submitting = false"
              data-redirect="{{ route('dashboard') }}" data-two-factor="{{ route('two-factor.login') }}" novalidate>
            <x-ajax-error field="form" class="mt-0! rounded-xl bg-red-50 px-3 py-2" />
            @csrf
            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />
            <x-ajax-error field="name" />

            <!-- Phone -->
            <flux:input
                name="phone"
                label="Nomor HP"
                :value="old('phone_display', old('phone'))"
                type="tel"
                inputmode="tel"
                required
                autocomplete="tel"
                placeholder="08123456789"
            />
            <x-ajax-error field="phone" />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
            <x-ajax-error field="password" />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
            <x-ajax-error field="password_confirmation" />

            <div class="flex items-center justify-end">
                <x-submit-button data-test="register-user-button" loading="Membuat akun…">{{ __('Create account') }}</x-submit-button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
