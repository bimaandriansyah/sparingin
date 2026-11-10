<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" description="Masukkan nomor HP dan kata sandi untuk masuk" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6" x-data="ajaxForm" x-on:submit.prevent="submit" x-on:pageshow.window="submitting = false"
              data-redirect="{{ route('dashboard') }}" data-two-factor="{{ route('two-factor.login') }}" novalidate>
            <x-ajax-error field="form" class="mt-0! rounded-xl bg-red-50 px-3 py-2" />
            @csrf

            <!-- Phone -->
            <flux:input
                name="phone"
                label="Nomor HP"
                :value="old('phone_display', old('phone'))"
                type="tel"
                inputmode="tel"
                required
                autofocus
                autocomplete="tel"
                placeholder="08123456789"
            />
            <x-ajax-error field="phone" />

            <!-- Password -->
            <div>
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />
            </div>
            <x-ajax-error field="password" />

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <x-submit-button data-test="login-button" loading="Masuk…">{{ __('Log in') }}</x-submit-button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
