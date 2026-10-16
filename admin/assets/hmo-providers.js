/* HMO Providers page interactions. Provider and coverage data are rendered by PHP. */
const ALL_PROVIDERS = window.HMO_PROVIDER_DATA || [];
const COVERAGE_BY_PROVIDER = window.HMO_COVERAGE_DATA || {};
const OPEN_COVERAGE_FOR = window.HMO_OPEN_COVERAGE_FOR || null;

const tableBody = document.getElementById('providersTableBody');
const detailPanel = document.getElementById('providerDetail');
let selectedProviderId = ALL_PROVIDERS.length ? Number(ALL_PROVIDERS[0].id) : null;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}

function closeModal(modal) {
    modal?.classList.remove('open');
}

document.querySelectorAll('[data-open-modal]').forEach((button) => {
    button.addEventListener('click', () => openModal(button.dataset.openModal));
});

document.querySelectorAll('[data-close-modal]').forEach((button) => {
    button.addEventListener('click', () => closeModal(button.closest('.modal-backdrop')));
});

document.querySelectorAll('.modal-backdrop').forEach((modal) => {
    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeModal(modal);
    });
});

function providerStatusBadges(provider) {
    const statusClass = provider.status === 'Active' ? 'active' :
        provider.status === 'Inactive' ? 'inactive' : 'archived';
    let badges = `<span class="status-pill ${statusClass}">${escapeHtml(provider.status)}</span>`;

    if (String(provider.on_hold) === '1') {
        badges += ` <span class="status-pill on-hold" title="${escapeHtml(provider.hold_reason || '')}">On hold</span>`;
    }
    return badges;
}

function updateMetrics() {
    const metrics = {
        totalCount: ALL_PROVIDERS.length,
        activeCount: ALL_PROVIDERS.filter((provider) => provider.status === 'Active').length,
        inactiveCount: ALL_PROVIDERS.filter((provider) => provider.status === 'Inactive').length,
        archivedCount: ALL_PROVIDERS.filter((provider) => provider.status === 'Archived').length,
    };

    Object.entries(metrics).forEach(([id, value]) => {
        document.getElementById(id).textContent = value;
    });
}

function getFilteredProviders() {
    const query = document.getElementById('providerSearch').value.toLowerCase().trim();
    const status = document.getElementById('statusFilter').value;
    const holdFilter = document.getElementById('holdFilter').value;

    return ALL_PROVIDERS.filter((provider) => {
        const searchableText = [
            provider.name,
            provider.license_no,
            provider.contact_person,
            provider.phone,
            provider.email,
        ].join(' ').toLowerCase();
        const matchesSearch = searchableText.includes(query);
        const matchesStatus = !status || provider.status === status;
        const isOnHold = String(provider.on_hold) === '1';
        const matchesHold = !holdFilter ||
            (holdFilter === 'hold' && isOnHold) ||
            (holdFilter === 'clear' && !isOnHold);

        return matchesSearch && matchesStatus && matchesHold;
    });
}

function renderProviderRows() {
    const providers = getFilteredProviders();
    document.getElementById('emptyState').style.display = providers.length ? 'none' : 'block';

    tableBody.innerHTML = providers.map((provider) => {
        const coverage = COVERAGE_BY_PROVIDER[provider.id] || [];
        const isSelected = Number(provider.id) === selectedProviderId;
        const initials = escapeHtml((provider.name || '?').slice(0, 1).toUpperCase());

        return `
            <tr data-id="${Number(provider.id)}" class="${isSelected ? 'selected' : ''}" onclick="selectProvider(${Number(provider.id)})">
                <td><div class="provider-name-cell"><span class="provider-logo">${initials}</span>${escapeHtml(provider.name)}</div></td>
                <td>${escapeHtml(provider.license_no || '—')}</td>
                <td>${escapeHtml(provider.contact_person || '—')}</td>
                <td>${escapeHtml(provider.phone || '—')}</td>
                <td>${coverage.length ? `<span class="status-pill active">${coverage.length} item${coverage.length === 1 ? '' : 's'}</span>` : '—'}</td>
                <td>${providerStatusBadges(provider)}</td>
                <td><div class="row-actions">
                    <button type="button" title="Edit provider" aria-label="Edit provider" onclick="event.stopPropagation(); openEditModal(${Number(provider.id)})">✎</button>
                    <button type="button" title="Manage coverage" aria-label="Manage coverage" onclick="event.stopPropagation(); openCoverageModal(${Number(provider.id)})">▤</button>
                </div></td>
            </tr>`;
    }).join('');

    document.getElementById('rowCount').textContent = `Showing ${providers.length} of ${ALL_PROVIDERS.length} providers`;
    updateMetrics();
}

function selectProvider(id) {
    selectedProviderId = Number(id);
    renderProviderRows();
    renderProviderDetails(id);
}

function renderProviderDetails(id) {
    const provider = ALL_PROVIDERS.find((item) => Number(item.id) === Number(id));
    if (!provider) {
        detailPanel.innerHTML = '<div class="detail-empty">Select a provider to view its details.</div>';
        return;
    }

    const coverage = COVERAGE_BY_PROVIDER[provider.id] || [];
    const validFrom = provider.accreditation_start || provider.start_date || 'Not recorded';
    const validTo = provider.accreditation_end || provider.end_date || 'Not recorded';
    const providerName = escapeHtml(provider.name);
    const holdAction = String(provider.on_hold) === '1'
        ? `<a class="secondary-btn" href="?release_provider=${Number(provider.id)}" onclick="return confirm('Release hold for this provider?')">Release Hold</a>`
        : `<button class="secondary-btn" type="button" onclick="holdProvider(${Number(provider.id)})">Put on Hold</button>`;
    const statusAction = provider.status === 'Active'
        ? `<a class="danger-btn" href="?archive_provider=${Number(provider.id)}" onclick="return confirm('Archive this provider?')">Archive</a>`
        : `<a class="secondary-btn" href="?restore_provider=${Number(provider.id)}" onclick="return confirm('Restore this provider?')">Restore</a>`;
    const notice = String(provider.on_hold) === '1'
        ? 'This provider is on hold and should not be accepted for new bookings.'
        : provider.status === 'Active'
            ? 'This provider is active and can be selected for eligible transactions.'
            : 'This provider is archived and should be restored before reuse.';

    detailPanel.innerHTML = `
        <div class="detail-head">
            <span class="provider-logo large">${escapeHtml((provider.name || '?').slice(0, 1).toUpperCase())}</span>
            <div><h2>${providerName}</h2><small>License: ${escapeHtml(provider.license_no || 'Not recorded')}</small></div>
            <button class="close-detail" type="button" aria-label="Clear provider selection" onclick="clearProviderDetails()">×</button>
        </div>
        <div class="detail-tabs">
            <button class="active" type="button">Details</button>
            <button type="button" onclick="openCoverageModal(${Number(provider.id)})">Service Coverage (${coverage.length})</button>
            <button type="button" onclick="showProviderHistoryNotice()">History</button>
        </div>
        <div class="detail-body">
            <h3>Provider Information</h3>
            <dl>
                <dt>Provider Name</dt><dd>${providerName}</dd>
                <dt>License Number</dt><dd>${escapeHtml(provider.license_no || '—')}</dd>
                <dt>Contact Person</dt><dd>${escapeHtml(provider.contact_person || '—')}</dd>
                <dt>Contact Number</dt><dd>${escapeHtml(provider.phone || '—')}</dd>
                <dt>Email Address</dt><dd>${escapeHtml(provider.email || '—')}</dd>
                <dt>Coverage Items</dt><dd>${coverage.length}</dd>
                <dt>Status</dt><dd>${providerStatusBadges(provider)}</dd>
                <dt>Hold Reason</dt><dd>${escapeHtml(String(provider.on_hold) === '1' ? (provider.hold_reason || 'No reason recorded') : 'Not on hold')}</dd>
                <dt>Accreditation</dt><dd>${escapeHtml(validFrom)} – ${escapeHtml(validTo)}</dd>
            </dl>
            <div class="detail-note">ⓘ ${escapeHtml(notice)}</div>
        </div>
        <div class="detail-actions">
            <button class="secondary-btn" type="button" onclick="openEditModal(${Number(provider.id)})">✎ Edit</button>
            <button class="secondary-btn" type="button" onclick="openCoverageModal(${Number(provider.id)})">▤ Coverage</button>
            ${holdAction}${statusAction}
        </div>`;
}

function clearProviderDetails() {
    detailPanel.innerHTML = '<div class="detail-empty">Select a provider to view its details.</div>';
}

function showProviderHistoryNotice() {
    window.alert('Provider configuration history is not available in the existing provider schema. Audit entries are recorded separately.');
}

function openEditModal(id) {
    const provider = ALL_PROVIDERS.find((item) => Number(item.id) === Number(id));
    if (!provider) return;

    document.getElementById('edit-provider-id').value = provider.id;
    document.getElementById('edit-provider-name').value = provider.name || '';
    document.getElementById('edit-provider-license').value = provider.license_no || '';
    document.getElementById('edit-provider-contact').value = provider.contact_person || '';
    document.getElementById('edit-provider-phone').value = provider.phone || '';
    document.getElementById('edit-provider-email').value = provider.email || '';
    document.getElementById('edit-provider-status').value = provider.status || 'Active';
    openModal('modal-edit-provider');
}

function renderCoverageList(providerId) {
    const items = COVERAGE_BY_PROVIDER[providerId] || [];
    const coverageList = document.getElementById('coverage-list');

    if (!items.length) {
        coverageList.innerHTML = '<div class="detail-empty">No coverage items yet. Add one below.</div>';
        return;
    }

    coverageList.innerHTML = items.map((item) => {
        const value = item.coverage_type === 'Percentage'
            ? `${Number(item.coverage_value)}%`
            : `₱${Number(item.coverage_value).toFixed(2)}`;

        return `
            <div class="coverage-item">
                <span><strong>${escapeHtml(item.description)}</strong><small>${escapeHtml(item.notes || '')}</small></span>
                <span>${value}</span>
                <a class="danger-btn" href="?remove_coverage=${Number(item.id)}&provider_id=${Number(providerId)}" onclick="return confirm('Remove this coverage item?')">Remove</a>
            </div>`;
    }).join('');
}

function openCoverageModal(id) {
    const provider = ALL_PROVIDERS.find((item) => Number(item.id) === Number(id));
    if (!provider) return;

    document.getElementById('coverage-provider-id').value = provider.id;
    document.getElementById('coverage-provider-name').textContent = `Coverage items for ${provider.name}`;
    renderCoverageList(provider.id);
    openModal('modal-coverage');
}

function holdProvider(id) {
    const reason = window.prompt('Enter the reason for putting this provider on hold (e.g. unpaid bills):');
    if (reason === null) return;
    if (!reason.trim()) {
        window.alert('A reason is required.');
        return;
    }

    document.getElementById('hold-provider-id').value = id;
    document.getElementById('hold-provider-reason').value = reason;
    document.getElementById('hold-form').submit();
}

document.getElementById('providerSearch').addEventListener('input', renderProviderRows);
document.getElementById('statusFilter').addEventListener('change', renderProviderRows);
document.getElementById('holdFilter').addEventListener('change', renderProviderRows);
document.querySelector('.mobile-menu')?.addEventListener('click', () => {
    document.querySelector('.sidebar')?.classList.toggle('mobile-open');
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.open').forEach(closeModal);
    }
});

renderProviderRows();
if (selectedProviderId !== null) renderProviderDetails(selectedProviderId);
if (OPEN_COVERAGE_FOR) openCoverageModal(OPEN_COVERAGE_FOR);
setTimeout(() => document.querySelectorAll('.toast').forEach((toast) => toast.remove()), 4500);
