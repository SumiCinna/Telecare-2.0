<?php
/**
 * TELE-CARE Admin — HMO Transactions
 *
 * This is a frontend prototype using the supplied sample transactions.
 * It does not write to the database or create a real POS transaction.
 * Connect the form and POS handoff to the approved database workflow before production.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$activeNav = 'hmo-transactions';
$adminName = trim((string) ($_SESSION['admin_name'] ?? 'Admin'));

/** Escape values before rendering them into HTML. */
function hmo_tx_escape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sample data supplied for the UI preview.
 * Amounts are intentionally marked as illustrative because actual service rates,
 * coverage limits, and patient co-pay must come from the clinic's configured data.
 */
$transactions = [
    [
        'id' => 'HMO-000125', 'patient' => 'Cruz, Juan D.', 'fullName' => 'Juan Dela Cruz',
        'membershipNo' => 'MC-1039482', 'provider' => 'Maxicare', 'service' => 'Teleconsultation',
        'doctor' => 'Dr. Maria Santos',
        'authorizationNo' => 'LOA-2025-00123', 'validity' => 'Jan 10, 2025 – Jan 24, 2025',
        'validFrom' => '2025-01-10', 'validTo' => '2025-01-24', 'authorizationStatus' => 'Approved',
        'posStatus' => 'Not sent', 'staff' => 'Maria S.', 'grossAmount' => 800.00,
        'coverageAmount' => 800.00, 'patientAmount' => 0.00,
        'notes' => 'Approved for one video teleconsultation with Dr. Maria Santos. Follow-up is subject to reauthorization.',
    ],
    [
        'id' => 'HMO-000124', 'patient' => 'Reyes, Ana L.', 'fullName' => 'Ana Lucia Reyes',
        'membershipNo' => 'IC-884210', 'provider' => 'Intellicare', 'service' => 'Medical Certificate',
        'doctor' => 'Dr. Carlo Reyes',
        'authorizationNo' => 'LOG-45891', 'validity' => 'Jan 08, 2025 – Feb 07, 2025',
        'validFrom' => '2025-01-08', 'validTo' => '2025-02-07', 'authorizationStatus' => 'Pending',
        'posStatus' => 'Not sent', 'staff' => 'Carlos P.', 'grossAmount' => 1500.00,
        'coverageAmount' => 0.00, 'patientAmount' => 1500.00,
        'notes' => 'Teleconsultation for medical certificate assessment is awaiting member eligibility confirmation.',
    ],
    [
        'id' => 'HMO-000123', 'patient' => 'Santos, Maria K.', 'fullName' => 'Maria Katrina Santos',
        'membershipNo' => 'MD-009172', 'provider' => 'Medicare', 'service' => 'Prescription Renewal',
        'doctor' => 'Dr. Ana Cruz',
        'authorizationNo' => 'LOA-77821', 'validity' => 'Jan 05, 2025 – Jan 20, 2025',
        'validFrom' => '2025-01-05', 'validTo' => '2025-01-20', 'authorizationStatus' => 'Rejected',
        'posStatus' => 'Not sent', 'staff' => 'Ramon L.', 'grossAmount' => 2200.00,
        'coverageAmount' => 0.00, 'patientAmount' => 2200.00,
        'notes' => 'Teleconsultation for prescription renewal is not covered under the member plan.',
    ],
    [
        'id' => 'HMO-000122', 'patient' => 'Dela Cruz, Carlo', 'fullName' => 'Carlo Dela Cruz',
        'membershipNo' => 'PC-551204', 'provider' => 'PhilCare', 'service' => 'Follow-up Teleconsultation',
        'doctor' => 'Dr. Kevin Lim',
        'authorizationNo' => 'LOA-55120', 'validity' => 'Jan 03, 2025 – Jan 17, 2025',
        'validFrom' => '2025-01-03', 'validTo' => '2025-01-17', 'authorizationStatus' => 'Approved',
        'posStatus' => 'Sent to POS', 'staff' => 'Ana R.', 'grossAmount' => 600.00,
        'coverageAmount' => 500.00, 'patientAmount' => 100.00,
        'notes' => 'Approved for a follow-up video teleconsultation after review of the submitted clinical notes.',
    ],
    [
        'id' => 'HMO-000121', 'patient' => 'Lim, Patricia', 'fullName' => 'Patricia Lim',
        'membershipNo' => 'AV-990031', 'provider' => 'Avega', 'service' => 'Fit-to-Work Certificate',
        'doctor' => 'Dr. Patricia Garcia',
        'authorizationNo' => 'LOA-99031', 'validity' => 'Dec 28, 2024 – Jan 28, 2025',
        'validFrom' => '2024-12-28', 'validTo' => '2025-01-28', 'authorizationStatus' => 'Expired',
        'posStatus' => 'Not sent', 'staff' => 'Marisol K.', 'grossAmount' => 900.00,
        'coverageAmount' => 0.00, 'patientAmount' => 900.00,
        'notes' => 'Authorization for the fit-to-work teleconsultation and certificate assessment has expired.',
    ],
    [
        'id' => 'HMO-000120', 'patient' => 'Garcia, Eric', 'fullName' => 'Eric Garcia',
        'membershipNo' => 'ET-663210', 'provider' => 'Etiqa', 'service' => 'Teleconsultation',
        'doctor' => 'Dr. Maria Santos',
        'authorizationNo' => 'LOA-66321', 'validity' => 'Jan 02, 2025 – Jan 16, 2025',
        'validFrom' => '2025-01-02', 'validTo' => '2025-01-16', 'authorizationStatus' => 'Approved',
        'posStatus' => 'Paid', 'staff' => 'Maria S.', 'grossAmount' => 800.00,
        'coverageAmount' => 800.00, 'patientAmount' => 0.00,
        'notes' => 'Approved and paid for one video teleconsultation with Dr. Maria Santos.',
    ],
    [
        'id' => 'HMO-000119', 'patient' => 'Tan, Michelle', 'fullName' => 'Michelle Tan',
        'membershipNo' => 'MC-102340', 'provider' => 'Maxicare', 'service' => 'Medical Certificate',
        'doctor' => 'Dr. Ana Cruz',
        'authorizationNo' => 'LOA-10234', 'validity' => 'Jan 06, 2025 – Jan 21, 2025',
        'validFrom' => '2025-01-06', 'validTo' => '2025-01-21', 'authorizationStatus' => 'Pending',
        'posStatus' => 'Not sent', 'staff' => 'John R.', 'grossAmount' => 1200.00,
        'coverageAmount' => 0.00, 'patientAmount' => 1200.00,
        'notes' => 'Medical certificate teleconsultation is pending an additional supporting document.',
    ],
    [
        'id' => 'HMO-000118', 'patient' => 'Villanueva, Kevin', 'fullName' => 'Kevin Villanueva',
        'membershipNo' => 'IC-334550', 'provider' => 'Intellicare', 'service' => 'Follow-up Teleconsultation',
        'doctor' => 'Dr. Carlo Reyes',
        'authorizationNo' => 'LOA-33455', 'validity' => 'Jan 01, 2025 – Jan 31, 2025',
        'validFrom' => '2025-01-01', 'validTo' => '2025-01-31', 'authorizationStatus' => 'Approved',
        'posStatus' => 'Not sent', 'staff' => 'Carlos P.', 'grossAmount' => 2200.00,
        'coverageAmount' => 2000.00, 'patientAmount' => 200.00,
        'notes' => 'Approved for a follow-up teleconsultation after eligibility verification.',
    ],
    [
        'id' => 'HMO-000117', 'patient' => 'Ramos, Daniel', 'fullName' => 'Daniel Ramos',
        'membershipNo' => 'PC-772100', 'provider' => 'Pacific Cross', 'service' => 'Teleconsultation',
        'doctor' => 'Dr. Kevin Lim',
        'authorizationNo' => 'LOA-77210', 'validity' => 'Dec 27, 2024 – Jan 27, 2025',
        'validFrom' => '2024-12-27', 'validTo' => '2025-01-27', 'authorizationStatus' => 'Rejected',
        'posStatus' => 'Not sent', 'staff' => 'Ana R.', 'grossAmount' => 800.00,
        'coverageAmount' => 0.00, 'patientAmount' => 800.00,
        'notes' => 'Teleconsultation request rejected because the member limit for this benefit has been reached.',
    ],
    [
        'id' => 'HMO-000116', 'patient' => 'Morales, Bianca', 'fullName' => 'Bianca Morales',
        'membershipNo' => 'MD-881230', 'provider' => 'Medicare', 'service' => 'Prescription Renewal',
        'doctor' => 'Dr. Patricia Garcia',
        'authorizationNo' => 'LOG-88123', 'validity' => 'Jan 04, 2025 – Feb 04, 2025',
        'validFrom' => '2025-01-04', 'validTo' => '2025-02-04', 'authorizationStatus' => 'Pending',
        'posStatus' => 'Not sent', 'staff' => 'Ramon L.', 'grossAmount' => 650.00,
        'coverageAmount' => 0.00, 'patientAmount' => 650.00,
        'notes' => 'Prescription renewal teleconsultation is pending review by the HMO coordinator.',
    ],
];

$providerNames = ['Maxicare', 'Intellicare', 'Medicare', 'PhilCare', 'Avega', 'Etiqa', 'Pacific Cross'];
$doctorNames = ['Dr. Maria Santos', 'Dr. Carlo Reyes', 'Dr. Ana Cruz', 'Dr. Kevin Lim', 'Dr. Patricia Garcia'];
$serviceNames = ['Teleconsultation', 'Follow-up Teleconsultation', 'Medical Certificate', 'Fit-to-Work Certificate', 'Prescription Renewal'];
$transactionJson = json_encode($transactions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HMO Transactions | TELE-CARE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/hmo.css">
    <link rel="stylesheet" href="assets/hmo-transactions.css">
    <script src="assets/hmo-transactions.js" defer></script>
</head>
<body>
<?php include 'sidebar.php'; ?>

<main class="hmo-main">
    <header class="hmo-topbar">
        <button class="mobile-menu" type="button" aria-label="Toggle navigation">☰</button>
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <span>Admin</span><b class="breadcrumb-separator">›</b>
            <span>HMO Management</span><b class="breadcrumb-separator">›</b>
            <strong>Transactions &amp; Authorization</strong>
        </nav>
        <div class="topbar-user">
            <span class="user-avatar" aria-hidden="true"><?= hmo_tx_escape(mb_substr($adminName, 0, 1)) ?></span>
            <span><b><?= hmo_tx_escape($adminName) ?></b><small>Administrator</small></span>
        </div>
    </header>

    <section class="hmo-content transaction-content">
        <div class="page-heading transaction-heading">
            <div>
                <div class="eyebrow">HMO MANAGEMENT</div>
                <h1>HMO Transactions &amp; Authorization Monitoring</h1>
                <p>Review member eligibility, record approved HMO coverage, and forward patient charges to POS.</p>
            </div>
            <button class="primary-btn" type="button" data-open-transaction>＋ New HMO Transaction</button>
        </div>

        <div class="transaction-demo-notice" role="note">
            <span class="notice-icon">i</span>
            <span><strong>Prototype data:</strong> transactions and amounts on this page are sample records. Creating or forwarding a transaction here does not save to the database or cashier system.</span>
        </div>

        <section class="transaction-metrics" aria-label="Transaction summary">
            <article class="metric-card blue"><span class="metric-icon">▤</span><div><small>Total shown</small><strong id="metricTotal">10</strong></div></article>
            <article class="metric-card green"><span class="metric-icon">✓</span><div><small>Approved</small><strong id="metricApproved">4</strong></div></article>
            <article class="metric-card amber"><span class="metric-icon">◷</span><div><small>Pending</small><strong id="metricPending">3</strong></div></article>
            <article class="metric-card red"><span class="metric-icon">×</span><div><small>Rejected</small><strong id="metricRejected">2</strong></div></article>
            <article class="metric-card violet"><span class="metric-icon">▣</span><div><small>Expired</small><strong id="metricExpired">1</strong></div></article>
        </section>

        <section class="panel transaction-table-panel">
            <div class="transaction-panel-heading">
                <div><h2>Transaction records</h2><p>Search, filter, and open a transaction to review its coverage and patient balance.</p></div>
                <span class="record-count" id="transactionCount">10 records</span>
            </div>

            <div class="transaction-toolbar">
                <label class="search-box" for="transactionSearch"><span aria-hidden="true">⌕</span><input id="transactionSearch" type="search" placeholder="Search patient, transaction, membership, or LOA..."></label>
                <select id="providerFilter" aria-label="Filter by HMO provider">
                    <option value="">All HMO providers</option>
                    <?php foreach ($providerNames as $provider): ?><option value="<?= hmo_tx_escape($provider) ?>"><?= hmo_tx_escape($provider) ?></option><?php endforeach; ?>
                </select>
                <select id="transactionStatus" aria-label="Filter by authorization status">
                    <option value="">All authorization statuses</option>
                    <option>Approved</option><option>Pending</option><option>Rejected</option><option>Expired</option>
                </select>
                <select id="posStatusFilter" aria-label="Filter by POS status">
                    <option value="">All POS statuses</option><option>Not sent</option><option>Sent to POS</option><option>Paid</option>
                </select>
                <button class="secondary-btn" id="clearFilters" type="button">Clear filters</button>
            </div>

            <div class="table-scroll transaction-table-scroll">
                <table class="transaction-table">
                    <thead><tr>
                        <th>Transaction No.</th><th>Patient</th><th>HMO Provider</th><th>Teleconsultation service</th>
                        <th>Authorization / LOA</th><th>HMO Coverage</th><th>Patient Pays</th>
                        <th>Authorization</th><th>POS Status</th><th>Actions</th>
                    </tr></thead>
                    <tbody id="transactionRows"></tbody>
                </table>
                <div class="transaction-empty" id="transactionEmpty" hidden>
                    <span>⌕</span><strong>No transactions found</strong><p>Try a different search term or clear your filters.</p>
                </div>
            </div>
            <div class="transaction-table-footer"><span id="transactionFooter">Showing 10 sample records</span><span>Amounts are illustrative until connected to clinic pricing and HMO plan rules.</span></div>
        </section>
    </section>
</main>

<!-- Transaction details: opens only when a row is selected; no permanently empty side panel. -->
<div class="transaction-overlay" id="detailsOverlay" hidden>
    <section class="transaction-drawer" role="dialog" aria-modal="true" aria-labelledby="detailsTitle">
        <header class="drawer-header"><div><span class="eyebrow">TRANSACTION DETAILS</span><h2 id="detailsTitle">HMO transaction</h2></div><button type="button" class="icon-button" data-close-details aria-label="Close details">×</button></header>
        <div id="transactionDetails" class="drawer-body"></div>
        <footer class="drawer-footer" id="transactionDetailsActions"></footer>
    </section>
</div>

<!-- New transaction form. Demo only; no server-side persistence is performed. -->
<div class="transaction-overlay" id="transactionModal" hidden>
    <section class="transaction-modal" role="dialog" aria-modal="true" aria-labelledby="newTransactionTitle">
        <header class="drawer-header"><div><span class="eyebrow">HMO MANAGEMENT</span><h2 id="newTransactionTitle">Create HMO transaction</h2><p>Enter verified membership and authorization details.</p></div><button type="button" class="icon-button" data-close-transaction aria-label="Close form">×</button></header>
        <form id="transactionForm">
            <div class="transaction-form-body">
                <div class="form-section-title">Patient and membership</div>
                <div class="transaction-form-grid">
                    <label>Patient name <em>*</em><input name="patient" required maxlength="120" placeholder="Enter patient full name"></label>
                    <label>Membership number <em>*</em><input name="membershipNo" required maxlength="80" placeholder="Enter HMO membership number"></label>
                    <label>HMO provider <em>*</em><select name="provider" required><option value="">Select provider</option><?php foreach ($providerNames as $provider): ?><option><?= hmo_tx_escape($provider) ?></option><?php endforeach; ?></select></label>
                    <label>Available doctor <em>*</em><select name="doctor" required><option value="">Select doctor</option><?php foreach ($doctorNames as $doctor): ?><option><?= hmo_tx_escape($doctor) ?></option><?php endforeach; ?></select></label>
                    <label>Requested service <em>*</em><select name="service" required><option value="">Select service</option><?php foreach ($serviceNames as $service): ?><option><?= hmo_tx_escape($service) ?></option><?php endforeach; ?></select></label>
                </div>

                <div class="form-section-title">Authorization / Letter of Authorization (LOA)</div>
                <div class="transaction-form-grid">
                    <label>Authorization / LOA number <em>*</em><input name="authorizationNo" required maxlength="80" placeholder="Enter reference number"></label>
                    <label>Authorization status <em>*</em><select name="authorizationStatus" required><option>Pending</option><option>Approved</option><option>Rejected</option></select></label>
                    <label>Valid from <em>*</em><input name="validFrom" type="date" required></label>
                    <label>Valid until <em>*</em><input name="validTo" type="date" required></label>
                </div>

                <div class="form-section-title">Charges and HMO coverage</div>
                <div class="transaction-form-grid transaction-amount-grid">
                    <label>Total service charge (₱) <em>*</em><input name="grossAmount" type="number" min="0" step="0.01" required value="0.00"></label>
                    <label>Approved HMO coverage (₱) <em>*</em><input name="coverageAmount" type="number" min="0" step="0.01" required value="0.00"></label>
                    <label>Patient co-payment / balance (₱)<input name="patientAmount" type="text" readonly value="₱0.00"><small>Calculated as total charge minus HMO coverage.</small></label>
                    <label>Staff / processed by<input name="staff" maxlength="100" value="<?= hmo_tx_escape($adminName) ?>" readonly></label>
                </div>
                <label class="transaction-notes-label">Notes <textarea name="notes" rows="3" maxlength="500" placeholder="Eligibility check, exclusions, or authorization notes"></textarea></label>
                <p class="form-warning" id="transactionFormWarning" hidden></p>
            </div>
            <footer class="modal-actions"><button type="button" class="secondary-btn" data-close-transaction>Cancel</button><button type="submit" class="primary-btn">Create transaction preview</button></footer>
        </form>
    </section>
</div>

<div class="transaction-toast" id="transactionToast" role="status" aria-live="polite" hidden></div>
<script>
window.HMO_TRANSACTION_CONFIG = {
    initialTransactions: <?= $transactionJson ?: '[]' ?>,
    currency: 'PHP'
};
</script>
</body>
</html>
