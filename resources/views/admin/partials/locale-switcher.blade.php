<form method="POST" action="{{ route('locale.update', app()->getLocale() === 'ar' ? 'en' : 'ar') }}">
    @csrf
    <button class="btn btn-outline-secondary locale-switch" type="submit" aria-label="{{ __('admin.locale.switch_to') }}">
        {{ __('admin.locale.switch_to') }}
    </button>
</form>
