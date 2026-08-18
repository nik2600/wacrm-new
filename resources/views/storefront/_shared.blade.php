@php
    // Shared theme helpers + JS. Every theme expects these template
    // variables: $sf, $workspace, $products|$product, $settings, $waNumber,
    // $shopName, $brand. Centralised here so themes only need palette +
    // optional layout tweaks.
@endphp

<script>
    window.STOREFRONT = window.STOREFRONT || (() => {
        // ─────────────────────────────────────────────────────────────────
        // Persistence keys — workspace-scoped via storefront id so two shops
        // sharing a browser don't trample each other's cart/wishlist state.
        // ─────────────────────────────────────────────────────────────────
        const SF_ID = {{ $sf->id }};
        const CART_KEY = 'sf_cart_' + SF_ID;
        const WISH_KEY = 'sf_wish_' + SF_ID;
        const COMPARE_KEY = 'sf_compare_' + SF_ID;
        const RECENT_KEY = 'sf_recent_' + SF_ID;
        const MAX_COMPARE = 4;
        const RECENT_LIMIT = 8;

        const load = (k) => {
            try {
                return JSON.parse(localStorage.getItem(k) || '[]');
            } catch (_) {
                return [];
            }
        };
        const save = (k, v) => localStorage.setItem(k, JSON.stringify(v));

        // Currency symbols for everything the admin product form supports.
        // 'fallback' renders the 3-letter code instead of a symbol.
        const CURRENCY = {
            INR: '₹',
            USD: '$',
            EUR: '€',
            GBP: '£',
            AED: 'د.إ',
            KES: 'KSh',
            NGN: '₦',
            CRC: '₡',
            BRL: 'R$',
            ZAR: 'R',
            PHP: '₱',
            IDR: 'Rp',
            MXN: '$',
            SGD: 'S$',
            MYR: 'RM',
            THB: '฿',
            VND: '₫',
            EGP: 'E£',
            PKR: '₨',
            BDT: '৳',
            LKR: 'Rs',
        };
        const SHOP_CURRENCY = (window.SF_SHOP && window.SF_SHOP.currency) || @json($sf->currency_code ?? 'INR');
        const SHIPPING = (window.SF_SHOP && window.SF_SHOP.shipping) || null;
        const PAYMENT = (window.SF_SHOP && window.SF_SHOP.payment) || {
            provider: null,
            handle: null
        };
        const fmt = (cents, code) => {
            const c = code || SHOP_CURRENCY;
            const sym = CURRENCY[c] || (c + ' ');
            const v = cents / 100;
            return sym + ' ' + v.toFixed((cents % 100) === 0 ? 0 : 2);
        };
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        } [c]));

        // Image with onerror fallback so a 404 doesn't show the browser's
        // broken-image icon — pain point #7/#17 from the WATI reviews.
        const safeImg = (src, cls) => src ?
            `<img src="${esc(src)}" class="${cls || ''}" onerror="this.outerHTML='<span class=&quot;sf-img-fallback&quot;>📦</span>'">` :
            '<span class="sf-img-fallback">📦</span>';

        const api = {
            cart: load(CART_KEY),
            wish: load(WISH_KEY),
            compare: load(COMPARE_KEY),
            recent: load(RECENT_KEY),
            waNumber: @json($waNumber ?? ''),
            storeName: @json($shopName ?? ($workspace?->name ?? 'Store')),
            {{-- Host-agnostic (stays on custom domain) but carries the app's
                 mount path so a sub-folder deploy like /public/s/... resolves. --}}
            @php $sfBase = request()->getBaseUrl() . '/s/' . ($sf->slug ?? ''); @endphp
            checkoutUrl: @json($sfBase . '/checkout'),
            couponUrl: @json($sfBase . '/coupon'),
            reviewUrl: @json($sfBase . '/review'),
            abandonUrl: @json($sfBase . '/abandon'),
            couponCode: null,
            _abandonSent: false,
            catalog: window.SF_CATALOG || {}, // {id: {name, price, image, slug, sku, description}}

            // ───────── Cart (with stock enforcement) ─────────
            // Stock guard: every mutator clamps the requested qty to the
            // product's stock_qty (when set). Pain point #5 / WATI complaint
            // — buyers could previously add 999 of a 1-stock item.
            stockCap(id) {
                const p = this.catalog[id] || {};
                if (p.stock_qty === null || p.stock_qty === undefined) return Infinity;
                return Math.max(0, parseInt(p.stock_qty, 10));
            },
            add(id, name, price, image) {
                const cap = this.stockCap(id);
                const existing = this.cart.find(i => i.id === id);
                const currentQty = existing ? existing.qty : 0;
                if (currentQty + 1 > cap) {
                    return this.modal({
                        title: 'Only ' + cap + ' in stock',
                        body: cap === 0 ?
                            'This product is sold out. We\'ll restock soon — message us on WhatsApp to be notified.' :
                            'There ' + (cap === 1 ? 'is only 1 unit' : 'are only ' + cap +
                            ' units') +
                            ' available, and you already have all of them in your cart.',
                        confirm: 'OK',
                    });
                }
                if (existing) existing.qty += 1;
                else this.cart.push({
                    id,
                    name,
                    price,
                    image,
                    qty: 1
                });
                save(CART_KEY, this.cart);
                this.bumpBadge('cart');
                this.renderAll();
                this.flash(name + ' · added to cart');
            },
            addQty(id, qty) {
                const cap = this.stockCap(id);
                const item = this.cart.find(i => i.id === id);
                const currentQty = item ? item.qty : 0;
                const newQty = currentQty + qty;
                if (qty > 0 && newQty > cap) {
                    this.flash('Only ' + cap + ' in stock');
                    // Clamp to stock instead of refusing entirely — friendlier UX.
                    if (item) item.qty = cap;
                    else if (cap > 0) {
                        const p = this.catalog[id] || {};
                        this.cart.push({
                            id,
                            name: p.name,
                            price: p.price,
                            image: p.image,
                            qty: cap
                        });
                    }
                } else if (item) {
                    item.qty = Math.max(1, newQty);
                } else if (qty > 0) {
                    const p = this.catalog[id] || {};
                    this.cart.push({
                        id,
                        name: p.name,
                        price: p.price,
                        image: p.image,
                        qty
                    });
                }
                save(CART_KEY, this.cart);
                this.bumpBadge('cart');
                this.renderAll();
            },
            setQty(id, qty) {
                const cap = this.stockCap(id);
                const item = this.cart.find(i => i.id === id);
                if (!item) return;
                const wanted = Math.max(1, parseInt(qty || 1, 10));
                if (wanted > cap) {
                    this.flash('Only ' + cap + ' in stock');
                    item.qty = cap;
                } else {
                    item.qty = wanted;
                }
                save(CART_KEY, this.cart);
                this.renderAll();
            },
            remove(id) {
                this.cart = this.cart.filter(i => i.id !== id);
                save(CART_KEY, this.cart);
                this.renderAll();
            },
            cartCount() {
                return this.cart.reduce((s, i) => s + i.qty, 0);
            },
            cartSubtotal() {
                return this.cart.reduce((s, i) => s + i.price * i.qty, 0);
            },
            shippingFee() {
                if (!SHIPPING) return 0;
                const sub = this.cartSubtotal();
                const free = parseInt(SHIPPING.free_above_minor || 0, 10);
                if (free > 0 && sub >= free) return 0;
                return parseInt(SHIPPING.flat_minor || 0, 10);
            },
            cartTotal() {
                return this.cartSubtotal() + this.shippingFee();
            },
            paymentLink() {
                const p = PAYMENT || {};
                const total = this.cartTotal();
                if (!p.handle) return null;
                switch (p.provider) {
                    case 'upi':
                        // upi:// deep link — works on Android out of the box.
                        return 'upi://pay?pa=' + encodeURIComponent(p.handle) +
                            '&pn=' + encodeURIComponent(this.storeName) +
                            '&am=' + (total / 100).toFixed(2) +
                            '&cu=INR';
                    case 'paypal_me':
                        return 'https://paypal.me/' + encodeURIComponent(p.handle.replace(/^@/, '')) + '/' +
                            (total / 100).toFixed(2);
                    case 'razorpay_link':
                    case 'stripe_link':
                        return p.handle; // already a full URL
                    case 'bank_transfer':
                        return null; // surfaced as text in the order message, not a link
                    default:
                        return null;
                }
            },
            paymentInstructions() {
                const p = PAYMENT || {};
                if (!p.handle || !p.provider) return null;
                const labels = {
                    upi: 'UPI',
                    razorpay_link: 'Razorpay',
                    stripe_link: 'Stripe',
                    paypal_me: 'PayPal',
                    bank_transfer: 'Bank transfer'
                };
                return labels[p.provider] + ': ' + p.handle;
            },

            // ───────── Wishlist ─────────
            toggleWish(id) {
                const i = this.wish.indexOf(id);
                if (i >= 0) {
                    this.wish.splice(i, 1);
                    this.flash('Removed from wishlist');
                } else {
                    this.wish.push(id);
                    this.bumpBadge('wish');
                    this.flash('Added to wishlist ♡');
                }
                save(WISH_KEY, this.wish);
                this.renderAll();
            },
            isWished(id) {
                return this.wish.includes(id);
            },
            wishCount() {
                return this.wish.length;
            },

            // ───────── Compare ─────────
            toggleCompare(id) {
                const i = this.compare.indexOf(id);
                if (i >= 0) {
                    this.compare.splice(i, 1);
                    this.flash('Removed from compare');
                } else if (this.compare.length >= MAX_COMPARE) {
                    return this.modal({
                        title: 'Compare full',
                        body: 'You can compare up to ' + MAX_COMPARE +
                            ' products at once. Remove one from the compare bar to add a new one.',
                        confirm: 'OK',
                    });
                } else {
                    this.compare.push(id);
                    this.bumpBadge('compare');
                    this.flash('Added to compare');
                }
                save(COMPARE_KEY, this.compare);
                this.renderAll();
            },
            isCompared(id) {
                return this.compare.includes(id);
            },
            compareCount() {
                return this.compare.length;
            },

            // ───────── Recently viewed ─────────
            pushRecent(id) {
                this.recent = [id, ...this.recent.filter(x => x !== id)].slice(0, RECENT_LIMIT);
                save(RECENT_KEY, this.recent);
            },

            // ───────── UI ─────────
            bumpBadge(kind) {
                document.querySelectorAll('[data-' + kind + '-count]').forEach(el => {
                    el.classList.remove('bump');
                    // restart the animation by forcing reflow
                    // eslint-disable-next-line no-unused-expressions
                    void el.offsetWidth;
                    el.classList.add('bump');
                });
            },
            renderBadges() {
                const set = (k, n) => document.querySelectorAll('[data-' + k + '-count]').forEach(el => {
                    el.textContent = n;
                    el.classList.toggle('hidden-count', n === 0);
                });
                set('cart', this.cartCount());
                set('wish', this.wishCount());
                set('compare', this.compareCount());
            },
            renderCardWishlistStates() {
                document.querySelectorAll('[data-wish-toggle]').forEach(btn => {
                    const id = parseInt(btn.dataset.wishToggle, 10);
                    btn.classList.toggle('is-on', this.isWished(id));
                    btn.setAttribute('aria-label', this.isWished(id) ? 'Remove from wishlist' :
                        'Add to wishlist');
                });
                document.querySelectorAll('[data-compare-toggle]').forEach(btn => {
                    const id = parseInt(btn.dataset.compareToggle, 10);
                    btn.classList.toggle('is-on', this.isCompared(id));
                });
            },
            renderCartDrawer() {
                const list = document.querySelector('[data-cart-items]');
                const totalEl = document.querySelector('[data-cart-total]');
                const subtotalEl = document.querySelector('[data-cart-subtotal]');
                if (!list) return;
                if (this.cart.length === 0) {
                    list.innerHTML = `
 <div class="sf-empty">
 <svg viewBox="0 0 64 64" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.2" opacity=".4">
 <path d="M14 18h36l-3 28a4 4 0 0 1-4 3.5H21a4 4 0 0 1-4-3.5L14 18Z"/>
 <path d="M22 18v-4a10 10 0 0 1 20 0v4"/>
 </svg>
 <div class="sf-empty-title">Your cart is empty</div>
 <div class="sf-empty-body">Browse the catalog and add a few favourites.</div>
 <button class="sf-empty-cta" onclick="STOREFRONT.toggleDrawer('cart')">Keep browsing</button>
 </div>`;
                } else {
                    list.innerHTML = this.cart.map(i => `
 <div class="sf-cart-row">
 <div class="sf-cart-img">${ i.image ? `<img src="${esc(i.image)}">` : `<span>📦</span>` }</div>
 <div class="sf-cart-meta">
 <div class="sf-cart-name">${esc(i.name)}</div>
 <div class="sf-cart-price">${fmt(i.price)} × ${i.qty}</div>
 <div class="sf-qty">
 <button onclick="STOREFRONT.addQty(${i.id},-1)" aria-label="Decrease">−</button>
 <input type="number" min="1" value="${i.qty}" onchange="STOREFRONT.setQty(${i.id}, this.value)">
 <button onclick="STOREFRONT.addQty(${i.id},1)" aria-label="Increase">+</button>
 </div>
 </div>
 <button class="sf-cart-remove" onclick="STOREFRONT.remove(${i.id})" aria-label="Remove">×</button>
 </div>`).join('');
                }
                if (subtotalEl) subtotalEl.textContent = fmt(this.cartSubtotal());
                if (totalEl) totalEl.textContent = fmt(this.cartTotal());

                // Render shipping line (only when configured)
                const shipEl = document.querySelector('[data-cart-shipping]');
                if (shipEl) {
                    const fee = this.shippingFee();
                    const sub = this.cartSubtotal();
                    const free = parseInt(SHIPPING?.free_above_minor || 0, 10);
                    if (!SHIPPING || !SHIPPING.flat_minor) {
                        shipEl.innerHTML = '';
                    } else if (fee === 0 && free > 0 && sub >= free) {
                        shipEl.innerHTML =
                            '<div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;color:#075E54"><span>Shipping</span><span style="font-family:JetBrains Mono,monospace">FREE ✓</span></div>';
                    } else if (fee > 0) {
                        const remaining = free > 0 && sub < free ? (free - sub) : 0;
                        shipEl.innerHTML =
                            `<div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;color:#6B807C"><span>Shipping</span><span style="font-family:JetBrains Mono,monospace;color:#0B1F1C">${fmt(fee)}</span></div>${ remaining > 0 ? `<div style="font-size:11px;color:#6B807C;margin-bottom:6px;font-family:JetBrains Mono,monospace">Add ${fmt(remaining)} more for free shipping</div>` : '' }`;
                    } else {
                        shipEl.innerHTML = '';
                    }
                }
            },
            renderWishDrawer() {
                const list = document.querySelector('[data-wish-items]');
                if (!list) return;
                if (this.wish.length === 0) {
                    list.innerHTML = `
 <div class="sf-empty">
 <svg viewBox="0 0 64 64" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.2" opacity=".4">
 <path d="M32 50s-16-10-16-22a8 8 0 0 1 16-3 8 8 0 0 1 16 3c0 12-16 22-16 22Z"/>
 </svg>
 <div class="sf-empty-title">No favourites yet</div>
 <div class="sf-empty-body">Tap the heart on any product to save it for later.</div>
 </div>`;
                    return;
                }
                list.innerHTML = this.wish.map(id => {
                    const p = this.catalog[id];
                    if (!p) return '';
                    return `
 <div class="sf-cart-row">
 <a class="sf-cart-img" href="${esc(p.url)}">${ p.image ? `<img src="${esc(p.image)}">` : `<span>📦</span>` }</a>
 <div class="sf-cart-meta">
 <a class="sf-cart-name" href="${esc(p.url)}">${esc(p.name)}</a>
 <div class="sf-cart-price">${fmt(p.price)}</div>
 <button class="sf-mini-btn" onclick="STOREFRONT.add(${id}, ${JSON.stringify(p.name).replace(/"/g,'&quot;')}, ${p.price}, ${JSON.stringify(p.image || '').replace(/"/g,'&quot;')})">Add to cart</button>
 </div>
 <button class="sf-cart-remove" onclick="STOREFRONT.toggleWish(${id})" aria-label="Remove">×</button>
 </div>`;
                }).join('');
            },
            renderCompareBar() {
                const bar = document.getElementById('sf-compare-bar');
                if (!bar) return;
                if (this.compare.length === 0) {
                    bar.style.display = 'none';
                    return;
                }
                bar.style.display = 'flex';
                const inner = bar.querySelector('[data-compare-list]');
                inner.innerHTML = this.compare.map(id => {
                    const p = this.catalog[id];
                    if (!p) return '';
                    return `
 <div class="sf-cmp-chip">
 <div class="sf-cmp-thumb">${ p.image ? `<img src="${esc(p.image)}">` : '📦' }</div>
 <div class="sf-cmp-name">${esc(p.name)}</div>
 <button class="sf-cmp-x" onclick="STOREFRONT.toggleCompare(${id})" aria-label="Remove">×</button>
 </div>`;
                }).join('');
            },
            renderCompareDrawer() {
                const tbl = document.querySelector('[data-compare-table]');
                if (!tbl) return;
                if (this.compare.length === 0) {
                    tbl.innerHTML = `
 <div class="sf-empty">
 <svg viewBox="0 0 64 64" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.2" opacity=".4">
 <rect x="6" y="14" width="22" height="36" rx="3"/><rect x="36" y="14" width="22" height="36" rx="3"/>
 </svg>
 <div class="sf-empty-title">Nothing to compare</div>
 <div class="sf-empty-body">Tap “Compare” on any two products to see them side by side.</div>
 </div>`;
                    return;
                }
                const cols = this.compare.map(id => this.catalog[id]).filter(Boolean);
                const fields = [
                    ['Image', p => p.image ?
                        `<img src="${esc(p.image)}" style="width:100%;border-radius:8px">` : '📦'
                    ],
                    ['Name', p => `<a href="${esc(p.url)}" style="font-weight:600">${esc(p.name)}</a>`],
                    ['Price', p => fmt(p.price)],
                    ['SKU', p => esc(p.sku || '—')],
                    ['Category', p => esc(p.category || '—')],
                    ['Description', p => esc(p.description || '—')],
                ];
                tbl.innerHTML = `
 <table class="sf-compare-table">
 <thead><tr><th></th>${cols.map(c => `<th><button class="sf-cmp-x" onclick="STOREFRONT.toggleCompare(${c.id})">×</button></th>`).join('')}</tr></thead>
 <tbody>${fields.map(([label, fn]) => `<tr><th>${label}</th>${cols.map(c => `<td>${fn(c)}</td>`).join('')}</tr>`).join('')}</tbody>
 </table>`;
            },
            renderAll() {
                this.renderBadges();
                this.renderCardWishlistStates();
                this.renderCartDrawer();
                this.renderWishDrawer();
                this.renderCompareBar();
                this.renderCompareDrawer();
            },

            // ───────── Drawers / modals ─────────
            toggleDrawer(kind) {
                ['cart', 'wish', 'compare'].forEach(k => {
                    const d = document.getElementById('sf-' + k + '-drawer');
                    if (!d) return;
                    if (k === kind) {
                        const open = d.dataset.open === '1';
                        d.dataset.open = open ? '0' : '1';
                    } else {
                        d.dataset.open = '0';
                    }
                });
                document.body.classList.toggle('sf-drawer-open', !!document.querySelector(
                    '[data-open="1"]'));
            },
            share(url, title) {
                // Public-storefront share modal — buyers tap this from the
                // product page or shop header. Same option set as the admin-
                // side share modal so users see a consistent experience.
                const fullUrl = url || window.location.href;
                const name = title || document.title;
                const text = `Check this out: ${name}\n${fullUrl}`;
                const waHref = 'https://wa.me/?text=' + encodeURIComponent(text);
                const tgHref = 'https://t.me/share/url?url=' + encodeURIComponent(fullUrl) + '&text=' +
                    encodeURIComponent(name);
                const mailto = 'mailto:?subject=' + encodeURIComponent(name) + '&body=' +
                    encodeURIComponent(text);
                const qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&margin=8&data=' +
                    encodeURIComponent(fullUrl);

                this.modal({
                    title: 'Share',
                    bodyHtml: `
 <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
 <input value="${esc(fullUrl)}" readonly style="flex:1;padding:8px 12px;border:1px solid #E5DFD0;border-radius:9px;font-family:'JetBrains Mono',monospace;font-size:11.5px;background:#FBFAF6;color:#0B1F1C" onclick="this.select()">
 <button class="sf-cta" style="padding:8px 14px;font-size:11.5px" onclick="navigator.clipboard.writeText(${JSON.stringify(fullUrl)}); this.textContent='Copied ✓'; setTimeout(()=>this.textContent='Copy',1500)">Copy</button>
 </div>
 <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
 <a href="${waHref}" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #E5DFD0;border-radius:11px;text-decoration:none;color:inherit">
 <span style="width:34px;height:34px;border-radius:50%;background:#D5F0EA;display:grid;place-items:center"><svg viewBox="0 0 24 24" width="16" height="16" fill="#075E54"><path d="M17.5 14.4c-.3-.1-1.5-.7-1.8-.8-.2-.1-.4-.1-.5.1-.2.3-.7.8-.8 1-.1.1-.3.2-.6.1-.3-.1-1.2-.4-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.4.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5-.1-.1-.5-1.3-.7-1.8-.2-.5-.4-.4-.5-.4h-.5c-.2 0-.4.1-.7.3-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3 4.8 4.2 1.7.7 2.3.8 3.1.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2 0-.1-.2-.2-.5-.3zM12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.5A10 10 0 1 0 12 2z"/></svg></span>
 <div><div style="font-weight:600;font-size:12.5px">WhatsApp</div><div style="font-size:11px;color:#6B807C">Send to a chat</div></div>
 </a>
 <a href="${mailto}" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #E5DFD0;border-radius:11px;text-decoration:none;color:inherit">
 <span style="width:34px;height:34px;border-radius:50%;background:#F5F2EA;display:grid;place-items:center"><svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="#0B1F1C" stroke-width="1.6"><rect x="2" y="3" width="12" height="10" rx="1"/><path d="m2 4 6 5 6-5"/></svg></span>
 <div><div style="font-weight:600;font-size:12.5px">Email</div><div style="font-size:11px;color:#6B807C">Open mail app</div></div>
 </a>
 <a href="${tgHref}" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #E5DFD0;border-radius:11px;text-decoration:none;color:inherit">
 <span style="width:34px;height:34px;border-radius:50%;background:#E7F4FB;display:grid;place-items:center"><svg viewBox="0 0 24 24" width="14" height="14" fill="#229ED9"><path d="M22 4 2 11.5l5.5 2 2 6.5 3-3.5 5.5 4Z"/></svg></span>
 <div><div style="font-weight:600;font-size:12.5px">Telegram</div><div style="font-size:11px;color:#6B807C">Share to chat</div></div>
 </a>
 <button onclick="STOREFRONT._nativeShare(${JSON.stringify(title || '')}, ${JSON.stringify(text)}, ${JSON.stringify(fullUrl)})" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid #E5DFD0;border-radius:11px;background:#fff;cursor:pointer;text-align:left">
 <span style="width:34px;height:34px;border-radius:50%;background:#F5F2EA;display:grid;place-items:center"><svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="#0B1F1C" stroke-width="1.6"><path d="M8 2v8M5 5l3-3 3 3M3 10v3a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1v-3"/></svg></span>
 <div><div style="font-weight:600;font-size:12.5px">More…</div><div style="font-size:11px;color:#6B807C">Phone share menu</div></div>
 </button>
 </div>
 <details style="margin-top:14px">
 <summary style="cursor:pointer;font-size:12px;color:#075E54;font-weight:600;list-style:none">Show QR code →</summary>
 <div style="margin-top:10px;display:flex;flex-direction:column;align-items:center;padding:14px;background:#FBFAF6;border-radius:12px">
 <img src="${qrSrc}" alt="QR" style="width:180px;height:180px;background:#fff;border-radius:8px">
 <div style="font-size:11px;color:#6B807C;margin-top:8px">Scan to open the shop</div>
 </div>
 </details>
 `,
                });
            },
            async _nativeShare(title, text, url) {
                if (navigator.share) {
                    try {
                        await navigator.share({
                            title,
                            text,
                            url
                        });
                    } catch (_) {}
                } else {
                    navigator.clipboard.writeText(url);
                    this.flash('Link copied');
                }
            },
            openQuickView(id) {
                const p = this.catalog[id];
                if (!p) return;
                this.modal({
                    title: p.name,
                    bodyHtml: `
 <div class="sf-qv">
 <div class="sf-qv-img">${ p.image ? `<img src="${esc(p.image)}">` : '<span>📦</span>' }</div>
 <div class="sf-qv-meta">
 <div class="sf-qv-price">
 <span class="sf-qv-now">${fmt(p.price)}</span>
 ${ p.compare && p.compare > p.price ? `<span class="sf-qv-was">${fmt(p.compare)}</span>` : '' }
 </div>
 ${ p.description ? `<p class="sf-qv-desc">${esc(p.description)}</p>` : '' }
 <div class="sf-qv-actions">
 <button class="sf-cta" onclick="STOREFRONT.add(${id}, ${JSON.stringify(p.name).replace(/"/g,'&quot;')}, ${p.price}, ${JSON.stringify(p.image || '').replace(/"/g,'&quot;')}); STOREFRONT.closeModal();">Add to cart</button>
 <a class="sf-cta-ghost" href="${esc(p.url)}">Full details →</a>
 </div>
 </div>
 </div>`,
                });
            },
            modal(opts) {
                const wrap = document.getElementById('sf-modal');
                if (!wrap) return Promise.resolve(false);
                const card = wrap.querySelector('[data-modal-card]');
                card.innerHTML = `
 <button class="sf-modal-close" onclick="STOREFRONT.closeModal()" aria-label="Close">×</button>
 ${ opts.title ? `<div class="sf-modal-title">${esc(opts.title)}</div>` : '' }
 ${ opts.body ? `<div class="sf-modal-body">${esc(opts.body)}</div>` : (opts.bodyHtml || '') }
 ${ (opts.confirm || opts.cancel) ? `<div class="sf-modal-actions">
 ${ opts.cancel ? `<button data-modal-cancel class="sf-cta-ghost">${esc(opts.cancel)}</button>` : '' }
 ${ opts.confirm ? `<button data-modal-confirm class="sf-cta">${esc(opts.confirm)}</button>` : '' }
 </div>` : '' }`;
                wrap.dataset.open = '1';
                return new Promise((resolve) => {
                    const close = (val) => {
                        this.closeModal();
                        resolve(val);
                    };
                    card.querySelector('[data-modal-cancel]')?.addEventListener('click', () =>
                        close(false));
                    card.querySelector('[data-modal-confirm]')?.addEventListener('click', () =>
                        close(true));
                    wrap.querySelector('[data-modal-backdrop]').addEventListener('click', () =>
                        close(false), {
                            once: true
                        });
                });
            },
            closeModal() {
                const wrap = document.getElementById('sf-modal');
                if (wrap) wrap.dataset.open = '0';
            },

            flash(msg) {
                let t = document.getElementById('sf-toast');
                if (!t) {
                    t = document.createElement('div');
                    t.id = 'sf-toast';
                    t.className = 'sf-toast';
                    document.body.appendChild(t);
                }
                t.textContent = msg;
                t.classList.add('show');
                clearTimeout(t._t);
                t._t = setTimeout(() => t.classList.remove('show'), 1800);
            },

            buildOrderText() {
                // Build a message the seller can act on with no extra
                // back-and-forth: each line has the name, qty, line total,
                // image URL (so they know which product) and short description.
                // Pain point #1 from WATI reviews was orders arriving with only
                // name + price, which doesn't tell the seller what they sold.
                const lines = ['*New order from ' + this.storeName + '*\n'];
                this.cart.forEach((i, idx) => {
                    const p = this.catalog[i.id] || {};
                    lines.push(`${idx + 1}. *${i.name}* × ${i.qty} — ${fmt(i.price * i.qty)}`);
                    if (p.description) lines.push(
                        ` ${p.description.slice(0, 140)}${p.description.length > 140 ? '…' : ''}`
                        );
                    if (p.url) lines.push(` ${p.url}`);
                    if (i.image) lines.push(` ${i.image}`);
                    lines.push('');
                });
                lines.push('─────────────');
                lines.push('Subtotal: ' + fmt(this.cartSubtotal()));
                const fee = this.shippingFee();
                if (SHIPPING && SHIPPING.flat_minor) {
                    lines.push('Shipping: ' + (fee === 0 ? 'FREE ✓' : fmt(fee)));
                }
                lines.push('*TOTAL: ' + fmt(this.cartTotal()) + '*');
                lines.push('');

                // Payment instructions / link (when shop has configured one)
                const link = this.paymentLink();
                const instr = this.paymentInstructions();
                if (link || instr) {
                    lines.push('💳 *Payment*');
                    if (instr) lines.push(instr);
                    if (link) lines.push(link);
                    lines.push('');
                }

                lines.push('_Please confirm and let me know about shipping._');
                lines.push('');
                lines.push('My name: ');
                lines.push('Delivery address: ');
                lines.push('Phone (if different): ');
                return lines.join('\n');
            },
            // Server-side checkout (S1): capture name + phone + address, POST to
            // the storefront so the order is recorded in the DB BEFORE WhatsApp.
            async checkout() {
                if (this.cart.length === 0) {
                    return this.modal({
                        title: 'Your cart is empty',
                        body: 'Browse the catalog and add a product before checking out.',
                        confirm: 'OK',
                    });
                }
                const inp = (id, ph, attrs) =>
                    `<label style="display:block;margin-bottom:10px"><span style="display:block;font-size:12px;color:#6B807C;margin-bottom:3px">${ph}</span>` +
                    `<input id="${id}" ${attrs || ''} style="width:100%;padding:9px 11px;border:1px solid #d9e2df;border-radius:9px;font-size:14px"></label>`;
                const summary =
                    `${this.cart.length} item${this.cart.length === 1 ? '' : 's'} · ${fmt(this.cartTotal())}`;
                this.modal({
                    title: 'Checkout',
                    bodyHtml: `
 <div class="sf-modal-body" style="text-align:left">
 <div style="font-size:12.5px;color:#6B807C;margin-bottom:12px">${esc(summary)}</div>
 ${inp('sf-co-name', 'Your name', 'maxlength="120"')}
 ${inp('sf-co-phone', 'WhatsApp number', 'type="tel" inputmode="tel" maxlength="32" placeholder="+1 555 123 4567" onblur="STOREFRONT.fireAbandon()"')}
 <label style="display:block;margin-bottom:10px"><span style="display:block;font-size:12px;color:#6B807C;margin-bottom:3px">Delivery address</span>
 <textarea id="sf-co-addr" rows="2" maxlength="1000" style="width:100%;padding:9px 11px;border:1px solid #d9e2df;border-radius:9px;font-size:14px"></textarea></label>
 ${inp('sf-co-note', 'Note (optional)', 'maxlength="1000"')}
 <div style="display:flex;gap:8px;align-items:flex-end;margin-bottom:10px">
 <label style="flex:1"><span style="display:block;font-size:12px;color:#6B807C;margin-bottom:3px">Discount code</span>
 <input id="sf-co-coupon" maxlength="64" style="width:100%;padding:9px 11px;border:1px solid #d9e2df;border-radius:9px;font-size:14px;text-transform:uppercase"></label>
 <button type="button" class="sf-cta-ghost" style="padding:9px 14px" onclick="STOREFRONT.applyCoupon()">Apply</button>
 </div>
 <div id="sf-co-coupon-msg" style="font-size:12px;min-height:16px"></div>
 <div style="margin:10px 0">
 <span style="display:block;font-size:12px;color:#6B807C;margin-bottom:5px">Payment</span>
 <label style="display:inline-flex;align-items:center;gap:6px;margin-right:16px;font-size:13.5px"><input type="radio" name="sf-co-pay" value="prepaid" checked> Pay online / on delivery details</label>
 <label style="display:inline-flex;align-items:center;gap:6px;font-size:13.5px"><input type="radio" name="sf-co-pay" value="cod"> Cash on delivery</label>
 </div>
 <div id="sf-co-status" style="font-size:12.5px;min-height:18px;margin-top:2px"></div>
 <button type="button" class="sf-cta" style="width:100%;margin-top:8px" onclick="STOREFRONT.submitCheckout()">Place order</button>
 </div>`,
                });
            },

            async submitProductReview(productId) {
                const msg = document.getElementById('rv-msg');
                const name = (document.getElementById('rv-name')?.value || '').trim();
                const rating = parseInt(document.getElementById('rv-rating')?.value || '5', 10);
                const body = (document.getElementById('rv-body')?.value || '').trim();
                if (!name) {
                    if (msg) {
                        msg.style.color = '#A1431F';
                        msg.textContent = 'Please add your name.';
                    }
                    return;
                }
                try {
                    const res = await fetch(this.reviewUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            product_id: productId,
                            name,
                            rating,
                            body
                        }),
                    });
                    const j = await res.json().catch(() => ({}));
                    if (res.ok && j.ok) {
                        if (msg) {
                            msg.style.color = '#075E54';
                            msg.textContent = j.message || 'Thanks for your review!';
                        }
                        ['rv-name', 'rv-body'].forEach((id) => {
                            const el = document.getElementById(id);
                            if (el) el.value = '';
                        });
                    } else {
                        if (msg) {
                            msg.style.color = '#A1431F';
                            msg.textContent = j.message || 'Could not submit your review.';
                        }
                    }
                } catch (e) {
                    if (msg) {
                        msg.style.color = '#A1431F';
                        msg.textContent = 'Network error. Please try again.';
                    }
                }
            },

            // S3 — beacon an abandoned cart when the buyer enters a phone but hasn't
            // ordered yet. Fires once per modal; the server cancels the nudge if the
            // order completes.
            async fireAbandon() {
                if (this._abandonSent || this.cart.length === 0) return;
                const phoneEl = document.getElementById('sf-co-phone');
                const phone = (phoneEl?.value || '').trim();
                if (phone.replace(/\D/g, '').length < 7) return;
                this._abandonSent = true;
                try {
                    await fetch(this.abandonUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            name: (document.getElementById('sf-co-name')?.value || '')
                                .trim(),
                            phone,
                            items: this.cart.map((i) => ({
                                id: i.id,
                                qty: i.qty
                            })),
                        }),
                    });
                } catch (e) {
                    /* best-effort */ }
            },

            async applyCoupon() {
                const codeEl = document.getElementById('sf-co-coupon');
                const msg = document.getElementById('sf-co-coupon-msg');
                const code = (codeEl?.value || '').trim();
                if (!code) {
                    this.couponCode = null;
                    if (msg) msg.textContent = '';
                    return;
                }
                try {
                    const res = await fetch(this.couponUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            code,
                            items: this.cart.map((i) => ({
                                id: i.id,
                                qty: i.qty
                            }))
                        }),
                    });
                    const j = await res.json().catch(() => ({}));
                    if (res.ok && j.ok) {
                        this.couponCode = j.code;
                        if (msg) {
                            msg.style.color = '#075E54';
                            const off = j.free_shipping && !j.discount_minor ? 'Free shipping applied' :
                                ('You save ' + fmt(j.discount_minor));
                            msg.textContent = off + ' · New total ' + fmt(j.total_minor);
                        }
                    } else {
                        this.couponCode = null;
                        if (msg) {
                            msg.style.color = '#A1431F';
                            msg.textContent = j.message || 'Invalid code.';
                        }
                    }
                } catch (e) {
                    if (msg) {
                        msg.style.color = '#A1431F';
                        msg.textContent = 'Could not check that code.';
                    }
                }
            },

            async submitCheckout() {
                const $ = (id) => document.getElementById(id);
                const status = $('sf-co-status');
                const btn = status?.parentElement?.querySelector('.sf-cta');
                const name = ($('sf-co-name')?.value || '').trim();
                const phone = ($('sf-co-phone')?.value || '').trim();
                const setErr = (m) => {
                    if (status) {
                        status.style.color = '#A1431F';
                        status.textContent = m;
                    }
                };

                if (!name) return setErr('Please enter your name.');
                if (phone.replace(/\D/g, '').length < 7) return setErr(
                    'Please enter a valid WhatsApp number.');
                if (this.cart.length === 0) return setErr('Your cart is empty.');

                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Placing order…';
                }
                if (status) {
                    status.style.color = '#6B807C';
                    status.textContent = '';
                }

                try {
                    const res = await fetch(this.checkoutUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            name,
                            phone,
                            address: ($('sf-co-addr')?.value || '').trim(),
                            note: ($('sf-co-note')?.value || '').trim(),
                            coupon: (($('sf-co-coupon')?.value || this.couponCode ||
                                '')).trim(),
                            payment_method: (document.querySelector(
                                    'input[name=sf-co-pay]:checked')?.value ||
                                'prepaid'),
                            items: this.cart.map((i) => ({
                                id: i.id,
                                qty: i.qty
                            })),
                        }),
                    });
                    const j = await res.json().catch(() => ({}));
                    if (res.ok && j.ok) {
                        // Order captured — clear the cart and hand off to WhatsApp.
                        this.cart = [];
                        save(CART_KEY, []);
                        this.renderAll();
                        if (j.wa_url) {
                            if (status) {
                                status.style.color = '#075E54';
                                status.textContent = 'Order placed! Opening WhatsApp…';
                            }
                            window.location.href = j.wa_url;
                        } else {
                            const track = j.track_url ?
                                `<div style="margin-top:10px"><a class="sf-cta-ghost" href="${esc(j.track_url)}">Track order #${esc(j.order_no)} →</a></div>` :
                                '';
                            this.modal({
                                title: 'Order placed',
                                bodyHtml: `<div class="sf-modal-body">${esc(j.message || "Thank you! We'll be in touch shortly.")}${track}</div>`,
                                confirm: 'Done'
                            });
                        }
                    } else {
                        setErr(j.message || 'Could not place the order. Please try again.');
                        if (btn) {
                            btn.disabled = false;
                            btn.textContent = 'Place order';
                        }
                    }
                } catch (e) {
                    setErr('Network error. Please try again.');
                    if (btn) {
                        btn.disabled = false;
                        btn.textContent = 'Place order';
                    }
                }
            },
        };

        document.addEventListener('DOMContentLoaded', () => {
            api.renderAll();

            // Backdrop click on drawers
            document.querySelectorAll('[data-drawer-backdrop]').forEach(el => {
                el.addEventListener('click', (e) => {
                    const drawer = el.closest('.sf-drawer');
                    if (!drawer) return;
                    drawer.dataset.open = '0';
                    document.body.classList.remove('sf-drawer-open');
                });
            });

            // Escape closes any open overlay
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                document.querySelectorAll('.sf-drawer').forEach(d => d.dataset.open = '0');
                api.closeModal();
                document.body.classList.remove('sf-drawer-open');
            });
        });

        return api;
    })();
</script>

<style>
    /* ─────────────── Shared storefront UI shell ─────────────── */
    [data-cart-count],
    [data-wish-count],
    [data-compare-count] {
        display: inline-grid;
        place-items: center;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 9999px;
        font-size: 10.5px;
        font-weight: 700;
        background: #fff;
        color: #0B1F1C;
        transition: transform .2s cubic-bezier(.34, 1.56, .64, 1);
    }

    [data-cart-count].hidden-count,
    [data-wish-count].hidden-count,
    [data-compare-count].hidden-count {
        display: none;
    }

    [data-cart-count].bump,
    [data-wish-count].bump,
    [data-compare-count].bump {
        animation: sf-bump .35s cubic-bezier(.34, 1.56, .64, 1);
    }

    @keyframes sf-bump {
        0% {
            transform: scale(1)
        }

        40% {
            transform: scale(1.45)
        }

        100% {
            transform: scale(1)
        }
    }

    .sf-icon-btn {
        background: transparent;
        border: 1px solid currentColor;
        opacity: .85;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: inline-grid;
        place-items: center;
        cursor: pointer;
        position: relative;
        color: inherit;
    }

    .sf-icon-btn:hover {
        opacity: 1;
    }

    .sf-icon-btn [data-cart-count],
    .sf-icon-btn [data-wish-count],
    .sf-icon-btn [data-compare-count] {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #E87A5D;
        color: #fff;
    }

    /* Drawers */
    body.sf-drawer-open {
        overflow: hidden;
    }

    .sf-drawer {
        position: fixed;
        inset: 0;
        z-index: 100;
        pointer-events: none;
    }

    .sf-drawer[data-open="1"] {
        pointer-events: auto;
    }

    .sf-drawer-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(11, 31, 28, 0.45);
        opacity: 0;
        transition: opacity .25s;
    }

    .sf-drawer[data-open="1"] .sf-drawer-backdrop {
        opacity: 1;
    }

    .sf-drawer-panel {
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        width: 420px;
        max-width: 95vw;
        background: #fff;
        box-shadow: -10px 0 40px rgba(0, 0, 0, 0.12);
        transform: translateX(100%);
        transition: transform .25s cubic-bezier(.4, 0, .2, 1);
        display: flex;
        flex-direction: column;
    }

    .sf-drawer[data-open="1"] .sf-drawer-panel {
        transform: translateX(0);
    }

    .sf-drawer-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 22px;
        border-bottom: 1px solid #EAE6DC;
    }

    .sf-drawer-head h2 {
        font-family: 'Fraunces', serif;
        font-size: 22px;
        margin: 0;
    }

    .sf-drawer-head button {
        background: none;
        border: none;
        font-size: 22px;
        cursor: pointer;
        color: #6B807C;
    }

    .sf-drawer-body {
        flex: 1;
        overflow-y: auto;
        padding: 12px 22px;
    }

    .sf-drawer-foot {
        padding: 18px 22px;
        border-top: 1px solid #EAE6DC;
        background: #FBFAF6;
    }

    .sf-empty {
        text-align: center;
        padding: 60px 12px;
        color: #6B807C;
    }

    .sf-empty-title {
        font-family: 'Fraunces', serif;
        font-size: 20px;
        color: #0B1F1C;
        margin: 14px 0 6px;
    }

    .sf-empty-body {
        font-size: 13px;
        line-height: 1.55;
        max-width: 280px;
        margin: 0 auto;
    }

    .sf-empty-cta {
        margin-top: 18px;
        padding: 10px 22px;
        border-radius: 9999px;
        background: #0B1F1C;
        color: #fff;
        border: none;
        font-weight: 600;
        font-size: 12.5px;
        cursor: pointer;
    }

    /* Cart / wishlist rows */
    .sf-cart-row {
        display: grid;
        grid-template-columns: 64px 1fr 24px;
        gap: 12px;
        padding: 14px 0;
        border-bottom: 1px solid #EAE6DC;
        align-items: flex-start;
    }

    .sf-cart-img {
        width: 64px;
        height: 64px;
        border-radius: 10px;
        overflow: hidden;
        background: #F5F2EA;
        display: grid;
        place-items: center;
    }

    .sf-cart-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .sf-cart-meta {
        min-width: 0;
    }

    .sf-cart-name {
        font-weight: 600;
        font-size: 13.5px;
        color: #0B1F1C;
        display: block;
    }

    .sf-cart-price {
        font-family: 'JetBrains Mono', monospace;
        font-size: 11.5px;
        color: #6B807C;
        margin-top: 3px;
    }

    .sf-qty {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 8px;
    }

    .sf-qty button {
        width: 40px;
        height: 48px;
        border-radius: 12px;
        border: 1px solid var(--sf-border, #E5DFD0);
        background: var(--sf-surface, #fff);
        color: var(--sf-text, #0B1F1C);
        cursor: pointer;
        font-size: 18px;
        line-height: 1;
        transition: border-color .15s;
    }

    .sf-qty button:hover {
        border-color: var(--sf-brand, #0B1F1C);
    }

    .sf-qty input {
        width: 52px;
        height: 48px;
        border: 1px solid var(--sf-border, #E5DFD0);
        border-radius: 12px;
        text-align: center;
        font-family: 'JetBrains Mono', monospace;
        font-size: 14px;
        background: var(--sf-bg, #fff);
        color: var(--sf-text, #0B1F1C);
    }

    .sf-cart-remove {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: none;
        background: transparent;
        color: #6B807C;
        font-size: 18px;
        cursor: pointer;
    }

    .sf-cart-remove:hover {
        background: #FCE7DE;
        color: #E87A5D;
    }

    .sf-mini-btn {
        margin-top: 8px;
        padding: 6px 12px;
        border-radius: 9999px;
        border: 1px solid #0B1F1C;
        background: transparent;
        font-size: 11.5px;
        cursor: pointer;
    }

    .sf-mini-btn:hover {
        background: #0B1F1C;
        color: #fff;
    }

    /* Toast */
    .sf-toast {
        position: fixed;
        left: 50%;
        bottom: 28px;
        transform: translate(-50%, 20px);
        background: #0B1F1C;
        color: #FBFAF6;
        padding: 11px 22px;
        border-radius: 9999px;
        font-size: 13px;
        font-weight: 500;
        z-index: 1000;
        opacity: 0;
        transition: opacity .2s, transform .2s;
        box-shadow: 0 14px 40px rgba(0, 0, 0, 0.18);
    }

    .sf-toast.show {
        opacity: 1;
        transform: translate(-50%, 0);
    }

    /* Compare bar (sticky bottom strip) */
    .sf-compare-bar {
        position: fixed;
        left: 50%;
        bottom: 18px;
        transform: translateX(-50%);
        background: #fff;
        border: 1px solid #E5DFD0;
        border-radius: 18px;
        box-shadow: 0 24px 60px rgba(0, 0, 0, 0.18);
        padding: 12px 14px;
        display: none;
        gap: 8px;
        z-index: 80;
        max-width: 96vw;
        align-items: center;
    }

    .sf-cmp-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 4px 8px 4px 4px;
        border: 1px solid #E5DFD0;
        border-radius: 12px;
    }

    .sf-cmp-thumb {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        background: #F5F2EA;
        display: grid;
        place-items: center;
        overflow: hidden;
    }

    .sf-cmp-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .sf-cmp-name {
        font-size: 11.5px;
        max-width: 140px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sf-cmp-x {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        border: none;
        background: #F5F2EA;
        cursor: pointer;
        font-size: 14px;
        line-height: 1;
        color: #6B807C;
    }

    .sf-cmp-cta {
        padding: 9px 18px;
        border-radius: 9999px;
        background: #0B1F1C;
        color: #fff;
        border: none;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }

    .sf-compare-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12.5px;
    }

    .sf-compare-table th,
    .sf-compare-table td {
        padding: 12px 10px;
        border-bottom: 1px solid #EAE6DC;
        vertical-align: top;
        text-align: left;
    }

    .sf-compare-table thead th {
        background: #FBFAF6;
    }

    .sf-compare-table tbody th {
        width: 110px;
        font-weight: 600;
        color: #6B807C;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: .08em;
    }

    /* Modal */
    #sf-modal {
        position: fixed;
        inset: 0;
        z-index: 110;
        display: none;
    }

    #sf-modal[data-open="1"] {
        display: grid;
        place-items: center;
        padding: 16px;
    }

    #sf-modal [data-modal-backdrop] {
        position: absolute;
        inset: 0;
        background: rgba(11, 31, 28, 0.5);
    }

    #sf-modal [data-modal-card] {
        position: relative;
        background: #fff;
        border-radius: 20px;
        padding: 28px;
        max-width: 520px;
        width: 100%;
        box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
    }

    .sf-modal-close {
        position: absolute;
        top: 14px;
        right: 14px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 18px;
        color: #6B807C;
    }

    .sf-modal-close:hover {
        background: #F5F2EA;
    }

    .sf-modal-title {
        font-family: 'Fraunces', serif;
        font-size: 24px;
        line-height: 1.2;
        margin-bottom: 8px;
        padding-right: 28px;
    }

    .sf-modal-body {
        color: #6B807C;
        font-size: 13.5px;
        line-height: 1.6;
        margin-bottom: 18px;
    }

    .sf-modal-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }

    .sf-cta {
        padding: 11px 22px;
        border-radius: 12px;
        border: none;
        background: var(--sf-brand, #0B1F1C);
        color: #fff;
        font-weight: 600;
        font-size: 12.5px;
        cursor: pointer;
        transition: filter .15s, transform .1s;
    }

    .sf-cta:hover {
        filter: brightness(1.08);
    }

    .sf-cta:active {
        transform: scale(.99);
    }

    .sf-cta:disabled {
        background: var(--sf-border, #ccc);
        color: var(--sf-muted, #888);
        cursor: not-allowed;
        filter: none;
    }

    .sf-cta-ghost {
        padding: 13px 22px;
        border-radius: 12px;
        border: 1px solid var(--sf-border, #E5DFD0);
        background: transparent;
        font-weight: 600;
        font-size: 12.5px;
        cursor: pointer;
        text-decoration: none;
        color: var(--sf-text, inherit);
        display: inline-block;
        text-align: center;
        margin-top: 10px;
        transition: border-color .15s;
    }

    .sf-cta-ghost:hover {
        border-color: var(--sf-brand, #0B1F1C);
    }

    /* Quick view */
    .sf-qv {
        display: grid;
        grid-template-columns: 200px 1fr;
        gap: 22px;
        align-items: start;
    }

    .sf-qv-img {
        aspect-ratio: 1/1;
        border-radius: 14px;
        overflow: hidden;
        background: #F5F2EA;
        display: grid;
        place-items: center;
    }

    .sf-qv-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .sf-qv-price {
        display: flex;
        align-items: baseline;
        gap: 8px;
        margin-bottom: 8px;
    }

    .sf-qv-now {
        font-family: 'Fraunces', serif;
        font-size: 26px;
        color: #0B1F1C;
    }

    .sf-qv-was {
        font-family: 'JetBrains Mono', monospace;
        font-size: 13px;
        color: #6B807C;
        text-decoration: line-through;
    }

    .sf-qv-desc {
        color: #6B807C;
        font-size: 13.5px;
        line-height: 1.55;
        margin: 8px 0 18px;
    }

    .sf-qv-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    @media (max-width: 580px) {
        .sf-qv {
            grid-template-columns: 1fr;
        }
    }

    /* Image fallback — for any 404 that fires onerror (SVG glyph, theme-aware) */
    .sf-img-fallback {
        display: grid;
        place-items: center;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, var(--sf-bg, #F5F2EA), var(--sf-surface, #fff));
        color: var(--sf-muted, #6B807C);
    }

    .sf-img-fallback svg {
        width: 34px;
        height: 34px;
        opacity: .5;
    }

    /* Product card extras */
    .sf-card-pos {
        position: relative;
    }

    .sf-card-pos .sf-wish,
    .sf-card-pos .sf-quick {
        position: absolute;
        right: 12px;
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 1px solid var(--sf-border, #E5DFD0);
        background: color-mix(in srgb, var(--sf-surface, #fff) 92%, transparent);
        color: var(--sf-text, #0B1F1C);
        display: grid;
        place-items: center;
        cursor: pointer;
        opacity: 0;
        transform: translateY(-6px);
        transition: opacity .2s, transform .25s, background .15s, color .15s;
        backdrop-filter: blur(6px);
        box-shadow: 0 4px 14px -6px rgba(0, 0, 0, .25);
    }

    .sf-card-pos .sf-wish {
        top: 12px;
    }

    .sf-card-pos .sf-quick {
        top: 54px;
    }

    .sf-card-pos:hover .sf-wish,
    .sf-card-pos:hover .sf-quick {
        opacity: 1;
        transform: translateY(0);
    }

    .sf-card-pos .sf-wish:hover,
    .sf-card-pos .sf-quick:hover {
        color: var(--sf-brand, #0B1F1C);
    }

    .sf-card-pos .sf-wish.is-on {
        opacity: 1;
        transform: translateY(0);
        color: #E8506B;
    }

    .sf-card-pos .sf-wish svg {
        transition: transform .2s;
    }

    .sf-card-pos .sf-wish:hover svg {
        transform: scale(1.15);
    }

    .sf-card-pos .sf-wish.is-on svg {
        fill: #E8506B;
        stroke: #E8506B;
    }

    .sf-card-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        display: inline-flex;
        gap: 6px;
        flex-wrap: wrap;
        z-index: 1;
    }

    .sf-card-badge span {
        background: var(--sf-text, #0B1F1C);
        color: var(--sf-surface, #fff);
        font-size: 10px;
        font-weight: 700;
        padding: 4px 9px;
        border-radius: 9999px;
        letter-spacing: .04em;
        text-transform: uppercase;
        box-shadow: 0 2px 8px -2px rgba(0, 0, 0, .3);
    }

    .sf-card-badge .sf-badge-stock {
        background: var(--sf-muted, #6B807C);
    }

    .sf-card-was {
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        color: var(--sf-muted, #6B807C);
        text-decoration: line-through;
        margin-left: 6px;
    }

    /* Filter sidebar */
    .sf-filters {
        background: var(--sf-surface, #fff);
        border: 1px solid var(--sf-border, #EAE6DC);
        border-radius: 16px;
        padding: 18px;
        align-self: start;
        position: sticky;
        top: 92px;
    }

    .sf-filters>h3:first-child {
        font-size: 15px;
        font-family: inherit;
        letter-spacing: -.01em;
        text-transform: none;
        color: var(--sf-text, #0B1F1C);
        margin-bottom: 14px;
    }

    .sf-filters h3 {
        font-family: 'JetBrains Mono', monospace;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .14em;
        color: var(--sf-muted, #6B807C);
        margin: 0 0 10px;
    }

    .sf-filter-group {
        padding: 14px 0;
        border-top: 1px solid var(--sf-border, #EAE6DC);
    }

    .sf-filter-group:first-of-type {
        border-top: none;
        padding-top: 0;
    }

    .sf-filter-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 5px 0;
        font-size: 13px;
        cursor: pointer;
        color: var(--sf-text, #0B1F1C);
    }

    .sf-filter-group input[type="checkbox"] {
        accent-color: var(--sf-brand, #0B1F1C);
    }

    .sf-price-range {
        display: flex;
        gap: 8px;
        align-items: center;
        margin-top: 6px;
    }

    .sf-price-range input {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid var(--sf-border, #E5DFD0);
        border-radius: 9px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 12px;
        background: var(--sf-bg, #fff);
        color: var(--sf-text, #0B1F1C);
    }

    /* Sort + view toggle */
    .sf-toolbar {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 24px;
        margin-bottom: 22px;
        flex-wrap: wrap;
    }

    .sf-toolbar .sf-count {
        color: var(--sf-muted, #6B807C);
        font-size: 12.5px;
        font-family: 'JetBrains Mono', monospace;
    }

    .sf-toolbar select {
        padding: 9px 14px;
        border: 1px solid var(--sf-border, #E5DFD0);
        border-radius: 9999px;
        background: var(--sf-surface, #fff);
        color: var(--sf-text, #0B1F1C);
        font-size: 12.5px;
        cursor: pointer;
    }

    .sf-view-toggle button {
        background: var(--sf-surface, transparent);
        color: var(--sf-text, #0B1F1C);
        border: 1px solid var(--sf-border, #E5DFD0);
        padding: 8px 12px;
        cursor: pointer;
        font-size: 12px;
        display: inline-grid;
        place-items: center;
    }

    .sf-view-toggle button:first-child {
        border-radius: 9999px 0 0 9999px;
    }

    .sf-view-toggle button:last-child {
        border-radius: 0 9999px 9999px 0;
        margin-left: -1px;
    }

    .sf-view-toggle button.is-on {
        background: var(--sf-brand, #0B1F1C);
        color: #fff;
        border-color: var(--sf-brand, #0B1F1C);
    }

    /* Product detail page extras (theme-aware via CSS vars) */
    .pd {
        max-width: 1200px;
        margin: 0 auto;
    }

    .sf-pd-gallery {
        display: grid;
        grid-template-columns: 84px 1fr;
        gap: 14px;
    }

    .sf-pd-thumbs {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .sf-pd-thumbs button {
        width: 84px;
        aspect-ratio: 1/1;
        border: 2px solid transparent;
        border-radius: 12px;
        overflow: hidden;
        padding: 0;
        cursor: pointer;
        background: var(--sf-bg, #F5F2EA);
    }

    .sf-pd-thumbs button.is-on {
        border-color: var(--sf-brand, #0B1F1C);
    }

    .sf-pd-thumbs img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .sf-pd-main {
        aspect-ratio: 1/1;
        border-radius: 18px;
        overflow: hidden;
        background: linear-gradient(135deg, var(--sf-bg, #F5F2EA), var(--sf-surface, #fff));
        display: grid;
        place-items: center;
        border: 1px solid var(--sf-border, #E5DFD0);
    }

    .sf-pd-main img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .sf-pd-main .sf-img-fallback svg {
        width: 56px;
        height: 56px;
    }

    .sf-pd-price {
        display: flex;
        align-items: baseline;
        gap: 10px;
        margin: 14px 0 10px;
    }

    .sf-pd-now {
        font-family: 'Fraunces', serif;
        font-size: 32px;
        font-weight: 600;
        color: var(--sf-text, #0B1F1C);
    }

    .sf-pd-was {
        font-family: 'JetBrains Mono', monospace;
        font-size: 15px;
        color: var(--sf-muted, #6B807C);
        text-decoration: line-through;
    }

    .sf-pd-save {
        background: #E8506B;
        color: #fff;
        font-size: 11px;
        padding: 3px 9px;
        border-radius: 9999px;
        font-weight: 700;
    }

    .sf-pd-stock {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 11.5px;
        padding: 5px 12px;
        border-radius: 9999px;
        background: color-mix(in srgb, var(--sf-brand, #075E54) 12%, transparent);
        color: var(--sf-brand, #075E54);
    }

    .sf-pd-stock.out {
        background: rgba(232, 80, 107, 0.12);
        color: #E8506B;
    }

    .sf-pd-actions {
        display: flex;
        gap: 10px;
        margin: 22px 0;
        flex-wrap: wrap;
        align-items: center;
    }

    .sf-pd-actions .sf-cta {
        flex: 1;
        min-width: 200px;
        padding: 15px 22px;
        font-size: 13.5px;
        border-radius: 12px;
    }

    .sf-pd-icons {
        display: flex;
        gap: 8px;
    }

    .sf-pd-icons button {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        border: 1px solid var(--sf-border, #E5DFD0);
        background: var(--sf-surface, #fff);
        color: var(--sf-text, #0B1F1C);
        cursor: pointer;
        display: grid;
        place-items: center;
        transition: border-color .15s, color .15s;
    }

    .sf-pd-icons button:hover {
        border-color: var(--sf-brand, #0B1F1C);
        color: var(--sf-brand, #0B1F1C);
    }

    .sf-pd-icons button.is-on {
        background: rgba(232, 80, 107, 0.12);
        color: #E8506B;
        border-color: #E8506B;
    }

    .sf-pd-meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px 18px;
        padding: 18px 0;
        border-top: 1px solid var(--sf-border, #EAE6DC);
        margin-top: 20px;
        font-size: 12.5px;
        color: var(--sf-text, #0B1F1C);
    }

    .sf-pd-meta dt {
        color: var(--sf-muted, #6B807C);
        font-family: 'JetBrains Mono', monospace;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .1em;
    }

    .sf-pd-meta dd {
        margin: 0 0 8px;
    }

    .sf-tabs {
        margin-top: 40px;
        padding: 0 24px;
        max-width: 1200px;
        margin-inline: auto;
    }

    .sf-tabs-head {
        display: flex;
        gap: 4px;
        border-bottom: 1px solid var(--sf-border, #EAE6DC);
    }

    .sf-tabs-head button {
        background: none;
        border: none;
        padding: 13px 18px;
        cursor: pointer;
        font-size: 13px;
        color: var(--sf-muted, #6B807C);
        border-bottom: 2px solid transparent;
    }

    .sf-tabs-head button.is-on {
        color: var(--sf-text, #0B1F1C);
        border-bottom-color: var(--sf-brand, #0B1F1C);
        font-weight: 600;
    }

    .sf-tabs-pane {
        padding: 24px 0;
        display: none;
        color: var(--sf-muted, #44524F);
        font-size: 14px;
        line-height: 1.7;
    }

    .sf-tabs-pane.is-on {
        display: block;
    }

    .sf-related {
        padding: 48px 24px;
        max-width: 1280px;
        margin: 0 auto;
    }

    .sf-related h2 {
        font-family: 'Fraunces', serif;
        font-size: 26px;
        margin-bottom: 20px;
        color: var(--sf-text, #0B1F1C);
    }

    .sf-related-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 20px;
    }

    @media (max-width: 760px) {
        .sf-drawer-panel {
            width: 95vw;
        }

        .sf-pd-gallery {
            grid-template-columns: 1fr;
        }

        .sf-pd-thumbs {
            flex-direction: row;
            overflow-x: auto;
        }
    }
</style>

{{-- ─────────────── Cart drawer ─────────────── --}}
<div id="sf-cart-drawer" class="sf-drawer" data-open="0">
    <div class="sf-drawer-backdrop" data-drawer-backdrop></div>
    <aside class="sf-drawer-panel" role="dialog" aria-label="{{ __('Cart') }}">
        <header class="sf-drawer-head">
            <h2>{{ __('Your cart') }}</h2>
            <button onclick="STOREFRONT.toggleDrawer('cart')" aria-label="{{ __('Close') }}">×</button>
        </header>
        <div class="sf-drawer-body" data-cart-items></div>
        <footer class="sf-drawer-foot">
            @php $sfZero = \App\Support\FormatSettings::formatIn(0, $sf->currency_code ?? 'USD'); @endphp
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;color:#6B807C">
                <span>{{ __('Subtotal') }}</span><span data-cart-subtotal
                    style="font-family:'JetBrains Mono',monospace;color:#0B1F1C">{!! $sfZero !!}</span>
            </div>
            <div data-cart-shipping></div>
            <div
                style="display:flex;justify-content:space-between;margin-bottom:14px;padding-top:8px;border-top:1px solid #EAE6DC;font-size:14px;font-weight:600">
                <span>{{ __('Total') }}</span><span data-cart-total
                    style="font-family:'JetBrains Mono',monospace">{!! $sfZero !!}</span>
            </div>
            @if ($sf->shipping_json['note'] ?? null)
                <div style="text-align:center;margin-bottom:10px;font-size:11px;color:#6B807C">
                    {{ $sf->shipping_json['note'] }}</div>
            @endif
            <button onclick="STOREFRONT.checkout()"
                style="width:100%;padding:13px 22px;border-radius:9999px;background:{{ $brand }};color:#fff;border:none;font-weight:600;font-size:13.5px;cursor:pointer">{{ __('Order on WhatsApp →') }}</button>
            <div
                style="text-align:center;margin-top:10px;font-size:11px;color:#6B807C;font-family:'JetBrains Mono',monospace">
                @php
                    $payLabels = [
                        'upi' => 'UPI',
                        'razorpay_link' => 'Razorpay',
                        'stripe_link' => 'Card',
                        'paypal_me' => 'PayPal',
                        'bank_transfer' => 'Bank transfer',
                    ];
                    $payLabel = $payLabels[$sf->payment_provider ?? ''] ?? null;
                @endphp
                @if ($payLabel)
                    Pay via {{ $payLabel }} · Cash on delivery
                @else
                    Cash on delivery · Confirm in chat
                @endif
            </div>
        </footer>
    </aside>
</div>

{{-- ─────────────── Wishlist drawer ─────────────── --}}
<div id="sf-wish-drawer" class="sf-drawer" data-open="0">
    <div class="sf-drawer-backdrop" data-drawer-backdrop></div>
    <aside class="sf-drawer-panel" role="dialog" aria-label="{{ __('Wishlist') }}">
        <header class="sf-drawer-head">
            <h2>{{ __('Wishlist') }}</h2>
            <button onclick="STOREFRONT.toggleDrawer('wish')" aria-label="{{ __('Close') }}">×</button>
        </header>
        <div class="sf-drawer-body" data-wish-items></div>
    </aside>
</div>

{{-- ─────────────── Compare drawer ─────────────── --}}
<div id="sf-compare-drawer" class="sf-drawer" data-open="0">
    <div class="sf-drawer-backdrop" data-drawer-backdrop></div>
    <aside class="sf-drawer-panel" role="dialog" aria-label="{{ __('Compare') }}" style="width:680px">
        <header class="sf-drawer-head">
            <h2>{{ __('Compare products') }}</h2>
            <button onclick="STOREFRONT.toggleDrawer('compare')" aria-label="{{ __('Close') }}">×</button>
        </header>
        <div class="sf-drawer-body" data-compare-table></div>
    </aside>
</div>

{{-- ─────────────── Sticky compare bar (when 1+ in compare) ─────────────── --}}
<div id="sf-compare-bar" class="sf-compare-bar">
    <div data-compare-list style="display:inline-flex;gap:8px"></div>
    <button class="sf-cmp-cta" onclick="STOREFRONT.toggleDrawer('compare')">{{ __('Compare →') }}</button>
</div>

{{-- ─────────────── Universal modal ─────────────── --}}
<div id="sf-modal" data-open="0">
    <div data-modal-backdrop></div>
    <div data-modal-card></div>
</div>
