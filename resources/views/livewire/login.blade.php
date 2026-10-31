<div class="lc-card">
    <h1>{{ config('larachat.panel.name') }} {{ __('larachat::panel.admin') }}</h1>
    <form wire:submit="login">
        <div class="lc-field">
            <label class="lc-label" for="email">{{ __('larachat::panel.email') }}</label>
            <input id="email" type="email" class="lc-input" wire:model="email" autocomplete="username" autofocus>
            @error('email') <div class="lc-error">{{ $message }}</div> @enderror
        </div>
        <div class="lc-field">
            <label class="lc-label" for="password">{{ __('larachat::panel.password') }}</label>
            <input id="password" type="password" class="lc-input" wire:model="password" autocomplete="current-password">
            @error('password') <div class="lc-error">{{ $message }}</div> @enderror
        </div>
        <label class="lc-check">
            <input type="checkbox" wire:model="remember"> {{ __('larachat::panel.remember') }}
        </label>
        <button type="submit" class="lc-btn lc-btn--primary">{{ __('larachat::panel.login') }}</button>
    </form>
</div>
