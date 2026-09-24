<?php
// staff/appointments.php
require_once 'includes/auth.php';
require_once 'includes/functions.php';
// staff/appointments.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Approve / Reject / Cancel / Complete ──
    if (isset($_POST['action'], $_POST['appt_id'])) {
        $aid    = (int)$_POST['appt_id'];
        $action = $_POST['action'];
        $notes  = trim($_POST['action_notes'] ?? '');
        $tab    = trim($_POST['active_tab']   ?? 'All');

        $map = [
            'approve'  => 'Confirmed',
            'reject'   => 'Cancelled',
            'cancel'   => 'Cancelled',
            'complete' => 'Completed',
        ];

        if (isset($map[$action])) {
            $new_status = $map[$action];

            if ($action === 'approve' || $action === 'reject') {
                $chk = $conn->prepare("SELECT status FROM appointments WHERE id=?");
                $chk->bind_param("i", $aid);
                $chk->execute();
                $cur = $chk->get_result()->fetch_assoc();
                if (!$cur || $cur['status'] !== 'DoctorApproved') {
                    $_SESSION['toast_error'] = "Cannot act on this appointment — waiting for doctor's acceptance first.";
                    header('Location: appointments.php?tab=' . urlencode($tab)); exit;
                }
            }

            $conn->query("UPDATE appointments SET status='$new_status' WHERE id=$aid");
            logAction($conn, $aid, $staff_id, ucfirst($action) . 'd', $notes);
            $_SESSION['toast'] = "Appointment " . $new_status . " successfully.";
            header('Location: appointments.php?tab=' . urlencode($tab)); exit;
        }
    }

    // ── Create appointment ──
    if (isset($_POST['create_appt'])) {
        $pid   = (int)$_POST['patient_id'];
        $did   = (int)$_POST['doctor_id'];
        $date  = trim($_POST['appt_date'] ?? '');
        $time  = trim($_POST['appt_time'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $ca_error = '';

        if ($pid && $did && $date && $time) {
            $appt_ts = strtotime("$date $time");
            if ($appt_ts <= time()) {
                $ca_error = 'Cannot book an appointment in the past.';
            } else {
                $day_of_week = date('l', $appt_ts);
                $t_start     = date('H:i:s', $appt_ts);
                $sched = $conn->prepare("SELECT id FROM doctor_schedules WHERE doctor_id=? AND day_of_week=? AND start_time<=? AND end_time>? LIMIT 1");
                $sched->bind_param("isss", $did, $day_of_week, $t_start, $t_start);
                $sched->execute();
                if (!$sched->get_result()->fetch_assoc()) {
                    $ca_error = "The doctor is not available on $day_of_week at that time.";
                }
            }

            if (!$ca_error) {
                $type = 'Teleconsult';
                $stmt = $conn->prepare("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,type,notes,status,payment_status) VALUES (?,?,?,?,?,?,'Confirmed','Unpaid')");
                $stmt->bind_param("iissss", $pid, $did, $date, $time, $type, $notes);
                $stmt->execute();
                $_SESSION['toast'] = "Appointment created successfully.";
                header('Location: appointments.php'); exit;
            }
        }
        $_SESSION['ca_error'] = $ca_error ?: 'Please fill in all required fields.';
        header('Location: appointments.php?ca_open=1'); exit;
    }

    // ── Reschedule ──
    if (isset($_POST['reschedule'])) {
        $aid  = (int)$_POST['appt_id'];
        $date = trim($_POST['new_date'] ?? '');
        $time = trim($_POST['new_time'] ?? '');
        $tab  = trim($_POST['active_tab'] ?? 'All');
        $rs_error = '';

        if ($aid && $date && $time) {
            $appt_ts = strtotime("$date $time");
            if ($appt_ts <= time()) {
                $rs_error = 'Cannot reschedule to a past date or time.';
            } else {
                $row = $conn->query("SELECT doctor_id FROM appointments WHERE id=$aid")->fetch_assoc();
                $did = (int)($row['doctor_id'] ?? 0);
                $day_of_week = date('l', $appt_ts);
                $t_start     = date('H:i:s', $appt_ts);
                $sched = $conn->prepare("SELECT id FROM doctor_schedules WHERE doctor_id=? AND day_of_week=? AND start_time<=? AND end_time>? LIMIT 1");
                $sched->bind_param("isss", $did, $day_of_week, $t_start, $t_start);
                $sched->execute();
                if (!$sched->get_result()->fetch_assoc()) {
                    $rs_error = "The doctor is not available on $day_of_week at that time.";
                }
            }

            if ($rs_error) {
                $_SESSION['rs_error']   = $rs_error;
                $_SESSION['rs_appt_id'] = $aid;
                header('Location: appointments.php?tab=' . urlencode($tab) . '&rs_open=' . $aid); exit;
            }

            $conn->query("UPDATE appointments SET appointment_date='$date', appointment_time='$time', status='Confirmed' WHERE id=$aid");
            $_SESSION['toast'] = "Appointment rescheduled.";
        }
        header('Location: appointments.php?tab=' . urlencode($tab)); exit;
    }
}

$toast       = $_SESSION['toast']       ?? null;
$toast_error = $_SESSION['toast_error'] ?? null;
$rs_error    = $_SESSION['rs_error']    ?? null;
$ca_error    = $_SESSION['ca_error']    ?? null;
unset($_SESSION['toast'], $_SESSION['toast_error'], $_SESSION['rs_error'], $_SESSION['rs_appt_id'], $_SESSION['ca_error']);

$rs_open  = (int)($_GET['rs_open'] ?? 0);
$ca_open  = (int)($_GET['ca_open'] ?? 0);

$active_tab  = htmlspecialchars($_GET['tab'] ?? 'All');
$active_page = 'appointments';

// ── Counts ──
$stat_pending         = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE status='Pending'")->fetch_assoc()['c'];
$stat_doctor_approved = (int)$conn->query("SELECT COUNT(*) c FROM appointments WHERE status='DoctorApproved'")->fetch_assoc()['c'];

// ── Fetch ALL rows (we do pagination in JS) ──
$all_appts = $conn->query("
    SELECT a.*, p.full_name AS patient_name,
           d.full_name AS doctor_name, d.specialty, d.id AS doctor_id,
           d.consultation_fee
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN doctors  d ON d.id = a.doctor_id
    ORDER BY a.id DESC
");

$sched_rows = $conn->query("SELECT doctor_id, day_of_week, start_time, end_time FROM doctor_schedules ORDER BY doctor_id, day_of_week, start_time");
$doctor_schedules = [];
if ($sched_rows) {
    while ($s = $sched_rows->fetch_assoc()) {
        $doctor_schedules[$s['doctor_id']][] = ['day'=>$s['day_of_week'],'start'=>substr($s['start_time'],0,5),'end'=>substr($s['end_time'],0,5)];
    }
}

require_once 'includes/header.php';
?>

<style>
/* ── Appointments page refresh (scoped additions, doesn't touch style.css) ── */
.ap-page-head{margin-bottom:1.4rem}
.sec-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.1rem}
.sec-head h2{font-family:'Playfair Display',serif;font-size:1.55rem;font-weight:800;color:var(--text,#1a1a1a);margin:0}
.search-bar{border:1.5px solid var(--border,#e5e7eb);border-radius:12px;padding:.6rem 1rem;font-family:'DM Sans',sans-serif;font-size:.85rem;min-width:260px;outline:none;transition:border-color .2s,box-shadow .2s;background:#fff}
.search-bar:focus{border-color:var(--red,#8B1E2B);box-shadow:0 0 0 3px rgba(139,30,43,.1)}

.ap-tabs{display:flex;gap:.5rem;margin-bottom:1.2rem;flex-wrap:wrap}
.ap-tab{border:1.5px solid var(--border,#e5e7eb);background:#fff;color:var(--muted,#6b7280);border-radius:50px;padding:.5rem 1.1rem;font-size:.82rem;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;transition:all .18s;display:inline-flex;align-items:center;gap:.4rem}
.ap-tab:hover{border-color:var(--red,#8B1E2B);color:var(--red,#8B1E2B)}
.ap-tab.active{background:var(--red,#8B1E2B);border-color:var(--red,#8B1E2B);color:#fff;box-shadow:0 4px 14px rgba(139,30,43,.25)}
.ap-tab .cnt{background:rgba(255,255,255,.25);border-radius:50%;min-width:19px;height:19px;font-size:.68rem;display:inline-flex;align-items:center;justify-content:center;padding:0 .3rem}
.ap-tab:not(.active) .cnt{background:var(--blue,#3F82E3);color:#fff}

.tbl-wrap{background:#fff;border:1.5px solid var(--border,#e5e7eb);border-radius:16px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.03)}
.tbl-wrap table{width:100%;border-collapse:collapse}
.tbl-wrap thead th{text-align:left;font-size:.7rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted,#6b7280);padding:.85rem 1rem;background:#fafafa;border-bottom:1.5px solid var(--border,#e5e7eb)}
.tbl-wrap tbody td{padding:.9rem 1rem;font-size:.86rem;border-bottom:1px solid var(--border,#f0f0f0);vertical-align:top}
.tbl-wrap tbody tr:last-child td{border-bottom:none}
.tbl-wrap tbody tr:hover{background:#fafafa}
.tbl-wrap tbody tr.row-doctor-approved{background:rgba(63,130,227,.045)}

.appt-id-badge{font-weight:800;color:var(--muted,#6b7280);font-size:.8rem}

.badge{display:inline-block;padding:.28rem .7rem;border-radius:50px;font-size:.72rem;font-weight:700}
.bg-green{background:rgba(34,197,94,.12);color:#15803d}
.bg-orange{background:rgba(245,158,11,.12);color:#b45309}
.bg-blue{background:rgba(63,130,227,.12);color:#1d4ed8}
.bg-red{background:rgba(239,68,68,.12);color:#b91c1c}

.btn-sm{border:none;border-radius:50px;padding:.42rem .85rem;font-size:.76rem;font-weight:700;font-family:'DM Sans',sans-serif;cursor:pointer;transition:transform .15s,opacity .15s}
.btn-sm:hover{transform:translateY(-1px)}
.btn-green{background:#16a34a;color:#fff}
.btn-red{background:#ef4444;color:#fff}
.btn-orange{background:#f59e0b;color:#fff}
.btn-primary{background:var(--red,#8B1E2B);color:#fff;border:none;border-radius:50px;padding:.65rem 1.3rem;font-size:.85rem;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(139,30,43,.28);transition:transform .15s}
.btn-primary:hover{transform:translateY(-1px)}

.btn-receipt-sm{background:#fff;border:1.5px solid var(--border,#e5e7eb);color:var(--text,#1a1a1a);border-radius:8px;padding:.3rem .6rem;font-size:.72rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center}
.btn-receipt-sm:hover{border-color:var(--red,#8B1E2B);color:var(--red,#8B1E2B)}

.pagination-wrap{display:flex;align-items:center;justify-content:space-between;margin-top:1rem;flex-wrap:wrap;gap:.6rem}
.pagination-info{font-size:.8rem;color:var(--muted,#6b7280)}
.pagination-btns{display:flex;align-items:center;gap:.3rem}
.pg-btn{border:1.5px solid var(--border,#e5e7eb);background:#fff;border-radius:8px;min-width:32px;height:32px;font-size:.78rem;font-weight:600;cursor:pointer;color:var(--text,#1a1a1a)}
.pg-btn:hover:not(:disabled){border-color:var(--red,#8B1E2B);color:var(--red,#8B1E2B)}
.pg-btn.active{background:var(--red,#8B1E2B);border-color:var(--red,#8B1E2B);color:#fff}
.pg-btn:disabled{opacity:.4;cursor:not-allowed}
.pg-ellipsis{color:var(--muted,#6b7280);padding:0 .2rem}
.empty-row{text-align:center;padding:2.5rem 1rem;color:var(--muted,#6b7280);font-size:.88rem}

/* ── Modals ── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(20,20,20,.5);z-index:9998;align-items:center;justify-content:center}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:18px;padding:1.6rem;max-width:480px;width:92%;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.modal h3{font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:800;margin:0 0 1rem}
.f-label{display:block;font-size:.75rem;font-weight:700;color:var(--muted,#6b7280);margin:.8rem 0 .35rem;text-transform:uppercase;letter-spacing:.04em}
.f-input{width:100%;padding:.65rem .85rem;border:1.5px solid var(--border,#e5e7eb);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.88rem;outline:none}
.f-input:focus{border-color:var(--red,#8B1E2B);box-shadow:0 0 0 3px rgba(139,30,43,.1)}
.btn-submit{width:100%;background:var(--red,#8B1E2B);color:#fff;border:none;border-radius:50px;padding:.75rem;font-weight:700;font-size:.88rem;cursor:pointer;margin-top:1.1rem}
.btn-cancel-modal{width:100%;background:transparent;border:1.5px solid var(--border,#e5e7eb);border-radius:50px;padding:.7rem;font-weight:700;font-size:.85rem;cursor:pointer;margin-top:.55rem;color:var(--muted,#6b7280)}

/* ── Calendar picker ── */
.cal-wrap{border:1.5px solid var(--border,#e5e7eb);border-radius:12px;padding:.8rem;margin-top:.3rem}
.cal-wrap.cal-disabled{opacity:.5;pointer-events:none}
.cal-placeholder{text-align:center;color:var(--muted,#6b7280);font-size:.82rem;padding:1rem 0}
.cal-header{display:flex;align-items:center;justify-content:space-between;font-weight:700;font-size:.85rem;margin-bottom:.5rem}
.cal-nav{background:none;border:none;font-size:1.1rem;cursor:pointer;color:var(--muted,#6b7280);width:26px;height:26px;border-radius:50%}
.cal-nav:hover:not(:disabled){background:#f3f4f6}
.cal-nav:disabled{opacity:.3;cursor:not-allowed}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center}
.cal-day-name{font-size:.65rem;font-weight:700;color:var(--muted,#6b7280);padding:.3rem 0}
.cal-cell{font-size:.78rem;padding:.45rem 0;border-radius:8px;cursor:default}
.cal-cell.empty{visibility:hidden}
.cal-cell.past{color:#d1d5db}
.cal-cell.blocked{color:#d1d5db;text-decoration:line-through}
.cal-cell.available{cursor:pointer;color:var(--text,#1a1a1a);font-weight:600}
.cal-cell.available:hover{background:rgba(139,30,43,.08)}
.cal-cell.today{box-shadow:inset 0 0 0 1.5px var(--blue,#3F82E3)}
.cal-cell.selected{background:var(--red,#8B1E2B);color:#fff}
.cal-legend{margin-top:.6rem;padding-top:.6rem;border-top:1px dashed var(--border,#e5e7eb);font-size:.72rem;color:var(--muted,#6b7280);line-height:1.7}
.cal-legend-title{font-weight:700;color:var(--text,#1a1a1a);margin-bottom:.2rem}

/* ══════════════ RECEIPT — redesigned ══════════════ */
.rcpt-modal{padding:0 !important;border-radius:22px !important;overflow-y:auto !important;overflow-x:hidden;max-height:90vh}
.rcpt-hero{background:linear-gradient(135deg,var(--red,#8B1E2B) 0%,#5c131c 100%);padding:1.9rem 1.6rem 1.6rem;text-align:center;position:relative;color:#fff}
.rcpt-hero-close{position:absolute;top:.9rem;right:.9rem;background:rgba(255,255,255,.18);border:none;color:#fff;width:30px;height:30px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center}
.rcpt-hero-close:hover{background:rgba(255,255,255,.3)}
.rcpt-brand{font-size:.68rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;opacity:.8;margin-bottom:.5rem;display:flex;align-items:center;justify-content:center;gap:.35rem}
.rcpt-title{font-family:'Playfair Display',serif;font-size:1.55rem;font-weight:800;margin-bottom:.2rem}
.rcpt-sub{font-size:.76rem;opacity:.8}

.rcpt-body{padding:1.7rem 1.5rem 0}
.rcpt-status-bar{display:flex;align-items:center;gap:.7rem;background:rgba(34,197,94,.08);border:1.5px solid rgba(34,197,94,.25);border-radius:14px;padding:.75rem 1rem;margin-bottom:1.2rem}
.rcpt-status-icon{width:34px;height:34px;background:linear-gradient(135deg,#16a34a,#15803d);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.rcpt-status-title{font-weight:700;font-size:.88rem;color:#15803d}
.rcpt-status-date{font-size:.72rem;color:#16a34a;opacity:.85}

.rcpt-no-wrap{text-align:center;margin-bottom:1rem}
.rcpt-no-label{font-size:.63rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--muted,#6b7280)}
.rcpt-no-val{font-size:1.1rem;font-weight:800;color:var(--red,#8B1E2B);letter-spacing:.04em}

.rcpt-divider{border:none;border-top:1.5px dashed var(--border,#e5e7eb);margin:.9rem 0}

.rcpt-detail-row{display:flex;justify-content:space-between;gap:1rem;font-size:.83rem;padding:.4rem 0}
.rcpt-detail-label{color:var(--muted,#6b7280);font-weight:600}
.rcpt-detail-val{font-weight:700;color:var(--text,#1a1a1a);text-align:right}

.rcpt-amount-box{background:#faf5f5;border:1.5px solid rgba(139,30,43,.12);border-radius:16px;padding:1.1rem;text-align:center;margin:1.1rem 0}
.rcpt-amount-label{font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted,#6b7280);margin-bottom:.3rem}
.rcpt-amount-val{font-size:2.1rem;font-weight:800;color:var(--red,#8B1E2B)}
.rcpt-paid-pill{display:inline-flex;align-items:center;gap:.3rem;background:#16a34a;color:#fff;border-radius:50px;padding:.22rem .85rem;font-size:.7rem;font-weight:700;margin-top:.5rem}

.rcpt-meta-row{display:flex;justify-content:space-between;font-size:.75rem;padding:.28rem 0}
.rcpt-meta-label{color:var(--muted,#6b7280);font-weight:600}
.rcpt-meta-val{color:var(--red,#8B1E2B);font-weight:700}

.rcpt-footer{border-top:1.5px dashed var(--border,#e5e7eb);margin-top:1rem;padding:1rem 1.5rem 1.5rem;text-align:center}
.rcpt-footer-text{font-size:.72rem;color:var(--muted,#6b7280);line-height:1.7}
.rcpt-print-btn{display:inline-flex;align-items:center;gap:.4rem;background:var(--red,#8B1E2B);color:#fff;padding:.65rem 1.5rem;border-radius:50px;font-size:.82rem;font-weight:700;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;margin-top:.9rem}
.rcpt-print-btn:hover{background:#5c131c}
</style>

<div class="ap-page-head">
<div class="sec-head">
  <h2>Appointment Management</h2>
  <div style="display:flex;gap:.6rem">
    <input class="search-bar" id="appt-search" placeholder="Search ID, patient or doctor…" oninput="onSearchInput(this.value)"/>
    <button class="btn-primary" onclick="openModal('modal-create')">+ Create Appointment</button>
  </div>
</div>
</div>

<?php if ($stat_doctor_approved > 0): ?>
<div style="background:linear-gradient(135deg,rgba(63,130,227,0.08),rgba(63,130,227,0.04));border:1.5px solid rgba(63,130,227,0.25);border-radius:14px;padding:0.8rem 1rem;margin-bottom:1rem;display:flex;align-items:center;gap:0.8rem;">
  <div style="width:36px;height:36px;background:var(--blue);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
  </div>
  <div style="flex:1;">
    <div style="font-weight:700;font-size:0.88rem;color:#1d4ed8;">
      <?= $stat_doctor_approved ?> appointment<?= $stat_doctor_approved > 1 ? 's' : '' ?> awaiting your confirmation
    </div>
    <div style="font-size:0.77rem;color:#3b82f6;margin-top:0.1rem;">Doctor has accepted these — confirm them to notify the patient to pay.</div>
  </div>
  <button onclick="filterStatus('DoctorApproved')" style="background:var(--blue);color:#fff;border:none;border-radius:50px;padding:0.4rem 0.9rem;font-size:0.78rem;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;white-space:nowrap;">Review Now</button>
</div>
<?php endif; ?>

<div class="ap-tabs">
  <?php
  $filter_tabs = [
      'All'           => ['label' => 'All',              'count' => null],
      'DoctorApproved'=> ['label' => 'Doctor Approved',  'count' => $stat_doctor_approved ?: null],
      'Pending'       => ['label' => 'Pending',          'count' => $stat_pending ?: null],
      'Confirmed'     => ['label' => 'Confirmed',        'count' => null],
      'Completed'     => ['label' => 'Completed',        'count' => null],
      'Cancelled'     => ['label' => 'Cancelled',        'count' => null],
  ];
  foreach ($filter_tabs as $fk => $fv): ?>
  <button class="ap-tab" id="filter-<?= $fk ?>" onclick="filterStatus('<?= $fk ?>')">
    <?= $fv['label'] ?>
    <?php if ($fv['count']): ?><span class="cnt"><?= $fv['count'] ?></span><?php endif; ?>
  </button>
  <?php endforeach; ?>
</div>

<div class="tbl-wrap">
  <table>
    <thead>
      <tr>
        <th style="width:70px;">ID</th>
        <th>Patient</th><th>Doctor</th><th>Date &amp; Time</th>
        <th>Status</th><th>Payment</th><th>Actions</th>
      </tr>
    </thead>
    <tbody id="appt-tbody">
    <?php
    if ($all_appts && $all_appts->num_rows > 0):
      while ($a = $all_appts->fetch_assoc()):
        $sc = match($a['status']) {
            'Confirmed'      => 'bg-green',
            'Pending'        => 'bg-orange',
            'DoctorApproved' => 'bg-blue',
            'Completed'      => 'bg-blue',
            default          => 'bg-red',
        };
        $pc   = $a['payment_status'] === 'Paid' ? 'bg-green' : 'bg-red';
        $paid = $a['payment_status'] === 'Paid';
        $row_class = $a['status'] === 'DoctorApproved' ? 'row-doctor-approved' : '';
        $receipt_no  = $a['receipt_number'] ?? ('TC-' . strtoupper(substr(md5($a['id']), 0, 8)));
        $paid_at_val = $a['paid_at'] ?? '';
        $fee_val     = floatval($a['consultation_fee'] ?? 0);
    ?>
    <tr data-status="<?= $a['status'] ?>"
        data-search="<?= strtolower('#' . $a['id'] . ' ' . $a['patient_name'] . ' ' . $a['doctor_name']) ?>"
        class="<?= $row_class ?>">
      <td>
        <span class="appt-id-badge">#<?= $a['id'] ?></span>
      </td>
      <td><div style="font-weight:600"><?= htmlspecialchars($a['patient_name']) ?></div></td>
      <td>
        Dr. <?= htmlspecialchars($a['doctor_name']) ?><br/>
        <span style="font-size:.72rem;color:var(--muted)"><?= htmlspecialchars($a['specialty'] ?? '') ?></span>
      </td>
      <td>
        <?= date('M j, Y', strtotime($a['appointment_date'])) ?><br/>
        <span style="font-size:.78rem;color:var(--muted)"><?= date('g:i A', strtotime($a['appointment_time'])) ?></span>
      </td>
      <td>
        <span class="badge <?= $sc ?>">
          <?= $a['status'] === 'DoctorApproved' ? 'Dr. Approved' : $a['status'] ?>
        </span>
        <?php if ($a['status'] === 'DoctorApproved'): ?>
        <div style="font-size:0.67rem;color:#3b82f6;font-weight:600;margin-top:0.2rem;">Needs your confirmation</div>
        <?php endif; ?>
      </td>
      <td>
        <span class="badge <?= $pc ?>"><?= $a['payment_status'] ?></span>
        <?php if ($paid): ?>
        <button class="btn-receipt-sm" style="margin-top:0.3rem;"
          data-appt-id="<?= $a['id'] ?>"
          data-patient="<?= htmlspecialchars($a['patient_name'], ENT_QUOTES) ?>"
          data-doctor="<?= htmlspecialchars($a['doctor_name'], ENT_QUOTES) ?>"
          data-specialty="<?= htmlspecialchars($a['specialty'] ?? '', ENT_QUOTES) ?>"
          data-date="<?= $a['appointment_date'] ?>"
          data-time="<?= substr($a['appointment_time'], 0, 5) ?>"
          data-fee="<?= $fee_val ?>"
          data-receipt="<?= htmlspecialchars($receipt_no, ENT_QUOTES) ?>"
          data-paid-at="<?= htmlspecialchars($paid_at_val, ENT_QUOTES) ?>"
          onclick="handleReceiptClick(this)">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2" style="vertical-align:-1px;margin-right:3px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h1M7 21h10a2 2 0 002-2V7.414a1 1 0 00-.293-.707l-4.414-4.414A1 1 0 0013.586 2H7a2 2 0 00-2 2v15a2 2 0 002 2z"/></svg>Receipt
        </button>
        <?php endif; ?>
      </td>
      <td>
        <div style="display:flex;gap:.35rem;flex-wrap:wrap">
          <?php if ($a['status'] === 'DoctorApproved'): ?>
          <button class="btn-green btn-sm" style="font-weight:800;display:inline-flex;align-items:center;gap:4px;" onclick="quickAction(<?= $a['id'] ?>, 'approve')"><svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Confirm</button>
          <button class="btn-red btn-sm" onclick="quickAction(<?= $a['id'] ?>, 'reject')">Reject</button>

          <?php elseif ($a['status'] === 'Pending'): ?>
          <span style="font-size:0.72rem;color:#3b82f6;font-weight:600;display:inline-flex;align-items:center;gap:4px;"><svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 3"/></svg>Awaiting doctor</span>

          <?php elseif ($a['status'] === 'Confirmed'): ?>
          <button class="btn-orange btn-sm"
                  onclick="openReschedule(<?= $a['id'] ?>, <?= (int)$a['doctor_id'] ?>, '<?= $a['appointment_date'] ?>', '<?= substr($a['appointment_time'],0,5) ?>')">
            Reschedule
          </button>
          <button class="btn-red btn-sm" onclick="quickAction(<?= $a['id'] ?>, 'cancel')">Cancel</button>
          <?php if ($paid): ?>
          <button class="btn-green btn-sm" onclick="quickAction(<?= $a['id'] ?>, 'complete')">Complete</button>
          <?php endif; ?>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php endwhile; else: ?>
    <tr><td colspan="7" class="empty-row">No appointments found.</td></tr>
    <?php endif ?>
    </tbody>
  </table>
</div>

<!-- Pagination controls -->
<div class="pagination-wrap" id="pagination-wrap">
  <div class="pagination-info" id="pagination-info"></div>
  <div class="pagination-btns" id="pagination-btns"></div>
</div>

<!-- Hidden quick-action form -->
<form method="POST" id="quick-form" style="display:none">
  <input type="hidden" name="action"       id="qf-action"/>
  <input type="hidden" name="appt_id"      id="qf-appt-id"/>
  <input type="hidden" name="action_notes" id="qf-notes"/>
  <input type="hidden" name="active_tab"   id="qf-tab"/>
</form>

<!-- Modal: Create Appointment -->
<div class="modal-overlay" id="modal-create">
  <div class="modal">
    <h3>Create Appointment</h3>
    <?php if ($ca_error): ?>
    <div style="background:rgba(195,54,67,.08);border:1px solid rgba(195,54,67,.2);color:#c33643;border-radius:10px;padding:.65rem .9rem;font-size:.82rem;margin-bottom:.9rem;display:flex;align-items:center;gap:7px;"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#c33643" stroke-width="2.4" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg><?= htmlspecialchars($ca_error) ?></div>
    <?php endif ?>
    <form method="POST" id="ca-form">
      <label class="f-label">Patient</label>
      <select name="patient_id" class="f-input" required>
        <option value="">Select patient…</option>
        <?php $pts2 = $conn->query("SELECT id, full_name FROM patients ORDER BY full_name ASC"); while ($r = $pts2->fetch_assoc()): ?>
        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['full_name']) ?></option>
        <?php endwhile ?>
      </select>
      <label class="f-label">Doctor</label>
      <select name="doctor_id" id="ca-doctor" class="f-input" required onchange="onCaDoctorChange()">
        <option value="">Select doctor…</option>
        <?php $docs2 = $conn->query("SELECT id, full_name, specialty FROM doctors WHERE status='active' ORDER BY full_name ASC"); while ($r = $docs2->fetch_assoc()): ?>
        <option value="<?= $r['id'] ?>">Dr. <?= htmlspecialchars($r['full_name']) ?> — <?= htmlspecialchars($r['specialty'] ?? '') ?></option>
        <?php endwhile ?>
      </select>
      <input type="hidden" name="appt_date" id="ca-date"/>
      <label class="f-label">Date <span id="ca-date-hint" style="font-weight:400;color:#9ab0ae;font-size:.75rem;margin-left:.4rem"></span></label>
      <div id="ca-cal-wrap" class="cal-wrap cal-disabled"><div class="cal-placeholder">Select a doctor to see available dates</div></div>
      <div id="ca-date-error" style="display:none;font-size:.75rem;color:#c33643;margin-top:-.4rem;margin-bottom:.5rem"></div>
      <label class="f-label">Time</label>
      <select name="appt_time" id="ca-time" class="f-input" required><option value="">Select a date first…</option></select>
      <div id="ca-client-error" style="display:none;background:rgba(195,54,67,.08);border:1px solid rgba(195,54,67,.2);color:#c33643;border-radius:10px;padding:.6rem .85rem;font-size:.81rem;margin-bottom:.6rem"></div>
      <label class="f-label">Notes</label>
      <textarea name="notes" class="f-input" rows="2" placeholder="Reason for visit…"></textarea>
      <button type="submit" name="create_appt" class="btn-submit">Create Appointment</button>
      <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-create')">Cancel</button>
    </form>
  </div>
</div>

<!-- Modal: Reschedule -->
<div class="modal-overlay" id="modal-reschedule">
  <div class="modal">
    <h3>Reschedule Appointment</h3>
    <?php if ($rs_error): ?>
    <div id="rs-server-error" style="background:rgba(195,54,67,.08);border:1px solid rgba(195,54,67,.2);color:#c33643;border-radius:10px;padding:.65rem .9rem;font-size:.82rem;margin-bottom:.9rem;display:flex;align-items:center;gap:7px;"><svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#c33643" stroke-width="2.4" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg><?= htmlspecialchars($rs_error) ?></div>
    <?php endif ?>
    <form method="POST" id="rs-form">
      <input type="hidden" name="reschedule" value="1"/>
      <input type="hidden" name="appt_id"    id="rs-appt-id"/>
      <input type="hidden" name="active_tab" id="rs-tab"/>
      <input type="hidden" name="new_date"   id="rs-date"/>
      <label class="f-label">New Date <span id="rs-date-hint" style="font-weight:400;color:#9ab0ae;font-size:.75rem;margin-left:.4rem"></span></label>
      <div id="rs-cal-wrap" class="cal-wrap"><div class="cal-placeholder">Loading schedule…</div></div>
      <div id="rs-date-error" style="display:none;font-size:.75rem;color:#c33643;margin-top:-.4rem;margin-bottom:.5rem"></div>
      <label class="f-label">New Time</label>
      <select name="new_time" id="rs-time" class="f-input" required><option value="">Pick a date first…</option></select>
      <div id="rs-client-error" style="display:none;background:rgba(195,54,67,.08);border:1px solid rgba(195,54,67,.2);color:#c33643;border-radius:10px;padding:.6rem .85rem;font-size:.81rem;margin-bottom:.6rem"></div>
      <button type="submit" class="btn-submit">Confirm Reschedule</button>
      <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-reschedule')">Cancel</button>
    </form>
  </div>
</div>

<!-- Modal: Receipt Preview (redesigned) -->
<div class="modal-overlay" id="modal-receipt">
  <div class="modal rcpt-modal" style="width:92%;max-width:560px;">

    <div class="rcpt-hero">
      <button class="rcpt-hero-close" onclick="closeModal('modal-receipt')">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
      <div class="rcpt-brand">
        <svg width="12" height="12" viewBox="0 0 32 32" fill="none"><rect x="1" y="1" width="30" height="30" rx="9" fill="#fff"/><path d="M16 8v16M8 16h16" stroke="var(--red,#8B1E2B)" stroke-width="3.4" stroke-linecap="round"/></svg>
        Tele-Care
      </div>
      <div class="rcpt-title">Payment Receipt</div>
      <div class="rcpt-sub">Official Consultation Receipt</div>
    </div>

    <div class="rcpt-body">
      <div class="rcpt-status-bar">
        <div class="rcpt-status-icon">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div>
          <div class="rcpt-status-title">Payment Successful</div>
          <div id="rm-paid-at" class="rcpt-status-date"></div>
        </div>
      </div>

      <div class="rcpt-no-wrap">
        <div class="rcpt-no-label">Receipt No.</div>
        <div id="rm-receipt-no" class="rcpt-no-val"></div>
      </div>

      <hr class="rcpt-divider"/>
      <div id="rm-rows"></div>

      <div class="rcpt-amount-box">
        <div class="rcpt-amount-label">Total Amount Paid</div>
        <div id="rm-amount" class="rcpt-amount-val"></div>
        <div><span class="rcpt-paid-pill">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          PAID
        </span></div>
      </div>

      <div class="rcpt-meta-row"><span class="rcpt-meta-label">Payment via</span><span class="rcpt-meta-val">PayMongo (GCash / Card)</span></div>
      <div class="rcpt-meta-row" style="padding-bottom:.5rem"><span class="rcpt-meta-label">Appointment ID</span><span id="rm-appt-id" class="rcpt-meta-val"></span></div>
    </div>

    <div class="rcpt-footer">
      <div class="rcpt-footer-text">
        Thank you for choosing TELE-CARE.<br/>
        <strong>This serves as official proof of payment.</strong>
      </div>
      <button onclick="printReceipt()" class="rcpt-print-btn">
        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Print / Save PDF
      </button>
    </div>
  </div>
</div>

<div id="print-receipt-area" style="display:none;"></div>

<script>
const DOCTOR_SCHEDULES = <?= json_encode($doctor_schedules) ?>;
const DAY_NAMES   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const TODAY_STR   = new Date().toISOString().slice(0, 10);

// ── Pagination State ──────────────────────────────────────────────────────────
const PER_PAGE  = 10;
let _activeTab  = '<?= $active_tab ?>';
let _searchQuery = '';
let _currentPage = 1;

// All rows from DOM (captured once)
const _allRows = Array.from(document.querySelectorAll('#appt-tbody tr[data-status]'));

function getVisibleRows() {
  return _allRows.filter(r => {
    const matchTab    = _activeTab === 'All' || r.dataset.status === _activeTab;
    const matchSearch = !_searchQuery || r.dataset.search.includes(_searchQuery.toLowerCase());
    return matchTab && matchSearch;
  });
}

function renderTable() {
  const visible = getVisibleRows();
  const totalPages = Math.max(1, Math.ceil(visible.length / PER_PAGE));
  if (_currentPage > totalPages) _currentPage = totalPages;
  const start = (_currentPage - 1) * PER_PAGE;
  const end   = start + PER_PAGE;

  // Show/hide rows
  _allRows.forEach(r => r.style.display = 'none');
  visible.forEach((r, i) => {
    r.style.display = (i >= start && i < end) ? '' : 'none';
  });

  // Empty state
  const existingEmpty = document.getElementById('pg-empty-row');
  if (existingEmpty) existingEmpty.remove();
  if (visible.length === 0) {
    const tr = document.createElement('tr');
    tr.id = 'pg-empty-row';
    tr.innerHTML = `<td colspan="7" class="empty-row">No appointments found.</td>`;
    document.getElementById('appt-tbody').appendChild(tr);
  }

  renderPagination(visible.length, totalPages);
}

function renderPagination(total, totalPages) {
  const info = document.getElementById('pagination-info');
  const btns = document.getElementById('pagination-btns');
  const start = total === 0 ? 0 : (_currentPage - 1) * PER_PAGE + 1;
  const end   = Math.min(_currentPage * PER_PAGE, total);

  info.textContent = total === 0
    ? 'No results'
    : `Showing ${start}–${end} of ${total} appointment${total !== 1 ? 's' : ''}`;

  btns.innerHTML = '';
  if (totalPages <= 1) return;

  // Prev button
  const prev = document.createElement('button');
  prev.className = 'pg-btn';
  prev.textContent = '‹ Prev';
  prev.disabled = _currentPage === 1;
  prev.onclick = () => goPage(_currentPage - 1);
  btns.appendChild(prev);

  // Page number buttons with ellipsis
  const pages = buildPageList(_currentPage, totalPages);
  pages.forEach(p => {
    if (p === '…') {
      const sp = document.createElement('span');
      sp.className = 'pg-ellipsis';
      sp.textContent = '…';
      btns.appendChild(sp);
    } else {
      const btn = document.createElement('button');
      btn.className = 'pg-btn' + (p === _currentPage ? ' active' : '');
      btn.textContent = p;
      btn.onclick = () => goPage(p);
      btns.appendChild(btn);
    }
  });

  // Next button
  const next = document.createElement('button');
  next.className = 'pg-btn';
  next.textContent = 'Next ›';
  next.disabled = _currentPage === totalPages;
  next.onclick = () => goPage(_currentPage + 1);
  btns.appendChild(next);
}

function buildPageList(current, total) {
  if (total <= 7) return Array.from({length: total}, (_, i) => i + 1);
  const pages = [];
  pages.push(1);
  if (current > 3) pages.push('…');
  for (let p = Math.max(2, current - 1); p <= Math.min(total - 1, current + 1); p++) {
    pages.push(p);
  }
  if (current < total - 2) pages.push('…');
  pages.push(total);
  return pages;
}

function goPage(p) {
  _currentPage = p;
  renderTable();
  // Scroll table into view smoothly
  document.querySelector('.tbl-wrap').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Filter & Search ───────────────────────────────────────────────────────────
function filterStatus(status) {
  _activeTab   = status;
  _currentPage = 1;
  document.querySelectorAll('[id^=filter-]').forEach(b => {
    b.classList.toggle('active', b.id === 'filter-' + status);
  });
  renderTable();
}

function onSearchInput(val) {
  _searchQuery = val.trim();
  _currentPage = 1;
  renderTable();
}

// ── Calendar helpers (unchanged) ──────────────────────────────────────────────
function fmt12(hhmm) {
  const [h, m] = hhmm.split(':').map(Number);
  return `${h % 12 || 12}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`;
}
function hoursInRange(start, end) {
  const slots = [];
  let h = parseInt(start), eh = parseInt(end);
  for (; h < eh; h++) slots.push(String(h).padStart(2,'0') + ':00');
  return slots;
}
function availableDaySet(doctorId) {
  return new Set((DOCTOR_SCHEDULES[doctorId] || []).map(r => r.day));
}
function dateIsAllowed(dateStr, doctorId) {
  if (!doctorId || !dateStr || dateStr < TODAY_STR) return false;
  const dayName = DAY_NAMES[new Date(dateStr + 'T00:00:00').getDay()];
  return availableDaySet(doctorId).has(dayName);
}
function buildScheduleLegendHTML(doctorId) {
  const slots = DOCTOR_SCHEDULES[doctorId] || [];
  if (!slots.length) return '';
  const byDay = {};
  slots.forEach(s => (byDay[s.day] = byDay[s.day] || []).push(`${fmt12(s.start)} – ${fmt12(s.end)}`));
  return '<div class="cal-legend-title">Available Hours</div>' +
    Object.entries(byDay).map(([day, times]) =>
      `<div><strong style="display:inline-block;min-width:2.6rem">${day.slice(0,3)}</strong>${times.join(' &nbsp;|&nbsp; ')}</div>`
    ).join('');
}
function populateTimeSelect(selEl, errEl, dateStr, doctorId, preselectHHMM = null) {
  selEl.innerHTML = '<option value="">Select a time…</option>';
  if (errEl) { errEl.textContent = ''; errEl.style.display = 'none'; }
  if (!dateStr || !doctorId) return 0;
  const dayName   = DAY_NAMES[new Date(dateStr + 'T00:00:00').getDay()];
  const schedules = (DOCTOR_SCHEDULES[doctorId] || []).filter(s => s.day === dayName);
  if (!schedules.length) return 0;
  const now        = new Date();
  const isToday    = dateStr === TODAY_STR;
  const cutoffHour = isToday ? now.getHours() + (now.getMinutes() > 0 ? 1 : 0) : -1;
  let added = 0;
  schedules.forEach(s => {
    hoursInRange(s.start, s.end).forEach(slot => {
      if (isToday && parseInt(slot) <= cutoffHour) return;
      const opt = document.createElement('option');
      opt.value       = slot + ':00';
      opt.textContent = fmt12(slot);
      if (preselectHHMM && slot === preselectHHMM) opt.selected = true;
      selEl.appendChild(opt);
      added++;
    });
  });
  if (added === 0 && errEl) {
    selEl.innerHTML = '<option value="">No slots remaining today</option>';
    errEl.textContent = 'All slots for this date have passed. Choose a future date.';
    errEl.style.display = 'block';
  }
  return added;
}

class CalendarPicker {
  constructor(options) {
    this.wrapper      = document.getElementById(options.wrapperId);
    this.hiddenInput  = document.getElementById(options.hiddenInputId);
    this.timeSel      = document.getElementById(options.timeSelId);
    this.errEl        = options.errElId ? document.getElementById(options.errElId) : null;
    this.onSelect     = options.onSelect || null;
    this.minDate      = options.minDateStr || TODAY_STR;
    this.doctorId     = null;
    this.selectedDate = null;
    const now = new Date();
    this.viewYear  = now.getFullYear();
    this.viewMonth = now.getMonth();
    this.wrapper._calPicker = this;
    this._render();
  }
  setDoctor(doctorId, preselectDate = null) {
    this.doctorId     = doctorId || null;
    this.selectedDate = null;
    this.hiddenInput.value = '';
    if (this.timeSel) this.timeSel.innerHTML = '<option value="">Select a date first…</option>';
    if (this.errEl)   { this.errEl.textContent = ''; this.errEl.style.display = 'none'; }
    if (preselectDate) { const d = new Date(preselectDate+'T00:00:00'); this.viewYear=d.getFullYear(); this.viewMonth=d.getMonth(); }
    else { const now=new Date(); this.viewYear=now.getFullYear(); this.viewMonth=now.getMonth(); }
    this._render();
  }
  selectDate(dateStr, preselectTimHHMM = null) {
    if (!dateStr) return;
    const d = new Date(dateStr + 'T00:00:00');
    this.viewYear=d.getFullYear(); this.viewMonth=d.getMonth();
    this.selectedDate=dateStr; this.hiddenInput.value=dateStr;
    this._render();
    if (this.timeSel) populateTimeSelect(this.timeSel, this.errEl, dateStr, this.doctorId, preselectTimHHMM);
    if (this.onSelect) this.onSelect(dateStr);
  }
  _clickDay(dateStr) {
    this.selectedDate=dateStr; this.hiddenInput.value=dateStr;
    if (this.errEl) { this.errEl.textContent=''; this.errEl.style.display='none'; }
    this._render();
    if (this.timeSel) populateTimeSelect(this.timeSel, this.errEl, dateStr, this.doctorId);
    if (this.onSelect) this.onSelect(dateStr);
  }
  prevMonth() { this.viewMonth--; if(this.viewMonth<0){this.viewMonth=11;this.viewYear--;} this._render(); }
  nextMonth() { this.viewMonth++; if(this.viewMonth>11){this.viewMonth=0;this.viewYear++;} this._render(); }
  _render() {
    const { viewYear:yr, viewMonth:mo, doctorId, selectedDate, minDate } = this;
    const availDays = doctorId ? availableDaySet(doctorId) : new Set();
    const minD      = new Date(minDate+'T00:00:00');
    const atMin     = yr===minD.getFullYear() && mo===minD.getMonth();
    const firstDow  = new Date(yr,mo,1).getDay();
    const daysInMonth = new Date(yr,mo+1,0).getDate();
    const todayStr  = TODAY_STR;
    let cells = '';
    for(let i=0;i<firstDow;i++) cells+=`<div class="cal-cell empty"></div>`;
    for(let d=1;d<=daysInMonth;d++){
      const ds  = `${yr}-${String(mo+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const dow = DAY_NAMES[new Date(ds+'T00:00:00').getDay()];
      const isPast    = ds < minDate;
      const isBlocked = !doctorId || (!isPast && !availDays.has(dow));
      const isToday   = ds === todayStr;
      const isSel     = ds === selectedDate;
      let cls = 'cal-cell';
      if(isPast) cls+=' past';
      else if(isBlocked) cls+=' blocked';
      else cls+=' available';
      if(isToday) cls+=' today';
      if(isSel) cls+=' selected';
      const clickable = !isPast && !isBlocked;
      const onclick   = clickable ? `onclick="document.getElementById('${this.wrapper.id}')._calPicker._clickDay('${ds}')"` : '';
      let title = '';
      if(!isPast && isBlocked && doctorId){const avail=[...availDays].join(', ')||'none';title=`title="Doctor unavailable on ${dow}s. Available: ${avail}"`;}
      cells+=`<div class="${cls}" ${onclick} ${title}>${d}</div>`;
    }
    const legendHTML = doctorId ? buildScheduleLegendHTML(doctorId) : '';
    this.wrapper.innerHTML = `
      <div class="cal-header">
        <button type="button" class="cal-nav" ${atMin?'disabled':''}
                onclick="document.getElementById('${this.wrapper.id}')._calPicker.prevMonth()">&#8249;</button>
        <span>${MONTH_NAMES[mo]} ${yr}</span>
        <button type="button" class="cal-nav"
                onclick="document.getElementById('${this.wrapper.id}')._calPicker.nextMonth()">&#8250;</button>
      </div>
      <div class="cal-grid">
        <div class="cal-day-name">Su</div><div class="cal-day-name">Mo</div><div class="cal-day-name">Tu</div>
        <div class="cal-day-name">We</div><div class="cal-day-name">Th</div><div class="cal-day-name">Fr</div>
        <div class="cal-day-name">Sa</div>${cells}
      </div>
      ${legendHTML?`<div class="cal-legend">${legendHTML}</div>`:''}
    `;
    this.wrapper._calPicker = this;
    if(!doctorId) this.wrapper.classList.add('cal-disabled');
    else this.wrapper.classList.remove('cal-disabled');
  }
}

const caCal = new CalendarPicker({ wrapperId:'ca-cal-wrap', hiddenInputId:'ca-date', timeSelId:'ca-time', errElId:'ca-date-error', minDateStr:TODAY_STR });
const rsCal = new CalendarPicker({ wrapperId:'rs-cal-wrap', hiddenInputId:'rs-date', timeSelId:'rs-time', errElId:'rs-date-error', minDateStr:TODAY_STR });

function onCaDoctorChange() {
  const sel = document.getElementById('ca-doctor');
  const did = parseInt(sel.value) || null;
  caCal.setDoctor(did);
  document.getElementById('ca-client-error').style.display = 'none';
}

document.getElementById('ca-form').addEventListener('submit', function(e) {
  const date=document.getElementById('ca-date').value, time=document.getElementById('ca-time').value;
  const did=parseInt(document.getElementById('ca-doctor').value)||null;
  const errEl=document.getElementById('ca-client-error');
  errEl.style.display='none';
  if(!did){e.preventDefault();errEl.textContent='Please select a doctor.';errEl.style.display='block';return;}
  if(!date){e.preventDefault();errEl.textContent='Please select a date from the calendar.';errEl.style.display='block';return;}
  if(!time){e.preventDefault();errEl.textContent='Please select a time slot.';errEl.style.display='block';return;}
  if(new Date(date+'T'+time)<=new Date()){e.preventDefault();errEl.textContent='Cannot book an appointment in the past.';errEl.style.display='block';return;}
  if(!dateIsAllowed(date,did)){e.preventDefault();errEl.textContent="Selected date is not within the doctor's schedule.";errEl.style.display='block';}
});

function openReschedule(apptId, doctorId, currentDate, currentTime) {
  document.getElementById('rs-appt-id').value = apptId;
  document.getElementById('rs-tab').value     = _activeTab;
  document.getElementById('rs-client-error').style.display = 'none';
  document.getElementById('rs-date-error').style.display   = 'none';
  const srvErr = document.getElementById('rs-server-error');
  if(srvErr) srvErr.style.display = 'none';
  rsCal.setDoctor(doctorId);
  rsCal.selectDate(currentDate, currentTime.substring(0,5));
  openModal('modal-reschedule');
}

document.getElementById('rs-form').addEventListener('submit', function(e) {
  const date=document.getElementById('rs-date').value, time=document.getElementById('rs-time').value;
  const errEl=document.getElementById('rs-client-error');
  errEl.style.display='none';
  if(!date){e.preventDefault();errEl.textContent='Please select a date from the calendar.';errEl.style.display='block';return;}
  if(!time){e.preventDefault();errEl.textContent='Please select a time slot.';errEl.style.display='block';return;}
  if(new Date(date+'T'+time)<=new Date()){e.preventDefault();errEl.textContent='Cannot reschedule to a past date or time.';errEl.style.display='block';return;}
});

function quickAction(id, action) {
  document.getElementById('qf-appt-id').value = id;
  document.getElementById('qf-action').value  = action;
  document.getElementById('qf-notes').value   = '';
  document.getElementById('qf-tab').value     = _activeTab;
  document.getElementById('quick-form').submit();
}

// ── Modal open/close (in case not already defined globally in style.css/sidebar.php) ──
if (typeof openModal !== 'function') {
  window.openModal = function(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
  };
}
if (typeof closeModal !== 'function') {
  window.closeModal = function(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
  };
}

// ── Receipt ───────────────────────────────────────────────────────────────────
function openReceiptModal(apptId, patient, doctor, specialty, apptDate, apptTime, amount, receiptNo, paidAt) {
  document.getElementById('rm-receipt-no').textContent = receiptNo;
  const paidDate = paidAt ? new Date(paidAt) : new Date();
  document.getElementById('rm-paid-at').textContent = paidDate.toLocaleString('en-PH', {
    month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit'
  });
  document.getElementById('rm-appt-id').textContent = '#' + apptId;
  document.getElementById('rm-amount').textContent = '₱' + parseFloat(amount).toLocaleString('en-PH', {
    minimumFractionDigits: 2, maximumFractionDigits: 2
  });
  const apptDateFmt = new Date(apptDate + 'T00:00:00').toLocaleDateString('en-PH', {
    month: 'long', day: 'numeric', year: 'numeric'
  });
  const [h, m]  = apptTime.split(':').map(Number);
  const timeFmt = `${h % 12 || 12}:${String(m).padStart(2,'0')} ${h >= 12 ? 'PM' : 'AM'}`;
  const rows = [
    ['Patient',     patient],
    ['Doctor',      'Dr. ' + doctor],
    ['Specialty',   specialty || '—'],
    ['Appointment', apptDateFmt + ' · ' + timeFmt],
  ];
  document.getElementById('rm-rows').innerHTML = rows.map(([label, val]) => `
    <div class="rcpt-detail-row">
      <span class="rcpt-detail-label">${label}</span>
      <span class="rcpt-detail-val">${val}</span>
    </div>
  `).join('');
  openModal('modal-receipt');
}

function printReceipt() {
  const receiptNo  = document.getElementById('rm-receipt-no').textContent;
  const paidAt     = document.getElementById('rm-paid-at').textContent;
  const apptId     = document.getElementById('rm-appt-id').textContent;
  const amount     = document.getElementById('rm-amount').textContent;
  const rowsHTML   = document.getElementById('rm-rows').innerHTML;

  const printWindow = window.open('', '_blank', 'width=500,height=780');

  printWindow.document.write('<!DOCTYPE html><html><head>' +
    '<title>Payment Receipt</title>' +
    '<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=DM+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">' +
    '<style>' +
    '*{box-sizing:border-box;}' +
    'body{font-family:"DM Sans",sans-serif;margin:0;padding:20px;background:#f4f4f4;color:#1a1a1a;}' +
    '.card{max-width:440px;margin:0 auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 18px rgba(0,0,0,.08);}' +
    '.hdr{background:linear-gradient(135deg,#8B1E2B,#5c131c);color:#fff;text-align:center;padding:1.7rem 1.2rem 1.3rem;}' +
    '.brand{font-size:.68rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;opacity:.8;margin-bottom:.5rem;}' +
    '.title{font-family:"Playfair Display",Georgia,serif;font-size:1.5rem;font-weight:800;margin-bottom:.2rem;}' +
    '.sub{font-size:.76rem;opacity:.8;}' +
    '.body{padding:1.5rem 1.3rem 0;}' +
    '.success-bar{display:flex;align-items:center;gap:.7rem;background:rgba(34,197,94,.08);border:1.5px solid rgba(34,197,94,.25);border-radius:14px;padding:.75rem 1rem;margin-bottom:1.2rem;}' +
    '.success-icon{width:32px;height:32px;background:linear-gradient(135deg,#16a34a,#15803d);border-radius:50%;flex-shrink:0;}' +
    '.success-title{font-weight:700;font-size:.88rem;color:#15803d;}' +
    '.success-date{font-size:.72rem;color:#16a34a;opacity:.85;}' +
    '.rcpt-no-wrap{text-align:center;margin-bottom:1rem;}' +
    '.rcpt-no-label{font-size:.63rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#6b7280;}' +
    '.rcpt-no-val{font-size:1.1rem;font-weight:800;color:#8B1E2B;letter-spacing:.04em;}' +
    '.dashed{border:none;border-top:1.5px dashed #e5e7eb;margin:.9rem 0;}' +
    '.detail-row{display:flex;justify-content:space-between;font-size:.82rem;padding:.4rem 0;}' +
    '.detail-label{color:#6b7280;font-weight:600;}' +
    '.detail-val{font-weight:700;text-align:right;}' +
    '.amount-box{background:#faf5f5;border:1.5px solid rgba(139,30,43,.12);border-radius:16px;padding:1.1rem;text-align:center;margin:1.1rem 0;}' +
    '.amount-label{font-size:.68rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6b7280;margin-bottom:.3rem;}' +
    '.amount-val{font-size:2rem;font-weight:800;color:#8B1E2B;}' +
    '.paid-badge{display:inline-flex;align-items:center;gap:.3rem;background:#16a34a;color:#fff;border-radius:50px;padding:.22rem .85rem;font-size:.7rem;font-weight:700;margin-top:.5rem;}' +
    '.meta-row{display:flex;justify-content:space-between;font-size:.75rem;padding:.28rem 0;}' +
    '.meta-label{color:#6b7280;font-weight:600;}' +
    '.meta-val{color:#8B1E2B;font-weight:700;}' +
    '.footer{border-top:1.5px dashed #e5e7eb;margin-top:.9rem;padding:1rem 1.3rem 1.5rem;text-align:center;font-size:.72rem;color:#6b7280;line-height:1.7;}' +
    '@media print{body{background:#fff;padding:0;}.card{box-shadow:none;}}' +
    '</style></head><body>' +
    '<div class="card">' +
      '<div class="hdr">' +
        '<div class="brand">Tele-Care</div>' +
        '<div class="title">Payment Receipt</div>' +
        '<div class="sub">Official Consultation Receipt</div>' +
      '</div>' +
      '<div class="body">' +
        '<div class="success-bar">' +
          '<div class="success-icon"></div>' +
          '<div>' +
            '<div class="success-title">Payment Successful</div>' +
            '<div class="success-date" id="pw-paid-at"></div>' +
          '</div>' +
        '</div>' +
        '<div class="rcpt-no-wrap">' +
          '<div class="rcpt-no-label">Receipt No.</div>' +
          '<div class="rcpt-no-val" id="pw-receipt-no"></div>' +
        '</div>' +
        '<hr class="dashed"/>' +
        '<div id="detail-rows"></div>' +
        '<div class="amount-box">' +
          '<div class="amount-label">Total Amount Paid</div>' +
          '<div class="amount-val" id="pw-amount"></div>' +
          '<div><span class="paid-badge">PAID</span></div>' +
        '</div>' +
        '<div class="meta-row"><span class="meta-label">Payment via</span><span class="meta-val">PayMongo (GCash / Card)</span></div>' +
        '<div class="meta-row" style="margin-bottom:.5rem"><span class="meta-label">Appointment ID</span><span class="meta-val" id="pw-appt-id"></span></div>' +
      '</div>' +
      '<div class="footer">Thank you for choosing TELE-CARE.<br/><strong>This serves as official proof of payment.</strong></div>' +
    '</div>' +
    '<' + '/body>' + '<' + '/html>');
  printWindow.document.close();

  printWindow.document.getElementById('pw-receipt-no').textContent = receiptNo;
  printWindow.document.getElementById('pw-paid-at').textContent    = paidAt;
  printWindow.document.getElementById('pw-amount').textContent     = amount;
  printWindow.document.getElementById('pw-appt-id').textContent    = apptId;

  const rowsEl = printWindow.document.getElementById('detail-rows');
  rowsEl.innerHTML = rowsHTML;
  rowsEl.querySelectorAll('.rcpt-detail-row').forEach(function (row) {
    row.className = 'detail-row';
    const label = row.querySelector('.rcpt-detail-label');
    const val   = row.querySelector('.rcpt-detail-val');
    if (label) label.className = 'detail-label';
    if (val)   val.className   = 'detail-val';
  });

  printWindow.onload = function () {
    setTimeout(function () { printWindow.print(); printWindow.close(); }, 300);
  };
}

function handleReceiptClick(btn) {
  openReceiptModal(
    btn.dataset.apptId, btn.dataset.patient, btn.dataset.doctor,
    btn.dataset.specialty, btn.dataset.date, btn.dataset.time,
    btn.dataset.fee, btn.dataset.receipt, btn.dataset.paidAt
  );
}

// ── Boot ──────────────────────────────────────────────────────────────────────
filterStatus(_activeTab);  // sets tab highlight + calls renderTable()

<?php if ($rs_open): ?>openModal('modal-reschedule');<?php endif ?>
<?php if ($ca_open): ?>openModal('modal-create');<?php endif ?>
</script>

<?php require_once 'includes/footer.php'; ?>