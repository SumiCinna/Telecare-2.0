<?php
// admin/hmo_provider.php — 2. HMO Provider Management (one provider: details, agents, participating doctors, history)
require_once 'includes/hmo_admin.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$load = function () use ($conn, $id) { $st = $conn->prepare("SELECT * FROM hmo_providers WHERE id = ?"); $st->bind_param('i', $id); $st->execute(); return $st->get_result()->fetch_assoc(); };
$p = $load();
if (!$p) hmo_admin_back('hmo_providers.php', 'Provider not found.', true);
$self = 'hmo_provider.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hmo_admin_csrf_ok()) hmo_admin_back($self, 'Your session expired. Please try again.', true);
    $act = (string)($_POST['action'] ?? '');

    if ($act === 'details') {
        $name  = trim((string)($_POST['name'] ?? ''));
        $code  = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_POST['provider_code'] ?? '')));
        $lic   = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string)($_POST['license_no'] ?? '')));
        $chan  = mb_substr(trim((string)($_POST['support_channels'] ?? '')), 0, 255);
        $tele  = !empty($_POST['teleconsult_available']) ? 1 : 0;
        $eff   = hmo_admin_date($_POST['effective_from'] ?? '');
        $state = (string)($_POST['state'] ?? 'Active');
        $why   = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($_POST['hold_reason'] ?? ''))), 0, 255);
        $contact = mb_substr(trim((string)($_POST['contact_person'] ?? '')), 0, 100);
        $phone = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 30);
        $email = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 100);
        if ($name === '') hmo_admin_back($self, 'Provider name is required.', true);
        if (!in_array($state, ['Active', 'Inactive', 'Suspended'], true)) hmo_admin_back($self, 'Choose a status.', true);
        if ($state === 'Suspended' && $why === '') hmo_admin_back($self, 'Write the reason for suspending this provider (for example: unpaid bills).', true);
        $status = $state === 'Inactive' ? 'Archived' : 'Active';
        $hold = $state === 'Suspended' ? 1 : 0;
        $holdSince = $hold ? ((int)$p['on_hold'] === 1 && $p['hold_since'] ? $p['hold_since'] : date('Y-m-d H:i:s')) : null;
        $whyN = $hold ? $why : null; $codeN = $code !== '' ? $code : null; $licN = $lic !== '' ? substr($lic, 0, 30) : null;
        $chanN = $chan !== '' ? $chan : null;
        $old = ['name' => $p['name'], 'code' => $p['provider_code'], 'license' => $p['license_no'] ?? null, 'state' => hmo_provider_state($p), 'hold_reason' => $p['hold_reason'],
                'teleconsult' => (int)$p['teleconsult_available'], 'effective_from' => $p['effective_from'], 'channels' => $p['support_channels'],
                'contact' => $p['contact_person'], 'phone' => $p['phone'], 'email' => $p['email']];
        try {
            $st = $conn->prepare("UPDATE hmo_providers SET name=?, provider_code=?, license_no=?, status=?, on_hold=?, hold_reason=?, hold_since=?, support_channels=?,
                                  teleconsult_available=?, effective_from=?, contact_person=?, phone=?, email=? WHERE id=?");
            $st->bind_param('ssssisssissssi', $name, $codeN, $licN, $status, $hold, $whyN, $holdSince, $chanN, $tele, $eff, $contact, $phone, $email, $id);
            $st->execute();
        } catch (Throwable $e) { hmo_admin_back($self, 'Could not save (is the name already used?).', true); }
        $new = ['name' => $name, 'code' => $codeN, 'license' => $licN, 'state' => $state, 'hold_reason' => $whyN, 'teleconsult' => $tele, 'effective_from' => $eff,
                'channels' => $chanN, 'contact' => $contact, 'phone' => $phone, 'email' => $email];
        $action = ($old['state'] !== $state) ? 'status_change' : 'update';
        hmo_admin_audit($conn, $admin_id, $action, 'hmo_provider', $id, $old, $new);
        hmo_admin_back($self, 'Provider saved.');
    }

    if ($act === 'add_agent') {
        $n = mb_substr(trim((string)($_POST['agent_name'] ?? '')), 0, 120);
        if ($n === '') hmo_admin_back($self . '#agents', 'Agent name is required.', true);
        $role = mb_substr(trim((string)($_POST['role_title'] ?? '')), 0, 80); $ph = mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 40);
        $em = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 120); $ch = mb_substr(trim((string)($_POST['channel'] ?? '')), 0, 60);
        $no = mb_substr(trim((string)($_POST['notes'] ?? '')), 0, 255); $prim = !empty($_POST['is_primary']) ? 1 : 0;
        if ($prim) { $u = $conn->prepare("UPDATE hmo_provider_contacts SET is_primary = 0 WHERE provider_id = ?"); $u->bind_param('i', $id); $u->execute(); }
        $st = $conn->prepare("INSERT INTO hmo_provider_contacts (provider_id, agent_name, role_title, phone, email, channel, notes, is_primary) VALUES (?,?,?,?,?,?,?,?)");
        $st->bind_param('issssssi', $id, $n, $role, $ph, $em, $ch, $no, $prim); $st->execute();
        hmo_admin_audit($conn, $admin_id, 'create', 'hmo_contact', $id, null, ['agent' => $n, 'role' => $role, 'phone' => $ph, 'email' => $em, 'channel' => $ch, 'primary' => $prim]);
        hmo_admin_back($self . '#agents', 'Agent added.');
    }
    if ($act === 'del_agent') {
        $aid = (int)($_POST['agent_id'] ?? 0);
        $q = $conn->prepare("SELECT agent_name, role_title, phone, email FROM hmo_provider_contacts WHERE id = ? AND provider_id = ?"); $q->bind_param('ii', $aid, $id); $q->execute();
        if ($old = $q->get_result()->fetch_assoc()) {
            $d = $conn->prepare("DELETE FROM hmo_provider_contacts WHERE id = ? AND provider_id = ?"); $d->bind_param('ii', $aid, $id); $d->execute();
            hmo_admin_audit($conn, $admin_id, 'delete', 'hmo_contact', $id, $old, null);
        }
        hmo_admin_back($self . '#agents', 'Agent removed.');
    }

    if ($act === 'doctors') {
        $ids = array_values(array_unique(array_map('intval', (array)($_POST['doctor_ids'] ?? []))));
        $oldIds = []; $r = $conn->query("SELECT doctor_id FROM hmo_provider_doctors WHERE provider_id = " . (int)$id); while ($r && ($x = $r->fetch_row())) $oldIds[] = (int)$x[0];
        $conn->begin_transaction();
        $conn->query("DELETE FROM hmo_provider_doctors WHERE provider_id = " . (int)$id);
        if ($ids) { $st = $conn->prepare("INSERT INTO hmo_provider_doctors (provider_id, doctor_id) VALUES (?, ?)"); foreach ($ids as $d) { $st->bind_param('ii', $id, $d); $st->execute(); } }
        $conn->commit();
        sort($oldIds); sort($ids);
        hmo_admin_audit($conn, $admin_id, 'update', 'hmo_doctors', $id, ['doctor_ids' => $oldIds], ['doctor_ids' => $ids]);
        hmo_admin_back($self . '#doctors', $ids ? 'Participation saved: only the selected doctors accept this HMO.' : 'No restriction: every doctor accepts this HMO.');
    }
    hmo_admin_back($self, 'Unknown action.', true);
}

$csrf = hmo_admin_csrf();
$agents = $conn->query("SELECT * FROM hmo_provider_contacts WHERE provider_id = " . (int)$id . " ORDER BY is_primary DESC, agent_name")->fetch_all(MYSQLI_ASSOC);
$sel = []; $r = $conn->query("SELECT doctor_id FROM hmo_provider_doctors WHERE provider_id = " . (int)$id); while ($r && ($x = $r->fetch_row())) $sel[(int)$x[0]] = true;
$doctors = [];
try { $r = $conn->query("SELECT id, full_name, department, clinic_name FROM doctors WHERE status = 'active' ORDER BY clinic_name, full_name"); $doctors = $r ? $r->fetch_all(MYSQLI_ASSOC) : []; } catch (Throwable $e) { }
$byClinic = []; foreach ($doctors as $d) $byClinic[$d['clinic_name'] ?: 'Main clinic'][] = $d;
$hist = $conn->query("SELECT l.*, a.full_name AS admin_name FROM audit_logs l LEFT JOIN admins a ON a.id = l.admin_id
                      WHERE l.entity_id = " . (int)$id . " AND l.entity_type IN ('hmo_provider','hmo_contact','hmo_rule','hmo_doctors') ORDER BY l.id DESC LIMIT 40");
$hist = $hist ? $hist->fetch_all(MYSQLI_ASSOC) : [];
$state = hmo_provider_state($p);

hmo_admin_head($p['name'], 'pos-hmo', '<a class="hx-btn ghost" href="hmo_providers.php">← All providers</a>');
?>
<div class="hx-card">
  <h3>Provider details <?= hmo_state_badge($state) ?></h3>
  <div class="sub">Only mark a provider Active after confirming the clinic really participates with them.</div>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"/><input type="hidden" name="id" value="<?= $id ?>"/><input type="hidden" name="action" value="details"/>
    <div class="hx-grid3">
      <div><label class="hx-lbl">Provider name</label><input class="hx-in" name="name" maxlength="100" value="<?= h($p['name']) ?>" required/></div>
      <div><label class="hx-lbl">Provider code</label><input class="hx-in" name="provider_code" maxlength="20" value="<?= h($p['provider_code'] ?? '') ?>" placeholder="e.g. MAXI"/></div>
      <div><label class="hx-lbl">Insurance Commission license no.</label><input class="hx-in" name="license_no" maxlength="30" value="<?= h($p['license_no'] ?? '') ?>"/></div>
      <div><label class="hx-lbl">Status</label>
        <select class="hx-in" name="state" id="st" onchange="document.getElementById('why').style.display=this.value==='Suspended'?'block':'none'">
          <?php foreach (['Active', 'Inactive', 'Suspended'] as $o): ?><option <?= $state === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach; ?></select></div>
      <div><label class="hx-lbl">Effective from</label><input class="hx-in" type="date" name="effective_from" value="<?= h($p['effective_from'] ?? '') ?>"/></div>
      <div><label class="hx-lbl">Teleconsultation</label><label class="hx-checks"><span><input type="checkbox" name="teleconsult_available" value="1" <?= (int)$p['teleconsult_available'] === 1 ? 'checked' : '' ?>/> Available for this HMO</span></label></div>
    </div>
    <div id="why" style="display:<?= $state === 'Suspended' ? 'block' : 'none' ?>;margin-top:.6rem"><label class="hx-lbl">Reason for suspension (patients can't use this HMO; staff can't approve its cases)</label>
      <input class="hx-in" name="hold_reason" maxlength="255" value="<?= h($p['hold_reason'] ?? '') ?>" placeholder="e.g. Unpaid bills to the clinic"/></div>
    <div class="hx-grid3" style="margin-top:.6rem">
      <div><label class="hx-lbl">Main contact person</label><input class="hx-in" name="contact_person" maxlength="100" value="<?= h($p['contact_person'] ?? '') ?>"/></div>
      <div><label class="hx-lbl">Main phone</label><input class="hx-in" name="phone" maxlength="30" value="<?= h($p['phone'] ?? '') ?>"/></div>
      <div><label class="hx-lbl">Main email</label><input class="hx-in" name="email" maxlength="100" value="<?= h($p['email'] ?? '') ?>"/></div>
    </div>
    <label class="hx-lbl" style="margin-top:.6rem">Official support channels</label>
    <input class="hx-in" name="support_channels" maxlength="255" value="<?= h($p['support_channels'] ?? '') ?>" placeholder="e.g. Provider hotline (02) 1234-5678; provider portal link; verification email"/>
    <div style="margin-top:.9rem"><button class="hx-btn" type="submit">Save provider</button> <a class="hx-btn ghost" href="hmo_rules.php?id=<?= $id ?>">Coverage &amp; rules →</a></div>
  </form>
</div>

<div class="hx-card" id="agents">
  <h3>Contact list: HMO agents (<?= count($agents) ?>)</h3>
  <div class="sub">Staff see this list on the HMO case page when they need to contact the HMO.</div>
  <?php if ($agents): ?><table class="hx-mini"><tr><th>Agent</th><th>Role</th><th>Phone</th><th>Email</th><th>Channel</th><th>Notes</th><th></th></tr>
  <?php foreach ($agents as $a): ?><tr>
    <td><b><?= h($a['agent_name']) ?></b><?= $a['is_primary'] ? ' <span class="badge badge-blue">Primary</span>' : '' ?></td><td><?= h($a['role_title']) ?></td><td><?= h($a['phone']) ?></td><td><?= h($a['email']) ?></td><td><?= h($a['channel']) ?></td><td><?= h($a['notes']) ?></td>
    <td><form method="POST" onsubmit="return confirm('Remove this agent?')"><input type="hidden" name="csrf" value="<?= h($csrf) ?>"/><input type="hidden" name="id" value="<?= $id ?>"/><input type="hidden" name="action" value="del_agent"/><input type="hidden" name="agent_id" value="<?= (int)$a['id'] ?>"/><button class="btn-sm btn-red" type="submit">Remove</button></form></td></tr>
  <?php endforeach; ?></table><?php else: ?><div class="hx-muted">No agents yet.</div><?php endif; ?>
  <form method="POST" style="margin-top:1rem">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"/><input type="hidden" name="id" value="<?= $id ?>"/><input type="hidden" name="action" value="add_agent"/>
    <div class="hx-grid3">
      <div><label class="hx-lbl">Agent name</label><input class="hx-in" name="agent_name" maxlength="120" required/></div>
      <div><label class="hx-lbl">Role / department</label><input class="hx-in" name="role_title" maxlength="80" placeholder="e.g. LOA desk"/></div>
      <div><label class="hx-lbl">Channel</label><input class="hx-in" name="channel" maxlength="60" placeholder="Phone / Viber / Portal"/></div>
      <div><label class="hx-lbl">Phone</label><input class="hx-in" name="phone" maxlength="40"/></div>
      <div><label class="hx-lbl">Email</label><input class="hx-in" name="email" maxlength="120"/></div>
      <div><label class="hx-lbl">Notes</label><input class="hx-in" name="notes" maxlength="255" placeholder="Office hours, etc."/></div>
    </div>
    <label class="hx-checks" style="margin:.6rem 0"><span><input type="checkbox" name="is_primary" value="1"/> Primary agent</span></label>
    <button class="hx-btn" type="submit">Add agent</button>
  </form>
</div>

<div class="hx-card" id="doctors">
  <h3>Supported clinics &amp; doctors</h3>
  <div class="sub">Tick the doctors who accept this HMO. Tick none = every doctor accepts it. Clinics come from each doctor's clinic name.</div>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"/><input type="hidden" name="id" value="<?= $id ?>"/><input type="hidden" name="action" value="doctors"/>
    <?php if (!$doctors): ?><div class="hx-muted">No active doctors found.</div><?php endif; ?>
    <?php foreach ($byClinic as $clinic => $ds): ?>
      <div style="font-weight:700;font-size:.8rem;margin:.6rem 0 .3rem"><?= h($clinic) ?></div>
      <div class="hx-checks"><?php foreach ($ds as $d): ?><span><label><input type="checkbox" name="doctor_ids[]" value="<?= (int)$d['id'] ?>" <?= isset($sel[(int)$d['id']]) ? 'checked' : '' ?>/> Dr. <?= h($d['full_name']) ?> <span class="hx-muted"><?= h($d['department']) ?></span></label></span><?php endforeach; ?></div>
    <?php endforeach; ?>
    <div style="margin-top:.9rem"><button class="hx-btn" type="submit">Save participation</button></div>
  </form>
</div>

<div class="hx-card">
  <h3>Configuration history</h3><div class="sub">Every change to this provider, its agents, rules and doctors.</div>
  <?php if (!$hist): ?><div class="hx-muted">No changes recorded yet.</div><?php else: ?>
  <table class="hx-mini"><tr><th>When</th><th>Who</th><th>Change</th><th>Previous</th><th>New</th></tr>
  <?php foreach ($hist as $l): ?><tr><td><?= h(date('M j, Y g:i A', strtotime($l['created_at']))) ?></td><td><?= h($l['admin_name'] ?: 'Admin #' . $l['admin_id']) ?></td>
    <td><?= h($l['entity_type'] . ' · ' . $l['action']) ?></td><td><div class="hx-diff"><?= h($l['old_values'] ?? '—') ?></div></td><td><div class="hx-diff"><?= h($l['new_values'] ?? '—') ?></div></td></tr><?php endforeach; ?></table><?php endif; ?>
</div>
<?php hmo_admin_foot();
