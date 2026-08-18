@props([
    /** Override the eyebrow line above the headline. */
    'kicker' => 'Ready when you are',
    /** Override the headline (HTML — `<br>` and `<span class="italic …">` allowed). */
    'headline' => null,
    /** Subtitle paragraph. */
    'subtitle' => 'Live in 4 minutes. No credit card. Cancel anytime, keep your data.',
    /** Primary CTA href (defaults to register). */
    'primaryHref' => null,
    'primaryLabel' => 'Start 14-day trial',
    /** Secondary CTA href + label (defaults to contact). */
    'secondaryHref' => null,
    'secondaryLabel' => 'Book a demo',
])

@php
    $primaryHref = $primaryHref ?? (Route::has('register') ? route('register') : url('/'));
    $secondaryHref = $secondaryHref ?? '#';
    $kicker = fcp('cta-final.kicker', $kicker);
    $headline = fcp(
        'cta-final.headline',
        $headline ?? 'Send your first<br><span class="italic text-wa-green">240,000</span> messages.',
    );
    $subtitle = fcp('cta-final.subtitle', $subtitle);
    $primaryLabel = fcp('cta-final.primary_label', $primaryLabel);
    $secondaryLabel = fcp('cta-final.secondary_label', $secondaryLabel);
@endphp

<section class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-28" data-fc-section="cta-final">
    <div
        class="rounded-[36px] bg-gradient-to-br from-wa-deep via-wa-teal to-ink-950 text-paper-0 px-6 sm:px-10 lg:px-12 py-12 sm:py-16 lg:py-20 relative overflow-hidden reveal">
        <div class="absolute inset-0 dot-pattern opacity-20"></div>
        <div class="absolute -bottom-32 -right-32 w-[400px] h-[400px] rounded-full bg-wa-green/20 blur-bub"></div>

        <div class="relative grid grid-cols-1 lg:grid-cols-12 gap-8 items-end">
            <div class="col-span-12 lg:col-span-8">
                <div class="mono text-[10px] uppercase tracking-[0.22em] text-paper-0/60 mb-4"
                    data-fc="{{ fc_skey('cta-final.kicker') }}">{{ __($kicker) }}</div>
                <h2 class="serif text-[44px] sm:text-[64px] lg:text-[100px] leading-[0.92] tracking-[-0.02em]"
                    data-fc="{{ fc_skey('cta-final.headline') }}">
                    {!! $headline !!}
                </h2>
                <p class="text-[16px] text-paper-0/80 mt-6 max-w-xl" data-fc="{{ fc_skey('cta-final.subtitle') }}">
                    {{ __($subtitle) }}</p>

                <div class="mt-8 flex gap-3 flex-wrap">
                    <a href="{{ $primaryHref }}"
                        class="px-6 py-3.5 rounded-full bg-wa-green text-ink-900 text-[14px] font-semibold hover:bg-[#1ec05a] flex items-center gap-2 glow-green">
                        <span data-fc="{{ fc_skey('cta-final.primary_label') }}">{{ __($primaryLabel) }}</span>
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M5 4l4 4-4 4" />
                        </svg>
                    </a>
                    <a href="{{ $secondaryHref }}"
                        class="px-6 py-3.5 hairline border-paper-0/30 rounded-full text-[14px] font-medium hover:bg-paper-0/10"
                        data-fc="{{ fc_skey('cta-final.secondary_label') }}">{{ __($secondaryLabel) }}</a>
                </div>
            </div>

            {{-- Recent signups social proof --}}
            <div class="col-span-12 lg:col-span-4">
                <div class="hairline border-paper-0/15 rounded-2xl bg-paper-0/5 p-5 backdrop-blur">
                    <div class="mono text-[10px] uppercase tracking-widest text-paper-0/50"
                        data-fc="cta-final.proof_label">{{ fc('cta-final.proof_label', __('Joined this week')) }}</div>
                    <div class="mt-3 space-y-2.5">
                        <div class="flex items-center gap-2.5">
                            <span
                                class="w-7 h-7 rounded-full bg-gradient-to-br from-accent-coral to-accent-amber text-paper-0 text-[10px] font-semibold flex items-center justify-center"
                                data-fc="cta-final.proof1_initials">{{ fc('cta-final.proof1_initials', 'RM') }}</span>
                            <div class="flex-1 leading-tight">
                                <div class="text-[12px] font-medium" data-fc="cta-final.proof1_name">
                                    {{ fc('cta-final.proof1_name', 'Ridgewell Mortgages') }}</div>
                                <div class="mono text-[10px] text-paper-0/50" data-fc="cta-final.proof1_meta">
                                    {{ fc('cta-final.proof1_meta', 'Berlin · upgraded to Scale') }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span
                                class="w-7 h-7 rounded-full bg-gradient-to-br from-wa-teal to-wa-deep text-paper-0 text-[10px] font-semibold flex items-center justify-center"
                                data-fc="cta-final.proof2_initials">{{ fc('cta-final.proof2_initials', 'MC') }}</span>
                            <div class="flex-1 leading-tight">
                                <div class="text-[12px] font-medium" data-fc="cta-final.proof2_name">
                                    {!! fc('cta-final.proof2_name', 'Maison &amp; Co.') !!}</div>
                                <div class="mono text-[10px] text-paper-0/50" data-fc="cta-final.proof2_meta">
                                    {{ fc('cta-final.proof2_meta', 'Paris · migrated from Wati') }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span
                                class="w-7 h-7 rounded-full bg-gradient-to-br from-accent-amber to-wa-green text-ink-900 text-[10px] font-semibold flex items-center justify-center"
                                data-fc="cta-final.proof3_initials">{{ fc('cta-final.proof3_initials', 'FO') }}</span>
                            <div class="flex-1 leading-tight">
                                <div class="text-[12px] font-medium" data-fc="cta-final.proof3_name">
                                    {{ fc('cta-final.proof3_name', 'Formas Studio') }}</div>
                                <div class="mono text-[10px] text-paper-0/50" data-fc="cta-final.proof3_meta">
                                    {{ fc('cta-final.proof3_meta', 'São Paulo · 12 seats') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
