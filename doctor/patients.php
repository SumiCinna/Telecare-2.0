<?php
require_once 'includes/auth.php';

$search = trim($_GET['q'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$gender_filter = trim($_GET['gender'] ?? '');

$sql = "
    SELECT p.*,
        (SELECT COUNT(*) FROM appointments WHERE patient_id=p.id AND doctor_id=$doctor_id) AS total_visits,
        (SELECT appointment_date FROM appointments WHERE patient_id=p.id AND doctor_id=$doctor_id ORDER BY appointment_date DESC LIMIT 1) AS last_visit,
        (SELECT COUNT(*) FROM lab_results WHERE patient_id=p.id AND doc_type='lab_result') AS lab_count,
        (SELECT COUNT(*) FROM lab_results WHERE patient_id=p.id AND doc_type='prescription') AS rx_count,
        (SELECT COUNT(*) FROM lab_results WHERE patient_id=p.id AND doc_type='lab_request') AS lab_req_count,
        (SELECT COUNT(*) FROM lab_results WHERE patient_id=p.id AND doc_type='med_cert') AS med_cert_count
    FROM patients p
    WHERE EXISTS (
        SELECT 1 FROM appointments ap
        WHERE ap.patient_id=p.id AND ap.doctor_id=$doctor_id
    )
";

$params = [];
$types = '';

if ($search !== '') {
    $sql .= " AND (p.full_name LIKE ? OR p.email LIKE ? OR CAST(p.id AS CHAR) LIKE ?)";
    $like = "%{$search}%";
    $params = [$like, $like, $like];
    $types = 'sss';
}

if ($gender_filter !== '') {
    $sql .= " AND p.gender=?";
    $params[] = $gender_filter;
    $types .= 's';
}

$sql .= " ORDER BY p.full_name ASC";

$stmt = $conn->prepare($sql);

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$patients = $stmt->get_result();

$page_title = 'Patients — TELE-CARE';
$page_title_short = 'Patients';
$active_nav = 'patients';

require_once 'includes/header.php';

function patientInitials($name) {
    $parts = preg_split('/\s+/', trim($name));
    return strtoupper(
        substr($parts[0] ?? 'P', 0, 1) .
        substr(end($parts) ?: '', 0, 1)
    );
}

function patientAge($dob) {
    if (!$dob) return '—';

    try {
        return date_diff(
            new DateTime($dob),
            new DateTime()
        )->y;
    } catch (Throwable $e) {
        return '—';
    }
}

function patientStatus($lastVisit) {
    if (!$lastVisit) return 'Active';

    return strtotime($lastVisit) >= strtotime('-180 days')
        ? 'Active'
        : 'Inactive';
}
?>

<style>
.patient-page{width:100%;max-width:1500px;margin:auto;padding:20px 24px;box-sizing:border-box}
.patient-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px}
.patient-heading h1{margin:0;color:var(--neutral-900);font-size:1.55rem}
.patient-heading p{margin:5px 0 0;color:var(--neutral-500);font-size:.75rem}
.heading-actions{display:flex;gap:8px}
.top-btn{height:38px;padding:0 13px;border:1px solid var(--border-color);border-radius:7px;background:#fff;color:var(--neutral-800);font-size:.65rem;font-weight:800;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.top-btn.primary{background:var(--primary);border-color:var(--primary);color:#fff}
.filters{display:grid;grid-template-columns:minmax(260px,1fr) 150px 150px 42px;gap:9px;padding:11px;margin-bottom:18px;border:1px solid var(--border-color);border-radius:10px;background:#fff}
.control{width:100%;height:38px;box-sizing:border-box;border:1px solid var(--border-color);border-radius:7px;background:#fff;color:var(--neutral-800);font:inherit;font-size:.67rem;padding:0 11px}
.search{position:relative}
.search svg{position:absolute;left:11px;top:11px;width:16px;height:16px;color:var(--neutral-500)}
.search .control{padding-left:34px}
.filter-btn{display:grid;place-items:center;border:1px solid var(--border-color);border-radius:7px;background:#fff;color:var(--neutral-700);cursor:pointer}
.patient-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.patient-card{padding:13px;border:1px solid var(--border-color);border-radius:9px;background:#fff;box-shadow:var(--shadow-sm)}
.patient-card.inactive{opacity:.65}
.patient-top{display:flex;gap:9px;align-items:flex-start}
.avatar{width:42px;height:42px;flex:none;border-radius:50%;overflow:hidden;display:grid;place-items:center;background:var(--secondary-soft);color:var(--secondary-dark);font-weight:800}
.avatar img{width:100%;height:100%;object-fit:cover}
.patient-main{flex:1;min-width:0}
.patient-name{font-size:.82rem;font-weight:800;color:var(--neutral-900);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.patient-id{font-size:.57rem;color:var(--neutral-500);margin-top:2px}
.state{font-size:.55rem;font-weight:800;padding:4px 7px;border-radius:15px;background:#e9fbf4;color:#087d50}
.state.inactive{background:#edf0f3;color:#69717b}
.patient-facts{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:13px 0;padding-bottom:10px;border-bottom:1px solid var(--border-color)}
.fact-label{display:block;color:var(--neutral-500);font-size:.52rem;font-weight:800;text-transform:uppercase;margin-bottom:3px}
.fact-value{font-size:.61rem;font-weight:700;color:var(--neutral-900)}
.contact-list{display:grid;gap:6px;padding-bottom:11px;border-bottom:1px solid var(--border-color)}
.contact{display:flex;gap:7px;align-items:center;color:var(--neutral-600);font-size:.6rem;min-width:0}
.contact svg{width:13px;height:13px;flex:none}
.contact span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.card-bottom{display:flex;align-items:center;justify-content:space-between;gap:8px;padding-top:10px}
.record-counts{display:flex;gap:5px}
.count{font-size:.52rem;font-weight:800;padding:4px 6px;border-radius:12px;background:#eef3ff;color:#3158a5}
.count.rx{background:#fff0ea;color:#bd5b35}
.count.cert{background:#edf9f0;color:#15803d}
.view-record{padding:7px 11px;border:1px solid var(--border-color);border-radius:6px;color:var(--primary);background:#fff;text-decoration:none;font-size:.6rem;font-weight:800}
.empty{grid-column:1/-1;padding:55px;text-align:center;color:var(--neutral-500);border:1px solid var(--border-color);border-radius:10px}

@media(max-width:1000px){
.patient-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
.filters{grid-template-columns:1fr 1fr}
.search{grid-column:1/-1}
.filter-btn{display:none}
}

@media(max-width:620px){
.patient-page{padding:14px 12px 90px}
.patient-heading{flex-direction:column}
.heading-actions{width:100%}
.top-btn{flex:1}
.patient-grid{grid-template-columns:1fr}
.filters{grid-template-columns:1fr}
.search{grid-column:auto}
}
</style>

<main class="page patient-page">

<header class="patient-heading">
    <div>
        <h1>Patient List</h1>
        <p>View and manage your assigned patients.</p>
    </div>

    <div class="heading-actions">
        <button type="button" class="top-btn" onclick="window.print()">↓ Export</button>
        <button type="button" class="top-btn primary" onclick="alert('New patient registration is handled through the patient registration workflow.')">＋ New Patient</button>
    </div>
</header>

<form method="GET" class="filters">

    <div class="search">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7"/>
            <path d="m20 20-4-4"/>
        </svg>

        <input
            class="control"
            name="q"
            value="<?= htmlspecialchars($search) ?>"
            placeholder="Search Patient"
        >
    </div>

    <select class="control" name="status" onchange="this.form.submit()">
        <option value="">Patient Status</option>
        <option value="Active" <?= $status_filter==='Active'?'selected':'' ?>>Active</option>
        <option value="Inactive" <?= $status_filter==='Inactive'?'selected':'' ?>>Inactive</option>
    </select>

    <select class="control" name="gender" onchange="this.form.submit()">
        <option value="">Gender</option>
        <option value="Female" <?= $gender_filter==='Female'?'selected':'' ?>>Female</option>
        <option value="Male" <?= $gender_filter==='Male'?'selected':'' ?>>Male</option>
    </select>

    <button class="filter-btn" type="submit">⌕</button>

</form>

<section class="patient-grid">

<?php
$shown = 0;

if ($patients && $patients->num_rows):

while ($pt = $patients->fetch_assoc()):

$status = patientStatus($pt['last_visit'] ?? null);

if ($status_filter && $status !== $status_filter) continue;

$shown++;
?>

<article class="patient-card <?= $status==='Inactive'?'inactive':'' ?>">

    <div class="patient-top">

        <div class="avatar">
            <?php if (!empty($pt['profile_photo'])): ?>
                <img src="../<?= htmlspecialchars($pt['profile_photo']) ?>" alt="">
            <?php else: ?>
                <?= patientInitials($pt['full_name']) ?>
            <?php endif; ?>
        </div>

        <div class="patient-main">
            <div class="patient-name">
                <?= htmlspecialchars($pt['full_name']) ?>
            </div>

            <div class="patient-id">
                ID: #PT-<?= str_pad((string)$pt['id'],4,'0',STR_PAD_LEFT) ?>
            </div>
        </div>

        <span class="state <?= $status==='Inactive'?'inactive':'' ?>">
            <?= $status ?>
        </span>

    </div>

    <div class="patient-facts">

        <div>
            <span class="fact-label">DOB / Age</span>
            <span class="fact-value">
                <?= !empty($pt['date_of_birth'])
                    ? date('d M Y',strtotime($pt['date_of_birth'])) .
                      ' (' . patientAge($pt['date_of_birth']) . 'y)'
                    : 'Not provided'
                ?>
            </span>
        </div>

        <div>
            <span class="fact-label">Sex</span>
            <span class="fact-value">
                <?= htmlspecialchars($pt['gender'] ?: 'Not provided') ?>
            </span>
        </div>

    </div>

    <div class="contact-list">

        <div class="contact">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                <path d="M3 5.5A2.5 2.5 0 0 1 5.5 3h2L9 7l-2 1.5a14 14 0 0 0 8.5 8.5L17 15l4 1.5v2A2.5 2.5 0 0 1 18.5 21C9.94 21 3 14.06 3 5.5Z"/>
            </svg>
            <span><?= htmlspecialchars($pt['phone_number'] ?: 'No phone number') ?></span>
        </div>

        <div class="contact">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                <rect x="3" y="5" width="18" height="14" rx="2"/>
                <path d="m3 7 9 6 9-6"/>
            </svg>
            <span><?= htmlspecialchars($pt['email'] ?: 'No email') ?></span>
        </div>

        <div class="contact">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                <path d="M12 8v4l3 2"/>
                <circle cx="12" cy="12" r="9"/>
            </svg>
            <span>
                Last visit:
                <?= !empty($pt['last_visit'])
                    ? date('M d, Y',strtotime($pt['last_visit']))
                    : 'No visit recorded'
                ?>
            </span>
        </div>

    </div>

    <div class="card-bottom">

        <div class="record-counts">
            <?php if($pt['lab_count']): ?>
                <span class="count">🧪 <?= (int)$pt['lab_count'] ?></span>
            <?php endif; ?>

            <?php if($pt['rx_count']): ?>
                <span class="count rx">💊 <?= (int)$pt['rx_count'] ?></span>
            <?php endif; ?>

            <?php if($pt['med_cert_count']): ?>
                <span class="count cert">📄 <?= (int)$pt['med_cert_count'] ?></span>
            <?php endif; ?>
        </div>

        <a
            class="view-record"
            href="patient-records.php?patient_id=<?= (int)$pt['id'] ?>"
        >
            View Record
        </a>

    </div>

</article>

<?php endwhile; endif; ?>

<?php if(!$shown): ?>
    <div class="empty">No patients found.</div>
<?php endif; ?>

</section>

</main>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>