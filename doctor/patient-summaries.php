<?php
require_once 'includes/auth.php';

$patient_id = (int)($_GET['patient_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT p.id, p.full_name, p.profile_photo
    FROM patients p
    WHERE p.id=? AND EXISTS(
        SELECT 1 FROM appointments a
        WHERE a.patient_id=p.id AND a.doctor_id=?
    )
    LIMIT 1
");

$stmt->bind_param('ii',$patient_id,$doctor_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if(!$patient){
    header('Location: patients.php');
    exit;
}

/* ---------- filters ---------- */

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

// basic sanity check on the date format so we don't pass garbage to SQL
if($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)) $dateFrom = '';
if($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo))     $dateTo   = '';

$perPage = 6;
$page    = max(1,(int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$where  = "patient_id=? AND doctor_id=?
    AND summary_pdf_path IS NOT NULL
    AND summary_pdf_path != ''
    AND summary_pdf_path != 'TEXT_CONFIRMED'";

$types  = 'ii';
$params = [$patient_id,$doctor_id];

if($dateFrom !== ''){
    $where   .= " AND appointment_date >= ?";
    $types   .= 's';
    $params[] = $dateFrom;
}

if($dateTo !== ''){
    $where   .= " AND appointment_date <= ?";
    $types   .= 's';
    $params[] = $dateTo;
}

/* ---------- count for pagination ---------- */

$countStmt = $conn->prepare("SELECT COUNT(*) total FROM appointments WHERE $where");

$countRefs = [$types];
foreach($params as $key => $value){ $countRefs[] = &$params[$key]; }
call_user_func_array([$countStmt,'bind_param'],$countRefs);

$countStmt->execute();
$totalRows  = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$totalPages = max(1,(int)ceil($totalRows / $perPage));
$page       = min($page,$totalPages);
$offset     = ($page - 1) * $perPage;

/* ---------- page of results ---------- */

$summaryStmt = $conn->prepare("
    SELECT id,appointment_date,appointment_time,type,reason,summary_pdf_path
    FROM appointments
    WHERE $where
    ORDER BY appointment_date DESC, appointment_time DESC
    LIMIT ? OFFSET ?
");

$dataTypes  = $types.'ii';
$dataParams = $params;
$dataParams[] = $perPage;
$dataParams[] = $offset;

$dataRefs = [$dataTypes];
foreach($dataParams as $key => $value){ $dataRefs[] = &$dataParams[$key]; }
call_user_func_array([$summaryStmt,'bind_param'],$dataRefs);

$summaryStmt->execute();
$summaryResult = $summaryStmt->get_result();

$summaryRows=[];
while($row=$summaryResult->fetch_assoc()){
    $summaryRows[]=$row;
}

function buildQuery($overrides,$patient_id,$dateFrom,$dateTo,$page){
    $q = array_merge([
        'patient_id' => $patient_id,
        'date_from'  => $dateFrom,
        'date_to'    => $dateTo,
        'page'       => $page,
    ],$overrides);

    $q = array_filter($q,fn($v) => $v !== '' && $v !== null);

    return http_build_query($q);
}
?>

<link rel="stylesheet" href="includes/patient-records.css">

<style>
    .summaries-wrap{
        max-width:760px;
        margin:0 auto;
    }

    .summaries-header{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:16px;
        flex-wrap:wrap;
    }

    .summaries-header .upload-button{
        text-decoration:none;
        white-space:nowrap;
        flex-shrink:0;
    }

    .summary-filter-bar{
        display:flex;
        align-items:flex-end;
        gap:12px;
        flex-wrap:wrap;
        margin-bottom:18px;
    }

    .summary-filter-field{
        display:flex;
        flex-direction:column;
        gap:4px;
    }

    .summary-filter-field label{
        font-size:12px;
        font-weight:600;
        color:#6b7280;
        text-transform:uppercase;
        letter-spacing:.03em;
    }

    .summary-filter-field input[type="date"]{
        padding:8px 10px;
        border:1px solid #e2e2e2;
        border-radius:8px;
        font-size:14px;
    }

    .summary-filter-actions{
        display:flex;
        gap:8px;
    }

    .summary-filter-actions button,
    .summary-filter-actions a{
        padding:8px 16px;
        border-radius:8px;
        font-size:14px;
        text-decoration:none;
        cursor:pointer;
    }

    .summary-filter-submit{
        border:none;
        background:#b91c1c;
        color:#fff;
    }

    .summary-filter-clear{
        border:1px solid #e2e2e2;
        background:#fff;
        color:#333;
    }

    .summary-count{
        font-size:13px;
        color:#6b7280;
        margin-bottom:12px;
    }

    .summary-pagination{
        display:flex;
        align-items:center;
        justify-content:center;
        gap:6px;
        margin-top:20px;
        flex-wrap:wrap;
    }

    .summary-pagination a,
    .summary-pagination span{
        min-width:34px;
        height:34px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:8px;
        font-size:13px;
        text-decoration:none;
        color:#333;
        border:1px solid #e2e2e2;
    }

    .summary-pagination a:hover{
        background:#f5f5f5;
    }

    .summary-pagination .active{
        background:#b91c1c;
        border-color:#b91c1c;
        color:#fff;
    }

    .summary-pagination .disabled{
        opacity:.4;
        pointer-events:none;
    }
</style>

<main class="page records-page">

    <div class="summaries-wrap">

        <div class="records-breadcrumb">
            <a href="patients.php">Patients</a>
            <span>›</span>
            <a href="patient-records.php?patient_id=<?= (int)$patient['id'] ?>">
                <?= htmlspecialchars($patient['full_name']) ?>
            </a>
            <span>›</span>
            <strong>All Summaries</strong>
        </div>

        <header class="records-header summaries-header">
            <div>
                <h1>All Consultation Summaries</h1>
                <p>
                    Complete summary history for
                    <?= htmlspecialchars($patient['full_name']) ?>.
                </p>
            </div>

            <a
                href="patient-records.php?patient_id=<?= (int)$patient['id'] ?>"
                class="upload-button"
            >
                ← Back to Patient Record
            </a>
        </header>

        <section class="record-card">

            <div class="section-heading">
                <h2>Summarized Notes</h2>
            </div>

            <form class="summary-filter-bar" method="get">

                <input type="hidden" name="patient_id" value="<?= (int)$patient['id'] ?>">

                <div class="summary-filter-field">
                    <label for="date_from">From</label>
                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="<?= htmlspecialchars($dateFrom) ?>"
                    >
                </div>

                <div class="summary-filter-field">
                    <label for="date_to">To</label>
                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="<?= htmlspecialchars($dateTo) ?>"
                    >
                </div>

                <div class="summary-filter-actions">
                    <button type="submit" class="summary-filter-submit">
                        Find
                    </button>

                    <?php if($dateFrom || $dateTo): ?>

                        <a
                            href="patient-summaries.php?patient_id=<?= (int)$patient['id'] ?>"
                            class="summary-filter-clear"
                        >
                            Clear
                        </a>

                    <?php endif; ?>
                </div>

            </form>

            <div class="summary-count">
                <?= $totalRows ?>
                summar<?= $totalRows===1?'y':'ies' ?> found
                <?= ($dateFrom || $dateTo) ? 'for the selected date range' : '' ?>
            </div>

            <?php if($summaryRows): ?>

                <div class="history-list">

                    <?php foreach($summaryRows as $summaryRow): ?>

                        <div class="history-row">

                            <div class="history-date">
                                <?= date(
                                    'M d, Y',
                                    strtotime($summaryRow['appointment_date'])
                                ) ?>
                            </div>

                            <div class="history-type">
                                <?= htmlspecialchars(
                                    $summaryRow['type'] ?: 'Consultation'
                                ) ?>
                            </div>

                            <div class="history-reason">
                                <?= htmlspecialchars(
                                    $summaryRow['reason']
                                    ?: 'No reason recorded.'
                                ) ?>
                            </div>

                            <div class="history-summary">
                                <span class="history-summary-label">Summarized Notes</span>

                                <?= date(
                                    'M d, Y g:i A',
                                    strtotime(
                                        $summaryRow['appointment_date']
                                        . ' '
                                        . $summaryRow['appointment_time']
                                    )
                                ) ?>

                                <a
                                    class="history-summary-pdf"
                                    href="../consultation_summaries/<?= htmlspecialchars($summaryRow['summary_pdf_path']) ?>"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    View Summary (PDF)
                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

                <?php if($totalPages > 1): ?>

                    <div class="summary-pagination">

                        <a
                            class="<?= $page<=1 ? 'disabled':'' ?>"
                            href="?<?= buildQuery(['page'=>$page-1],$patient['id'],$dateFrom,$dateTo,$page-1) ?>"
                        >
                            ‹
                        </a>

                        <?php for($p=1;$p<=$totalPages;$p++): ?>

                            <a
                                class="<?= $p===$page ? 'active':'' ?>"
                                href="?<?= buildQuery(['page'=>$p],$patient['id'],$dateFrom,$dateTo,$p) ?>"
                            >
                                <?= $p ?>
                            </a>

                        <?php endfor; ?>

                        <a
                            class="<?= $page>=$totalPages ? 'disabled':'' ?>"
                            href="?<?= buildQuery(['page'=>$page+1],$patient['id'],$dateFrom,$dateTo,$page+1) ?>"
                        >
                            ›
                        </a>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <span class="muted">
                    No consultation summaries found<?= ($dateFrom || $dateTo) ? ' for the selected date range.' : ' for this patient yet.' ?>
                </span>

            <?php endif; ?>

        </section>

    </div>

</main>

<?php require_once 'includes/nav.php'; ?>