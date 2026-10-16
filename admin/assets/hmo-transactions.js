/* HMO transactions page interactions. All mutations remain in-memory demo state. */
(() => {
    'use strict';

    const config = window.HMO_TRANSACTION_CONFIG || { initialTransactions: [] };
    let transactions = Array.isArray(config.initialTransactions) ? config.initialTransactions.map((item) => ({ ...item })) : [];
    let selectedTransactionId = null;

    const elements = {
        search: document.getElementById('transactionSearch'),
        provider: document.getElementById('providerFilter'),
        status: document.getElementById('transactionStatus'),
        posStatus: document.getElementById('posStatusFilter'),
        rows: document.getElementById('transactionRows'),
        empty: document.getElementById('transactionEmpty'),
        count: document.getElementById('transactionCount'),
        footer: document.getElementById('transactionFooter'),
        modal: document.getElementById('transactionModal'),
        form: document.getElementById('transactionForm'),
        warning: document.getElementById('transactionFormWarning'),
        overlay: document.getElementById('detailsOverlay'),
        details: document.getElementById('transactionDetails'),
        detailsTitle: document.getElementById('detailsTitle'),
        detailsActions: document.getElementById('transactionDetailsActions'),
        toast: document.getElementById('transactionToast')
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[character]));

    const money = (value) => new Intl.NumberFormat('en-PH', {
        style: 'currency', currency: 'PHP', minimumFractionDigits: 2
    }).format(Number(value) || 0);

    const statusClass = (status) => String(status || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    const initials = (name) => String(name || '?').trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
    const statusPill = (status) => `<span class="status-pill ${statusClass(status)}">${escapeHtml(status)}</span>`;

    function updateMetrics() {
        const counts = transactions.reduce((summary, transaction) => {
            const status = transaction.authorizationStatus;
            summary.total += 1;
            if (Object.prototype.hasOwnProperty.call(summary, status)) summary[status] += 1;
            return summary;
        }, { total: 0, Approved: 0, Pending: 0, Rejected: 0, Expired: 0 });

        document.getElementById('metricTotal').textContent = counts.total;
        document.getElementById('metricApproved').textContent = counts.Approved;
        document.getElementById('metricPending').textContent = counts.Pending;
        document.getElementById('metricRejected').textContent = counts.Rejected;
        document.getElementById('metricExpired').textContent = counts.Expired;
    }

    function getFilteredTransactions() {
        const query = elements.search.value.trim().toLowerCase();
        const provider = elements.provider.value;
        const status = elements.status.value;
        const posStatus = elements.posStatus.value;

        return transactions.filter((transaction) => {
            const searchText = [transaction.id, transaction.patient, transaction.fullName, transaction.membershipNo,
                transaction.provider, transaction.service, transaction.authorizationNo, transaction.staff].join(' ').toLowerCase();
            return (!query || searchText.includes(query))
                && (!provider || transaction.provider === provider)
                && (!status || transaction.authorizationStatus === status)
                && (!posStatus || transaction.posStatus === posStatus);
        });
    }

    function renderRows() {
        const filtered = getFilteredTransactions();
        elements.rows.innerHTML = filtered.map((transaction) => `
            <tr>
                <td><span class="transaction-number">${escapeHtml(transaction.id)}</span></td>
                <td><div class="patient-cell"><span class="patient-initial">${escapeHtml(initials(transaction.patient))}</span><span class="patient-name">${escapeHtml(transaction.patient)}</span></div></td>
                <td><div class="provider-cell"><span class="provider-logo">${escapeHtml(initials(transaction.provider).slice(0, 1))}</span>${escapeHtml(transaction.provider)}</div></td>
                <td><strong>${escapeHtml(transaction.service)}</strong><br><small>${escapeHtml(transaction.doctor || 'Doctor not recorded')}</small></td>
                <td>${escapeHtml(transaction.authorizationNo || 'Not recorded')}</td>
                <td class="money-cell"><strong>${money(transaction.coverageAmount)}</strong></td>
                <td class="money-cell"><strong>${money(transaction.patientAmount)}</strong></td>
                <td>${statusPill(transaction.authorizationStatus)}</td>
                <td>${statusPill(transaction.posStatus || 'Not sent')}</td>
                <td><div class="row-actions"><button type="button" data-view-transaction="${escapeHtml(transaction.id)}" aria-label="View ${escapeHtml(transaction.id)}" title="View details">◉</button></div></td>
            </tr>`).join('');

        elements.empty.hidden = filtered.length > 0;
        elements.count.textContent = `${filtered.length} ${filtered.length === 1 ? 'record' : 'records'}`;
        elements.footer.textContent = filtered.length === transactions.length
            ? `Showing all ${filtered.length} sample records`
            : `Showing ${filtered.length} of ${transactions.length} sample records`;
        updateMetrics();
    }

    function detailItem(label, value) {
        return `<div class="detail-item"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value || 'Not recorded')}</strong></div>`;
    }

    function openDetails(transactionId) {
        const transaction = transactions.find((item) => item.id === transactionId);
        if (!transaction) return;
        selectedTransactionId = transactionId;
        elements.detailsTitle.textContent = transaction.id;
        elements.details.innerHTML = `
            <div class="detail-id"><strong>${escapeHtml(transaction.patient)}</strong>${statusPill(transaction.authorizationStatus)}</div>
            <section class="detail-section"><h3>Patient and HMO membership</h3><div class="detail-grid">
                ${detailItem('Patient full name', transaction.fullName || transaction.patient)}
                ${detailItem('Membership number', transaction.membershipNo)}
                ${detailItem('HMO provider', transaction.provider)}
                ${detailItem('Requested service', transaction.service)}
                ${detailItem('Available doctor', transaction.doctor)}
            </div></section>
            <section class="detail-section"><h3>Authorization</h3><div class="detail-grid">
                ${detailItem('Authorization / LOA no.', transaction.authorizationNo)}
                ${detailItem('Validity period', transaction.validity || `${transaction.validFrom || '—'} to ${transaction.validTo || '—'}`)}
                ${detailItem('Processed by', transaction.staff)}
                <div class="detail-item"><span>POS status</span><strong>${statusPill(transaction.posStatus || 'Not sent')}</strong></div>
            </div></section>
            <section class="detail-section"><h3>Charge breakdown</h3><div class="amount-summary">
                <div class="amount-row"><span>Total service charge</span><strong>${money(transaction.grossAmount)}</strong></div>
                <div class="amount-row"><span>Approved HMO coverage</span><strong>− ${money(transaction.coverageAmount)}</strong></div>
                <div class="amount-row total"><span>Patient co-payment / balance</span><strong>${money(transaction.patientAmount)}</strong></div>
            </div></section>
            <section class="detail-section"><h3>Processing notes</h3><div class="detail-notes">${escapeHtml(transaction.notes || 'No notes recorded.')}</div></section>`;

        const canForward = transaction.authorizationStatus === 'Approved' && transaction.posStatus === 'Not sent';
        elements.detailsActions.innerHTML = `
            <button type="button" class="secondary-btn" data-close-details>Close</button>
            ${canForward ? '<button type="button" class="primary-btn" data-forward-pos>Forward patient balance to POS</button>' : ''}`;
        elements.overlay.hidden = false;
        document.body.classList.add('transaction-modal-open');
    }

    function closeDetails() {
        elements.overlay.hidden = true;
        selectedTransactionId = null;
        document.body.classList.remove('transaction-modal-open');
    }

    function openModal() {
        elements.warning.hidden = true;
        elements.modal.hidden = false;
        document.body.classList.add('transaction-modal-open');
    }

    function closeModal() {
        elements.modal.hidden = true;
        document.body.classList.remove('transaction-modal-open');
    }

    let toastTimer;
    function showToast(message) {
        elements.toast.textContent = message;
        elements.toast.hidden = false;
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => { elements.toast.hidden = true; }, 3600);
    }

    function updatePatientAmount() {
        const gross = Number(elements.form.elements.grossAmount.value) || 0;
        const coverage = Number(elements.form.elements.coverageAmount.value) || 0;
        const patientInput = elements.form.elements.patientAmount;
        patientInput.value = money(Math.max(0, gross - coverage));
        elements.form.elements.coverageAmount.setCustomValidity(coverage > gross ? 'HMO coverage cannot exceed the total service charge.' : '');
    }

    function formatDate(dateString) {
        if (!dateString) return 'Not recorded';
        const date = new Date(`${dateString}T00:00:00`);
        return Number.isNaN(date.getTime()) ? dateString : new Intl.DateTimeFormat('en-PH', { year: 'numeric', month: 'short', day: '2-digit' }).format(date);
    }

    function createTransaction(formData) {
        const grossAmount = Number(formData.get('grossAmount')) || 0;
        const coverageAmount = Number(formData.get('coverageAmount')) || 0;
        const validFrom = String(formData.get('validFrom') || '');
        const validTo = String(formData.get('validTo') || '');
        const authorizationStatus = String(formData.get('authorizationStatus') || 'Pending');

        if (coverageAmount > grossAmount) {
            elements.warning.textContent = 'Approved HMO coverage cannot be greater than the total service charge.';
            elements.warning.hidden = false;
            elements.form.elements.coverageAmount.focus();
            return;
        }
        if (validFrom && validTo && validTo < validFrom) {
            elements.warning.textContent = 'The authorization end date must be on or after the start date.';
            elements.warning.hidden = false;
            elements.form.elements.validTo.focus();
            return;
        }
        if (authorizationStatus !== 'Approved' && coverageAmount > 0) {
            elements.warning.textContent = 'Record HMO coverage only after the authorization has been approved. Set coverage to ₱0.00 while pending or rejected.';
            elements.warning.hidden = false;
            elements.form.elements.coverageAmount.focus();
            return;
        }

        const nextNumber = Math.max(125, ...transactions.map((item) => Number(String(item.id).replace(/\D/g, '')) || 0)) + 1;
        const patient = String(formData.get('patient')).trim();
        const transaction = {
            id: `HMO-${String(nextNumber).padStart(6, '0')}`,
            patient,
            fullName: patient,
            membershipNo: String(formData.get('membershipNo')).trim(),
            provider: String(formData.get('provider')),
            service: String(formData.get('service')),
            doctor: String(formData.get('doctor')),
            authorizationNo: String(formData.get('authorizationNo')).trim(),
            validFrom,
            validTo,
            validity: `${formatDate(validFrom)} – ${formatDate(validTo)}`,
            authorizationStatus,
            posStatus: 'Not sent',
            staff: String(formData.get('staff') || 'Admin'),
            grossAmount,
            coverageAmount,
            patientAmount: Math.max(0, grossAmount - coverageAmount),
            notes: String(formData.get('notes') || '').trim() || 'No notes recorded.'
        };

        transactions.unshift(transaction);
        elements.search.value = '';
        elements.provider.value = '';
        elements.status.value = '';
        elements.posStatus.value = '';
        renderRows();
        closeModal();
        elements.form.reset();
        elements.form.elements.grossAmount.value = '0.00';
        elements.form.elements.coverageAmount.value = '0.00';
        updatePatientAmount();
        showToast(`${transaction.id} added to this preview only. It has not been saved to the database.`);
    }

    document.addEventListener('click', (event) => {
        const viewButton = event.target.closest('[data-view-transaction]');
        if (viewButton) openDetails(viewButton.dataset.viewTransaction);
        if (event.target.closest('[data-open-transaction]')) openModal();
        if (event.target.closest('[data-close-transaction]')) closeModal();
        if (event.target.closest('[data-close-details]')) closeDetails();
        if (event.target === elements.modal) closeModal();
        if (event.target === elements.overlay) closeDetails();

        if (event.target.closest('[data-forward-pos]')) {
            const transaction = transactions.find((item) => item.id === selectedTransactionId);
            if (!transaction || transaction.authorizationStatus !== 'Approved' || transaction.posStatus !== 'Not sent') return;
            transaction.posStatus = 'Sent to POS';
            renderRows();
            openDetails(transaction.id);
            showToast('Preview status updated to Sent to POS. No cashier/POS record was created.');
        }
    });

    [elements.search, elements.provider, elements.status, elements.posStatus].forEach((element) => {
        element.addEventListener(element.tagName === 'INPUT' ? 'input' : 'change', renderRows);
    });

    document.getElementById('clearFilters').addEventListener('click', () => {
        elements.search.value = '';
        elements.provider.value = '';
        elements.status.value = '';
        elements.posStatus.value = '';
        renderRows();
    });

    elements.form.elements.grossAmount.addEventListener('input', updatePatientAmount);
    elements.form.elements.coverageAmount.addEventListener('input', updatePatientAmount);
    elements.form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!elements.form.reportValidity()) return;
        createTransaction(new FormData(elements.form));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal();
            closeDetails();
        }
    });

    renderRows();
    updatePatientAmount();
})();
