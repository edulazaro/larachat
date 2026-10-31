<div class="lc-card">
    <h1>{{ __('larachat::panel.profile') }}</h1>

    <form wire:submit="saveProfile">
        @if (session('profile_saved')) <div class="lc-ok">{{ session('profile_saved') }}</div> @endif
        <div class="lc-field">
            <label class="lc-label" for="name">{{ __('larachat::panel.name') }}</label>
            <input id="name" type="text" class="lc-input" wire:model="name">
            @error('name') <div class="lc-error">{{ $message }}</div> @enderror
        </div>
        <div class="lc-field">
            <label class="lc-label" for="email">{{ __('larachat::panel.email') }}</label>
            <input id="email" type="email" class="lc-input" wire:model="email">
            @error('email') <div class="lc-error">{{ $message }}</div> @enderror
        </div>
        <div class="lc-field">
            <label class="lc-label" for="email_password">{{ __('larachat::panel.email_password') }}</label>
            <input id="email_password" type="password" class="lc-input" wire:model="email_password" autocomplete="current-password">
            @error('email_password') <div class="lc-error">{{ $message }}</div> @enderror
        </div>
        <button type="submit" class="lc-btn lc-btn--primary">{{ __('larachat::panel.save') }}</button>
    </form>

    <h2>{{ __('larachat::panel.change_password') }}</h2>
    <form wire:submit="savePassword">
        @if (session('password_saved')) <div class="lc-ok">{{ session('password_saved') }}</div> @endif
        <div class="lc-field">
            <label class="lc-label" for="current_password">{{ __('larachat::panel.current_password') }}</label>
            <input id="current_password" type="password" class="lc-input" wire:model="current_password" autocomplete="current-password">
            @error('current_password') <div class="lc-error">{{ $message }}</div> @enderror
        </div>
        <div class="lc-field">
            <label class="lc-label" for="password">{{ __('larachat::panel.new_password') }}</label>
            <input id="password" type="password" class="lc-input" wire:model="password" autocomplete="new-password">
            @error('password') <div class="lc-error">{{ $message }}</div> @enderror
        </div>
        <div class="lc-field">
            <label class="lc-label" for="password_confirmation">{{ __('larachat::panel.repeat_password') }}</label>
            <input id="password_confirmation" type="password" class="lc-input" wire:model="password_confirmation" autocomplete="new-password">
        </div>
        <button type="submit" class="lc-btn lc-btn--primary">{{ __('larachat::panel.change_password') }}</button>
    </form>
</div>
