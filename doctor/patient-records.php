<?php
require_once 'includes/auth.php';

$patient_id = (int)($_GET['patient_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT p.*,
        (SELECT COUNT(*) FROM appointments WHERE patient_id=p.id AND doctor_id=?) total_visits,
        (SELECT appointment_date FROM appointments WHERE patient_id=p.id AND doctor_id=? ORDER BY appointment_date DESC LIMIT 1) last_visit
    FROM patients p
    WHERE p.id=? AND EXISTS(
        SELECT 1 FROM appointments a
        WHERE a.patient_id=p.id AND a.doctor_id=?
    )
    LIMIT 1
");

$stmt->bind_param('iiii',$doctor_id,$doctor_id,$patient_id,$doctor_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if(!$patient){
    header('Location: patients.php');
    exit;
}

$historyStmt = $conn->prepare("
    SELECT id,appointment_date,appointment_time,type,status,reason,notes,consultation_summary,summary_pdf_path,completed_at
    FROM appointments
    WHERE patient_id=? AND doctor_id=?
    ORDER BY appointment_date DESC,appointment_time DESC
");

$historyStmt->bind_param('ii',$patient_id,$doctor_id);
$historyStmt->execute();
$history = $historyStmt->get_result();

$recordsStmt = $conn->prepare("
    SELECT *
    FROM lab_results
    WHERE patient_id=?
    ORDER BY uploaded_at DESC
");

$recordsStmt->bind_param('i',$patient_id);
$recordsStmt->execute();
$records = $recordsStmt->get_result();

$historyRows=[];

while($row=$history->fetch_assoc()){
    $historyRows[]=$row;
}

$documentRows=[];

while($row=$records->fetch_assoc()){
    $documentRows[]=$row;
}

$sendableAppointments = array_values(array_filter($historyRows, function($row) {
    return !empty($row['completed_at'])
        && strtotime($row['completed_at']) >= (time() - 3600);
}));

function ageFromDob($dob){
    if(!$dob) return '—';

    try{
        return date_diff(
            new DateTime($dob),
            new DateTime()
        )->y;
    }catch(Throwable $e){
        return '—';
    }
}

function initials($name){
    $parts=preg_split('/\s+/',trim($name));

    return strtoupper(
        substr($parts[0]??'P',0,1).
        substr(end($parts)?:'',0,1)
    );
}

function documentType($type){
    return [
        'lab_result'=>'Laboratory Result',
        'prescription'=>'Prescription',
        'lab_request'=>'Laboratory Request',
        'med_cert'=>'Medical Certificate'
    ][$type]??'Medical Document';
}
?>

<link rel="stylesheet" href="includes/patient-records.css">


<main class="page records-page">

    <div class="records-breadcrumb">
        <a href="patients.php">Patients</a>
        <span>›</span>
        <strong><?= htmlspecialchars($patient['full_name']) ?></strong>
    </div>

    <header class="records-header">
        <div>
            <h1>Patient Records</h1>
            <p>View and manage individual patient medical information.</p>
        </div>
    </header>

    <div class="records-layout">

        <div class="left-column">

            <section class="record-card patient-profile">

                <div class="profile-avatar">

                    <?php if(!empty($patient['profile_photo'])): ?>

                        <img
                            src="../<?= htmlspecialchars($patient['profile_photo']) ?>"
                            alt=""
                        >

                    <?php else: ?>

                        <?= initials($patient['full_name']) ?>

                    <?php endif; ?>

                </div>

                <div class="profile-name">
                    <?= htmlspecialchars($patient['full_name']) ?>
                </div>

                <div class="profile-id">
                    #PT-<?= str_pad((string)$patient['id'],4,'0',STR_PAD_LEFT) ?>
                </div>

                <div class="patient-details">

                    <div class="patient-detail">
                        <label>DOB</label>
                        <span>
                            <?= !empty($patient['date_of_birth'])
                                ? date('d M Y',strtotime($patient['date_of_birth']))
                                . ' (' . ageFromDob($patient['date_of_birth']) . 'y)'
                                : 'Not provided'
                            ?>
                        </span>
                    </div>

                    <div class="patient-detail">
                        <label>Sex</label>
                        <span>
                            <?= htmlspecialchars($patient['gender'] ?: 'Not provided') ?>
                        </span>
                    </div>

                    <div class="patient-detail">
                        <label>Contact</label>
                        <span>
                            <?= htmlspecialchars($patient['phone_number'] ?: 'Not provided') ?>
                        </span>
                    </div>

                    <div class="patient-detail">
                        <label>Email</label>
                        <span>
                            <?= htmlspecialchars($patient['email'] ?: 'Not provided') ?>
                        </span>
                    </div>

                    <div class="patient-detail">
                        <label>Address</label>
                        <span>
                            <?= htmlspecialchars(
                                trim(
                                    implode(', ',array_filter([
                                        $patient['address'] ?? '',
                                        $patient['home_address'] ?? '',
                                        $patient['city'] ?? ''
                                    ]))
                                ) ?: 'Not provided'
                            ) ?>
                        </span>
                    </div>

                </div>

            </section>

            <section class="record-card">

                <div class="section-heading">
                    <h2>Reason for Check-up</h2>
                </div>

                <?php
                $latestReason=$historyRows[0]['reason']??'';
                $latestNotes=$historyRows[0]['notes']??'';
                ?>

                <div class="reason-label">
                    Chief Complaint
                </div>

                <div class="reason-box">
                    <?= htmlspecialchars(
                        $latestReason ?: 'No recent chief complaint recorded.'
                    ) ?>
                </div>

                <div class="reason-label">
                    Recent Visit
                </div>

                <div class="visit-chips">

                    <span class="visit-chip">
                        <?= (int)$patient['total_visits'] ?>
                        visit<?= (int)$patient['total_visits']===1?'':'s' ?>
                    </span>

                    <?php if(!empty($patient['last_visit'])): ?>

                        <span class="visit-chip">
                            <?= date('M d, Y',strtotime($patient['last_visit'])) ?>
                        </span>

                    <?php endif; ?>

                </div>

                <?php if($latestNotes): ?>

                    <div class="reason-label">
                        Patient Intake Notes
                    </div>

                    <div class="intake-box">
                        <?= htmlspecialchars($latestNotes) ?>
                    </div>

                <?php endif; ?>

            </section>

        </div>

        <div class="right-column">

            <div class="record-tabs">

                <button
                    class="record-tab active"
                    type="button"
                    data-tab="overview"
                >
                    Overview
                </button>

                <button
                    class="record-tab"
                    type="button"
                    data-tab="documents"
                >
                    Documents
                </button>

            </div>

            <section id="overview" class="tab-content">

                <div class="overview-grid">

                    <section class="overview-card">

                        <div class="overview-title" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">

                            <span style="display:flex;align-items:center;gap:8px;">

                                <svg
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                >
                                    <path d="M6 3h12v18H6z"/>
                                    <path d="M9 7h6M9 11h6M9 15h4"/>
                                </svg>

                                Medical History

                            </span>

                            <a
                                href="patient-summaries.php?patient_id=<?= (int)$patient_id ?>"
                                class="upload-button"
                                style="text-decoration:none;"
                            >
                                View All Summaries
                            </a>

                        </div>

                        <?php if($historyRows): ?>

                            <div class="history-list">

                                <?php foreach(array_slice($historyRows,0,5) as $historyRow): ?>

                                    <div class="history-row">

                                        <div class="history-date">
                                            <?= date(
                                                'M d, Y',
                                                strtotime($historyRow['appointment_date'])
                                            ) ?>
                                        </div>

                                        <div class="history-type">
                                            <?= htmlspecialchars(
                                                $historyRow['type'] ?: 'Consultation'
                                            ) ?>
                                        </div>

                                        <div class="history-reason">
                                            <?= htmlspecialchars(
                                                $historyRow['reason']
                                                ?: 'No reason recorded.'
                                            ) ?>
                                        </div>

                                        <?php if (
                                            !empty($historyRow['summary_pdf_path'])
                                            && $historyRow['summary_pdf_path'] !== 'TEXT_CONFIRMED'
                                        ): ?>

                                            <div class="history-summary">
                                                <span class="history-summary-label">Summarized Notes</span>

                                                <?= date(
                                                    'M d, Y g:i A',
                                                    strtotime(
                                                        $historyRow['appointment_date']
                                                        . ' '
                                                        . $historyRow['appointment_time']
                                                    )
                                                ) ?>

                                                <a
                                                    class="history-summary-pdf"
                                                    href="../consultation_summaries/<?= htmlspecialchars($historyRow['summary_pdf_path']) ?>"
                                                    target="_blank"
                                                    rel="noopener"
                                                >
                                                    View Summary (PDF)
                                                </a>

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <span class="muted">
                                No consultation history recorded.
                            </span>

                        <?php endif; ?>

                    </section>

                    <section class="overview-card">

                        <div class="overview-title">

                            <svg
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <rect x="5" y="4" width="14" height="17" rx="2"/>
                                <path d="M9 4V2h6v2M8 9h8M8 13h6"/>
                            </svg>

                            Current Medications

                        </div>

                        <?php
                        $medications=[];

                        $medStmt=$conn->prepare("
                            SELECT doc_label,extracted_text,uploaded_at
                            FROM lab_results
                            WHERE patient_id=?
                            AND doc_type='prescription'
                            ORDER BY uploaded_at DESC
                            LIMIT 5
                        ");

                        $medStmt->bind_param('i',$patient_id);
                        $medStmt->execute();

                        $medResult=$medStmt->get_result();

                        while($med=$medResult->fetch_assoc()){
                            $medications[]=$med;
                        }
                        ?>

                        <?php if($medications): ?>

                            <?php foreach($medications as $med): ?>

                                <div class="medication-row">

                                    <div>

                                        <div class="medication-name">
                                            <?= htmlspecialchars(
                                                $med['doc_label']
                                                ?: 'Prescription'
                                            ) ?>
                                        </div>

                                        <div class="medication-text">
                                            <?= htmlspecialchars(
                                                mb_substr(
                                                    trim(
                                                        preg_replace(
                                                            '/\s+/',
                                                            ' ',
                                                            $med['extracted_text'] ?? ''
                                                        )
                                                    ),
                                                    0,
                                                    150
                                                )
                                            ) ?>
                                        </div>

                                    </div>

                                    <span class="status-badge">
                                        Recorded
                                    </span>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <span class="muted">
                                No prescription records available.
                            </span>

                        <?php endif; ?>

                    </section>

                </div>

            </section>

            <section
                id="documents"
                class="tab-content"
                style="display:none"
            >

                <div class="documents-section">

                    <div class="documents-header">

                        <div class="documents-title">

                            <h2>
                                Optical Character Recognition (OCR) Documents
                            </h2>

                            <p>
                                Digitized paperwork and extracted information from patient documents.
                            </p>

                        </div>

                        <button
                            type="button"
                            class="upload-button"
                            onclick="openSendDocumentModal()"
                        >
                            + Send Document
                        </button>

                    </div>

                    <?php if($documentRows): ?>

                        <div class="document-grid">

                            <?php foreach($documentRows as $document): ?>

                                <article class="document-card">

                                    <div class="document-top">

                                        <div class="document-icon">

                                            <svg
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="1.8"
                                            >
                                                <path d="M6 3h9l3 3v15H6z"/>
                                                <path d="M14 3v4h4M9 12h6M9 16h6"/>
                                            </svg>

                                        </div>

                                        <div>

                                            <div class="document-name">
                                                <?= htmlspecialchars(
                                                    $document['doc_label']
                                                    ?: documentType($document['doc_type'])
                                                ) ?>
                                            </div>

                                            <div class="document-type">
                                                <?= htmlspecialchars(
                                                    documentType($document['doc_type'])
                                                ) ?>

                                                <?php if(!empty($document['uploaded_at'])): ?>

                                                    ·
                                                    <?= date(
                                                        'M d, Y',
                                                        strtotime($document['uploaded_at'])
                                                    ) ?>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    </div>

                                    <div class="ocr-box">

                                        <div class="ocr-title">
                                            EXTRACTED TEXT
                                        </div>

                                        <div class="ocr-text">
                                            <?= htmlspecialchars(
                                                $document['extracted_text']
                                                ?: 'No OCR text available.'
                                            ) ?>
                                        </div>

                                    </div>

                                    <div class="document-actions">

                                        <?php if(!empty($document['file_path'])): ?>

                                            <a
                                                href="../<?= htmlspecialchars($document['file_path']) ?>"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                View Scanned File
                                            </a>

                                        <?php endif; ?>

                                        <button
                                            type="button"
                                            onclick="copyOCR(this)"
                                        >
                                            Copy OCR
                                        </button>

                                    </div>

                                    <textarea hidden><?= htmlspecialchars(
                                        $document['extracted_text'] ?? ''
                                    ) ?></textarea>

                                </article>

                            <?php endforeach; ?>

                        </div>

                    <?php else: ?>

                        <div class="empty-records">
                            No OCR documents or medical documents are available.
                        </div>

                    <?php endif; ?>

                </div>

            </section>

        </div>

    </div>

</main>

<div id="sendDocumentModal" class="modal-overlay" onclick="if(event.target===this) closeSendDocumentModal()">
    <div class="modal-box">

        <div class="modal-head">
            <h3>Send Document</h3>
            <button type="button" class="modal-close" onclick="closeSendDocumentModal()">&times;</button>
        </div>

        <?php if ($sendableAppointments): ?>

            <?php if (count($sendableAppointments) > 1): ?>

                <label class="modal-label">Which consultation is this for?</label>

                <div class="modal-appt-list">
                    <?php foreach ($sendableAppointments as $i => $sendAppt): ?>
                        <label class="modal-appt-option">
                            <input
                                type="radio"
                                name="send_doc_appt"
                                value="<?= (int)$sendAppt['id'] ?>"
                                <?= $i === 0 ? 'checked' : '' ?>
                            >
                            <span>
                                <?= date('M d, Y', strtotime($sendAppt['appointment_date'])) ?>
                                · <?= htmlspecialchars($sendAppt['type'] ?: 'Consultation') ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>

                <input type="hidden" id="soleSendApptId" value="<?= (int)$sendableAppointments[0]['id'] ?>">

                <p class="modal-hint">
                    For the <?= date('M d, Y', strtotime($sendableAppointments[0]['appointment_date'])) ?> consultation.
                </p>

            <?php endif; ?>

            <label class="modal-label" style="margin-top:14px;">Document type</label>

            <div class="modal-doctype-grid">
                <button type="button" class="doctype-btn" onclick="sendDocument('prescription')">💊 Prescription</button>
                <button type="button" class="doctype-btn" onclick="sendDocument('lab_request')">🧪 Lab Request</button>
                <button type="button" class="doctype-btn" onclick="sendDocument('med_cert')">📄 Medical Certificate</button>
            </div>

        <?php else: ?>

            <p class="modal-hint">
                Documents can only be sent within 1 hour after a teleconsultation is completed.
                There's no eligible consultation for this patient right now.
            </p>

        <?php endif; ?>

    </div>
</div>

<script>
document.querySelectorAll('.record-tab').forEach(tab=>{
    tab.addEventListener('click',()=>{
        document.querySelectorAll('.record-tab').forEach(item=>{
            item.classList.remove('active');
        });

        document.querySelectorAll('.tab-content').forEach(panel=>{
            panel.style.display='none';
        });

        tab.classList.add('active');

        const panel=document.getElementById(tab.dataset.tab);

        if(panel){
            panel.style.display='block';
        }
    });
});

function copyOCR(button){
    const text=button
        .closest('.document-card')
        .querySelector('textarea')
        .value;

    navigator.clipboard.writeText(text).then(()=>{
        const original=button.textContent;

        button.textContent='Copied';

        setTimeout(()=>{
            button.textContent=original;
        },1500);
    });
}

function openSendDocumentModal(){
    document.getElementById('sendDocumentModal').classList.add('open');
}

function closeSendDocumentModal(){
    document.getElementById('sendDocumentModal').classList.remove('open');
}

function sendDocument(docType){
    const radios = document.getElementsByName('send_doc_appt');
    let apptId = null;

    if (radios.length) {
        const checked = Array.from(radios).find(r => r.checked);
        apptId = checked ? checked.value : null;
    } else {
        const sole = document.getElementById('soleSendApptId');
        apptId = sole ? sole.value : null;
    }

    if (!apptId) return;

    window.location.href = 'send_document.php?appt_id=' + encodeURIComponent(apptId)
        + '&doc_type=' + encodeURIComponent(docType);
}
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>