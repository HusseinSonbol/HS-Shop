<section id="hs-contact" class="hs-contact-section" aria-labelledby="hs-contact-heading">
    <div class="container hs-shell">
        <div class="row g-4 g-xl-5 hs-contact-grid" dir="ltr">
            <div class="col-lg-5" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
                <div class="hs-contact-visual">
                    <img src="{{ asset('emarket/image/catalog/demo/product/funiture/9.jpg') }}" alt="{{ __('store.contact.visual_alt') }}" loading="lazy" width="600" height="680">
                    <div class="hs-contact-visual-copy">
                        <span class="hs-eyebrow hs-eyebrow-light">{{ __('store.contact.eyebrow') }}</span>
                        <h2>{{ __('store.contact.visual_title') }}</h2>
                        <p>{{ __('store.contact.visual_note') }}</p>
                        <span class="hs-contact-visual-mark" aria-hidden="true">HS</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-7" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
                <div class="hs-contact-panel">
                    <div class="hs-contact-heading">
                        <span class="hs-eyebrow">{{ __('store.contact.eyebrow') }}</span>
                        <h2 id="hs-contact-heading">{{ __('store.contact.title') }}</h2>
                        <p>{{ __('store.contact.description') }}</p>
                    </div>

                    @if (session('contact_status'))
                        <div class="alert alert-success" role="status">{{ session('contact_status') }}</div>
                    @endif

                    <form method="POST" action="{{ route('store.contact-messages.store') }}" class="hs-contact-form" novalidate>
                        @csrf
                        <div class="hs-contact-honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="contact-sender-name">{{ __('store.contact.name') }}</label>
                                <input id="contact-sender-name" name="sender_name" value="{{ old('sender_name') }}" maxlength="160" autocomplete="name" class="form-control hs-contact-control @error('sender_name') is-invalid @enderror" required>
                                @error('sender_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="contact-email">{{ __('store.contact.email') }}</label>
                                <input id="contact-email" name="email" type="email" value="{{ old('email') }}" maxlength="190" autocomplete="email" dir="ltr" class="form-control hs-contact-control @error('email') is-invalid @enderror" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="contact-phone">{{ __('store.contact.phone') }}</label>
                                <input id="contact-phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="32" autocomplete="tel" dir="ltr" class="form-control hs-contact-control @error('phone') is-invalid @enderror">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="contact-subject">{{ __('store.contact.subject') }}</label>
                                <input id="contact-subject" name="subject" value="{{ old('subject') }}" maxlength="220" class="form-control hs-contact-control @error('subject') is-invalid @enderror">
                                @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="contact-message">{{ __('store.contact.message') }}</label>
                                <textarea id="contact-message" name="message" rows="5" maxlength="5000" class="form-control hs-contact-control hs-contact-textarea @error('message') is-invalid @enderror" required>{{ old('message') }}</textarea>
                                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            @error('website')<div class="col-12"><p class="text-danger small mb-0">{{ $message }}</p></div>@enderror
                            <div class="col-12 d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                <p class="hs-contact-privacy mb-0">{{ __('store.contact.privacy_note') }}</p>
                                <button class="btn hs-button hs-button-dark hs-contact-submit" type="submit">{{ __('store.contact.send') }} <span aria-hidden="true">↗</span></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
