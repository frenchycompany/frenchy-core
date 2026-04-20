class BookingWidget {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.logementId = options.logementId || 1;
        this.apiBase = options.apiBase || './api';
        this.lang = options.lang || 'fr';
        this.currency = options.currency || 'EUR';

        this.currentMonth = new Date();
        this.currentMonth.setDate(1);

        this.bookedDates = new Set();
        this.prices = {};
        this.periods = [];
        this.selecting = false;
        this.selectionStart = null;
        this.selectionEnd = null;

        this.months = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        this.days = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];

        this.render();
        this.loadAvailability();
    }

    render() {
        this.container.innerHTML = `
            <div class="bw-widget">
                <div class="bw-main">
                    <div class="bw-calendar-section">
                        <div class="bw-calendar-header">
                            <button class="bw-nav-btn" data-dir="-1">&larr;</button>
                            <div class="bw-month-display">
                                <span class="bw-month-title"></span>
                            </div>
                            <button class="bw-nav-btn" data-dir="1">&rarr;</button>
                        </div>
                        <div class="bw-calendars-grid">
                            <div class="bw-calendar" id="bw-cal-1"></div>
                            <div class="bw-calendar" id="bw-cal-2"></div>
                        </div>
                        <div class="bw-legend">
                            <span class="bw-legend-item"><span class="bw-dot bw-dot-available"></span> Disponible</span>
                            <span class="bw-legend-item"><span class="bw-dot bw-dot-booked"></span> Réservé</span>
                            <span class="bw-legend-item"><span class="bw-dot bw-dot-selected"></span> Sélectionné</span>
                        </div>
                    </div>
                    <div class="bw-sidebar">
                        <div class="bw-cart">
                            <h3 class="bw-cart-title">Votre séjour</h3>
                            <div class="bw-periods-list" id="bw-periods-list">
                                <p class="bw-empty-cart">Sélectionnez vos dates sur le calendrier</p>
                            </div>
                            <div class="bw-add-period-hint" id="bw-add-hint" style="display:none">
                                <button class="bw-btn bw-btn-outline" id="bw-add-period-btn">+ Ajouter une autre période</button>
                            </div>
                            <div class="bw-cart-summary" id="bw-cart-summary" style="display:none">
                                <div class="bw-summary-line"><span>Sous-total</span><span id="bw-subtotal"></span></div>
                                <div class="bw-summary-line bw-discount" id="bw-discount-line" style="display:none">
                                    <span>Remise long séjour (<span id="bw-discount-pct"></span>%)</span>
                                    <span id="bw-discount-amount"></span>
                                </div>
                                <div class="bw-summary-total"><span>Total</span><span id="bw-total"></span></div>
                            </div>
                            <button class="bw-btn bw-btn-primary" id="bw-book-btn" style="display:none">Réserver maintenant</button>
                        </div>
                    </div>
                </div>
                <div class="bw-form-overlay" id="bw-form-overlay" style="display:none">
                    <div class="bw-form-modal">
                        <button class="bw-form-close" id="bw-form-close">&times;</button>
                        <h3>Finaliser la réservation</h3>
                        <form id="bw-booking-form">
                            <div class="bw-form-section">
                                <h4>Informations personnelles</h4>
                                <div class="bw-form-row">
                                    <div class="bw-form-group">
                                        <label>Prénom *</label>
                                        <input type="text" name="prenom" required>
                                    </div>
                                    <div class="bw-form-group">
                                        <label>Nom *</label>
                                        <input type="text" name="nom" required>
                                    </div>
                                </div>
                                <div class="bw-form-row">
                                    <div class="bw-form-group">
                                        <label>Email *</label>
                                        <input type="email" name="email" required>
                                    </div>
                                    <div class="bw-form-group">
                                        <label>Téléphone *</label>
                                        <input type="tel" name="telephone" required placeholder="+33...">
                                    </div>
                                </div>
                                <div class="bw-form-row">
                                    <div class="bw-form-group">
                                        <label>Adultes</label>
                                        <input type="number" name="nb_adultes" value="1" min="1" max="20">
                                    </div>
                                    <div class="bw-form-group">
                                        <label>Enfants</label>
                                        <input type="number" name="nb_enfants" value="0" min="0" max="10">
                                    </div>
                                </div>
                            </div>
                            <div class="bw-form-section">
                                <label class="bw-toggle">
                                    <input type="checkbox" id="bw-pro-toggle" name="is_pro">
                                    <span class="bw-toggle-label">Réservation professionnelle</span>
                                </label>
                                <div id="bw-pro-fields" style="display:none">
                                    <div class="bw-form-group">
                                        <label>Raison sociale *</label>
                                        <input type="text" name="raison_sociale">
                                    </div>
                                    <div class="bw-form-row">
                                        <div class="bw-form-group">
                                            <label>SIRET *</label>
                                            <input type="text" name="siret" placeholder="XXX XXX XXX XXXXX">
                                        </div>
                                        <div class="bw-form-group">
                                            <label>TVA intracommunautaire</label>
                                            <input type="text" name="tva_intracommunautaire" placeholder="FRXXXXXXXXX">
                                        </div>
                                    </div>
                                    <div class="bw-form-group">
                                        <label>Adresse de facturation *</label>
                                        <textarea name="adresse_facturation" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="bw-form-section">
                                <h4>Mode de paiement</h4>
                                <div class="bw-payment-options">
                                    <label class="bw-payment-option">
                                        <input type="radio" name="payment_method" value="stripe" checked>
                                        <span class="bw-payment-card">
                                            <strong>Carte bancaire</strong>
                                            <small>Paiement sécurisé immédiat via Stripe</small>
                                        </span>
                                    </label>
                                    <label class="bw-payment-option">
                                        <input type="radio" name="payment_method" value="virement">
                                        <span class="bw-payment-card">
                                            <strong>Virement bancaire</strong>
                                            <small>Sous 48h - Confirmation manuelle</small>
                                        </span>
                                    </label>
                                </div>
                            </div>
                            <div class="bw-form-section bw-form-recap" id="bw-form-recap"></div>
                            <button type="submit" class="bw-btn bw-btn-primary bw-btn-full">Confirmer la réservation</button>
                        </form>
                    </div>
                </div>
                <div class="bw-confirmation" id="bw-confirmation" style="display:none">
                    <div class="bw-confirm-content"></div>
                </div>
            </div>
        `;
        this.bindEvents();
    }

    bindEvents() {
        this.container.querySelectorAll('.bw-nav-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const dir = parseInt(btn.dataset.dir);
                this.currentMonth.setMonth(this.currentMonth.getMonth() + dir);
                this.loadAvailability();
            });
        });

        document.getElementById('bw-add-period-btn')?.addEventListener('click', () => {
            this.selecting = false;
            this.selectionStart = null;
            this.selectionEnd = null;
            this.renderCalendars();
        });

        document.getElementById('bw-book-btn')?.addEventListener('click', () => this.showForm());
        document.getElementById('bw-form-close')?.addEventListener('click', () => this.hideForm());
        document.getElementById('bw-pro-toggle')?.addEventListener('change', (e) => {
            document.getElementById('bw-pro-fields').style.display = e.target.checked ? 'block' : 'none';
        });
        document.getElementById('bw-booking-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitBooking();
        });

        document.getElementById('bw-form-overlay')?.addEventListener('click', (e) => {
            if (e.target.id === 'bw-form-overlay') this.hideForm();
        });
    }

    async loadAvailability() {
        const m1 = new Date(this.currentMonth);
        const m2 = new Date(this.currentMonth);
        m2.setMonth(m2.getMonth() + 1);

        const fmt = d => d.toISOString().slice(0, 10);
        const endM1 = new Date(m1.getFullYear(), m1.getMonth() + 1, 0);
        const endM2 = new Date(m2.getFullYear(), m2.getMonth() + 1, 0);

        try {
            const [r1, r2] = await Promise.all([
                fetch(`${this.apiBase}/availability.php?logement_id=${this.logementId}&start=${fmt(m1)}&end=${fmt(endM1)}`).then(r => r.json()),
                fetch(`${this.apiBase}/availability.php?logement_id=${this.logementId}&start=${fmt(m2)}&end=${fmt(endM2)}`).then(r => r.json()),
            ]);

            this.bookedDates = new Set([...(r1.booked || []), ...(r2.booked || [])]);
            this.prices = { ...(r1.prices || {}), ...(r2.prices || {}) };
        } catch (e) {
            this.bookedDates = new Set();
            this.prices = {};
        }

        this.renderCalendars();
    }

    renderCalendars() {
        const m1 = new Date(this.currentMonth);
        const m2 = new Date(this.currentMonth);
        m2.setMonth(m2.getMonth() + 1);

        const titleEl = this.container.querySelector('.bw-month-title');
        titleEl.textContent = `${this.months[m1.getMonth()]} ${m1.getFullYear()} — ${this.months[m2.getMonth()]} ${m2.getFullYear()}`;

        this.renderMonth(document.getElementById('bw-cal-1'), m1);
        this.renderMonth(document.getElementById('bw-cal-2'), m2);
    }

    renderMonth(el, monthDate) {
        const year = monthDate.getFullYear();
        const month = monthDate.getMonth();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstDow = (new Date(year, month, 1).getDay() + 6) % 7;

        let html = `<div class="bw-month-name">${this.months[month]} ${year}</div>`;
        html += '<div class="bw-day-names">';
        this.days.forEach(d => html += `<span>${d}</span>`);
        html += '</div><div class="bw-day-grid">';

        for (let i = 0; i < firstDow; i++) {
            html += '<span class="bw-day bw-day-empty"></span>';
        }

        const today = new Date().toISOString().slice(0, 10);

        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const isBooked = this.bookedDates.has(dateStr);
            const isPast = dateStr < today;
            const isSelected = this.isDateSelected(dateStr);
            const isInSelection = this.isDateInCurrentSelection(dateStr);
            const price = this.prices[dateStr];

            let cls = 'bw-day';
            if (isBooked) cls += ' bw-day-booked';
            else if (isPast) cls += ' bw-day-past';
            else cls += ' bw-day-available';
            if (isSelected) cls += ' bw-day-selected';
            if (isInSelection) cls += ' bw-day-in-selection';

            const priceStr = price && !isBooked && !isPast ? `<span class="bw-day-price">${Math.round(price)}€</span>` : '';

            html += `<span class="${cls}" data-date="${dateStr}">
                <span class="bw-day-num">${d}</span>
                ${priceStr}
            </span>`;
        }

        html += '</div>';
        el.innerHTML = html;

        el.querySelectorAll('.bw-day-available').forEach(dayEl => {
            dayEl.addEventListener('click', () => this.onDateClick(dayEl.dataset.date));
            dayEl.addEventListener('mouseenter', () => this.onDateHover(dayEl.dataset.date));
        });
    }

    onDateClick(dateStr) {
        if (!this.selectionStart || this.selectionEnd) {
            this.selectionStart = dateStr;
            this.selectionEnd = null;
            this.selecting = true;
        } else {
            if (dateStr < this.selectionStart) {
                this.selectionEnd = this.selectionStart;
                this.selectionStart = dateStr;
            } else {
                this.selectionEnd = dateStr;
            }
            this.selecting = false;

            if (!this.hasConflict(this.selectionStart, this.selectionEnd)) {
                const checkout = new Date(this.selectionEnd);
                checkout.setDate(checkout.getDate() + 1);
                this.periods.push({
                    checkin: this.selectionStart,
                    checkout: checkout.toISOString().slice(0, 10),
                });
                this.updateCart();
            }
            this.selectionStart = null;
            this.selectionEnd = null;
        }
        this.renderCalendars();
    }

    onDateHover(dateStr) {
        if (!this.selecting || !this.selectionStart) return;
        this.selectionEnd = dateStr;
        this.renderCalendars();
    }

    hasConflict(start, end) {
        const s = new Date(start);
        const e = new Date(end);
        while (s <= e) {
            const d = s.toISOString().slice(0, 10);
            if (this.bookedDates.has(d)) return true;
            for (const p of this.periods) {
                if (d >= p.checkin && d < p.checkout) return true;
            }
            s.setDate(s.getDate() + 1);
        }
        return false;
    }

    isDateSelected(dateStr) {
        for (const p of this.periods) {
            if (dateStr >= p.checkin && dateStr < p.checkout) return true;
        }
        return false;
    }

    isDateInCurrentSelection(dateStr) {
        if (!this.selecting || !this.selectionStart) return false;
        const end = this.selectionEnd || this.selectionStart;
        const s = this.selectionStart < end ? this.selectionStart : end;
        const e = this.selectionStart < end ? end : this.selectionStart;
        return dateStr >= s && dateStr <= e;
    }

    async updateCart() {
        if (this.periods.length === 0) {
            document.getElementById('bw-periods-list').innerHTML = '<p class="bw-empty-cart">Sélectionnez vos dates sur le calendrier</p>';
            document.getElementById('bw-cart-summary').style.display = 'none';
            document.getElementById('bw-book-btn').style.display = 'none';
            document.getElementById('bw-add-hint').style.display = 'none';
            return;
        }

        try {
            const resp = await fetch(`${this.apiBase}/pricing.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({logement_id: this.logementId, periods: this.periods}),
            });
            const data = await resp.json();

            let html = '';
            data.periods.forEach((p, i) => {
                html += `
                    <div class="bw-period-card">
                        <div class="bw-period-dates">
                            <span>${this.formatDate(p.checkin)} → ${this.formatDate(p.checkout)}</span>
                            <button class="bw-period-remove" data-idx="${i}">&times;</button>
                        </div>
                        <div class="bw-period-detail">
                            ${p.nb_nights} nuit${p.nb_nights > 1 ? 's' : ''} &middot; ${p.avg_per_night}€/nuit
                        </div>
                        <div class="bw-period-price">${p.total.toFixed(2)}€</div>
                    </div>
                `;
            });

            document.getElementById('bw-periods-list').innerHTML = html;
            document.getElementById('bw-subtotal').textContent = data.subtotal.toFixed(2) + '€';
            document.getElementById('bw-total').textContent = data.total.toFixed(2) + '€';

            const discountLine = document.getElementById('bw-discount-line');
            if (data.long_stay_discount_percent > 0) {
                document.getElementById('bw-discount-pct').textContent = data.long_stay_discount_percent;
                document.getElementById('bw-discount-amount').textContent = '-' + data.long_stay_discount_amount.toFixed(2) + '€';
                discountLine.style.display = 'flex';
            } else {
                discountLine.style.display = 'none';
            }

            document.getElementById('bw-cart-summary').style.display = 'block';
            document.getElementById('bw-book-btn').style.display = 'block';
            document.getElementById('bw-add-hint').style.display = 'block';

            this.lastPricing = data;

            document.querySelectorAll('.bw-period-remove').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.periods.splice(parseInt(btn.dataset.idx), 1);
                    this.updateCart();
                    this.renderCalendars();
                });
            });
        } catch (e) {
            console.error('Pricing error:', e);
        }
    }

    formatDate(dateStr) {
        const d = new Date(dateStr + 'T12:00:00');
        return d.toLocaleDateString('fr-FR', {day: 'numeric', month: 'short'});
    }

    showForm() {
        const recap = document.getElementById('bw-form-recap');
        let html = '<h4>Récapitulatif</h4>';
        this.lastPricing.periods.forEach(p => {
            html += `<div class="bw-recap-line">
                <span>${this.formatDate(p.checkin)} → ${this.formatDate(p.checkout)} (${p.nb_nights} nuits)</span>
                <span>${p.total.toFixed(2)}€</span>
            </div>`;
        });
        if (this.lastPricing.long_stay_discount_amount > 0) {
            html += `<div class="bw-recap-line bw-discount">
                <span>Remise long séjour -${this.lastPricing.long_stay_discount_percent}%</span>
                <span>-${this.lastPricing.long_stay_discount_amount.toFixed(2)}€</span>
            </div>`;
        }
        html += `<div class="bw-recap-total">
            <span>Total</span><span>${this.lastPricing.total.toFixed(2)}€</span>
        </div>`;
        recap.innerHTML = html;

        document.getElementById('bw-form-overlay').style.display = 'flex';
    }

    hideForm() {
        document.getElementById('bw-form-overlay').style.display = 'none';
    }

    async submitBooking() {
        const form = document.getElementById('bw-booking-form');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        data.logement_id = this.logementId;
        data.periods = this.periods;
        data.is_pro = data.is_pro === 'on';

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Réservation en cours...';

        try {
            const resp = await fetch(`${this.apiBase}/book.php`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data),
            });
            const result = await resp.json();

            if (result.success) {
                if (result.payment_url) {
                    window.location.href = result.payment_url;
                    return;
                }

                this.hideForm();
                const confirm = document.getElementById('bw-confirmation');
                let html = '<div class="bw-confirm-icon">&#10003;</div>';
                html += `<h3>Réservation confirmée !</h3>`;
                html += `<p>Référence : <strong>${result.booking_ref}</strong></p>`;
                html += `<p>${result.nb_periods} période(s) &middot; ${result.total_nights} nuits &middot; ${result.total.toFixed(2)}€</p>`;

                if (result.virement_info) {
                    html += `<div class="bw-virement-info">
                        <h4>Instructions de virement</h4>
                        <p><strong>Bénéficiaire :</strong> ${result.virement_info.beneficiaire}</p>
                        <p><strong>IBAN :</strong> ${result.virement_info.iban}</p>
                        <p><strong>BIC :</strong> ${result.virement_info.bic}</p>
                        <p><strong>Montant :</strong> ${result.virement_info.montant.toFixed(2)}€</p>
                        <p><strong>Référence :</strong> ${result.virement_info.reference}</p>
                        <p><em>${result.virement_info.instruction}</em></p>
                    </div>`;
                }

                html += `<p>Un email de confirmation sera envoyé à votre adresse.</p>`;
                confirm.querySelector('.bw-confirm-content').innerHTML = html;
                confirm.style.display = 'flex';
            } else {
                alert(result.error || 'Erreur lors de la réservation');
            }
        } catch (e) {
            alert('Erreur de connexion');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Confirmer la réservation';
        }
    }
}
