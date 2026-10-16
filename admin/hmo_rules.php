<?php
// admin/hmo_rules.php — 3. HMO Coverage & Authorization Rules (provider-specific)
require_once 'includes/hmo_admin.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hmo_admin_csrf_ok()) hmo_admin_back('hmo_rules.php?id=' . $id, 'Your session expired. Please try again.', true);
    $st = $conn->prepare("SELECT id, name FROM hmo_providers WHERE id = ?"); $st->bind_param('i', $id); $st->execute();
    $pr = $st->get_result()->fetch_assoc();
    if (!$pr) hmo_admin_back('hmo_rules.php', 'Provider not found.', true);

    $loa = (string)($_POST['loa_requirement'] ?? 'optional');
    if (!in_array($loa, ['required', 'optional', 'not_applicable'], true)) $loa = 'optional';
    $ver = (string)($_POST['verification_method'] ?? 'phone');
    if (!isset(HMO_RULE_METHODS[$ver])) $ver = 'phone';
    $docs   = array_values(array_intersect(array_keys(HMO_RULE_DOCS), (array)($_POST['required_docs'] ?? [])));
    $fields = array_values(array_intersect(array_keys(HMO_RULE_FIELDS), (array)($_POST['member_fields'] ?? [])));
    $types  = array_values(array_intersect(HMO_CONSULT_TYPES, (array)($_POST['consult_types'] ?? [])));
    if (!$types) hmo_admin_back('hmo_rules.php?id=' . $id, 'Pick at least one consultation type.', true);
    $days = trim((string)($_POST['validity_days'] ?? '')); $days = ($days !== '' && ctype_digit($days) && (int)$days > 0 && (int)$days <= 3650) ? (int)$days : null;
    $copay = mb_substr(trim((string)($_POST['copay_note'] ?? '')), 0, 255);
    $eff = hmo_admin_date($_POST['effective_from'] ?? '');

    $old = hmo_rules_for($conn, $pr['name']);
    $new = ['loa_requirement' => $loa, 'member_fields' => $fields, 'required_docs' => $docs, 'verification_method' => $ver, 'validity_days' => $days,
            'consult_types' => $types, 'copay_note' => $copay, 'effective_from' => $eff];
    $docsS = implode(',', $docs); $fieldsS = implode(',', $fields); $typesS = implode(',', $types); $copayN = $copay !== '' ? $copay : null;
    $q = $conn->prepare("INSERT INTO hmo_rules (provider_id, loa_requirement, member_fields, required_docs, verification_method, validity_days, consult_types, copay_note, effective_from, updated_by, updated_at)
                         VALUES (?,?,?,?,?,?,?,?,?,?,NOW())
                         ON DUPLICATE KEY UPDATE loa_requirement=VALUES(loa_requirement), member_fields=VALUES(member_fields), required_docs=VALUES(required_docs),
                         verification_method=VALUES(verification_method), validity_days=VALUES(validity_days), consult_types=VALUES(consult_types), copay_note=VALUES(copay_note),
                         effective_from=VALUES(effective_from), updated_by=VALUES(updated_by), updated_at=NOW()");
    $q->bind_param('issssisssi', $id, $loa, $fieldsS, $docsS, $ver, $days, $typesS, $copayN, $eff, $admin_id);
    $q->execute();
    $oldOut = $old['saved'] ? array_diff_key($old, ['saved' => 1]) : null;
    hmo_admin_audit($conn, $admin_id, $old['saved'] ? 'update' : 'create', 'hmo_rule', $id, $oldOut, $new);
    hmo_admin_back('hmo_rules.php?id=' . $id, 'Rules saved for ' . $pr['name'] . '.');
}

$csrf = hmo_admin_csrf();
$provs = $conn->query("SELECT p.*, (SELECT COUNT(*) FROM hmo_coverage c WHERE c.provider_id = p.id) AS n_cov FROM hmo_providers p ORDER BY p.name")->fetch_all(MYSQLI_ASSOC);
$sel = null; foreach ($provs as $p) if ((int)$p['id'] === $id) $sel = $p;
$rules = $sel ? hmo_rules_for($conn, $sel['name']) : null;
$ruleLabels = ['required' => 'LOA required', 'optional' => 'LOA optional', 'not_applicable' => 'LOA not applicable'];

hmo_admin_head('Coverage & Authorization Rules', 'hmo-rules');
?>
<div class="hx-card"><h3>Rules are per provider</h3>
  <div class="sub">Each HMO has its own rules. They are enforced when the patient books (LOA, member fields, consultation type), when the patient submits documents, and shown to staff when they verify. Providers without saved rules use safe defaults (LOA optional, HMO card required).</div>
  <table class="hx-mini"><tr><th>Provider</th><th>Teleconsult</th><th>LOA</th><th>Required documents</th><th>Verified by</th><th>Validity</th><th>Rules</th><th></th></tr>
  <?php foreach ($provs as $p): $r = hmo_rules_for($conn, $p['name']); ?>
    <tr style="<?= (int)$p['id'] === $id ? 'background:rgba(195,54,67,.05)' : '' ?>">
      <td><b><?= h($p['name']) ?></b> <?= hmo_state_badge(hmo_provider_state($p)) ?></td>
      <td><?= (int)$p['teleconsult_available'] ? 'Yes' : '<span style="color:var(--red)">No</span>' ?></td>
      <td><?= h($ruleLabels[$r['loa_requirement']]) ?></td>
      <td><?= h(implode(', ', array_map(fn($k) => HMO_RULE_DOCS[$k] ?? $k, $r['required_docs'])) ?: '—') ?></td>
      <td><?= h(HMO_RULE_METHODS[$r['verification_method']]) ?></td><td><?= $r['validity_days'] ? (int)$r['validity_days'] . ' days' : '—' ?></td>
      <td><?= $r['saved'] ? '<span class="badge badge-green">Configured</span>' : '<span class="badge badge-gray">Defaults</span>' ?></td>
      <td><a class="btn-sm btn-edit" href="hmo_rules.php?id=<?= (int)$p['id'] ?>#form">Edit</a></td></tr>
  <?php endforeach; ?></table>
</div>

<?php if ($sel): ?>
<div class="hx-card" id="form">
  <h3>Rules for <?= h($sel['name']) ?></h3>
  <div class="sub">Teleconsultation on/off, status and participating doctors are set on the <a href="hmo_provider.php?id=<?= (int)$sel['id'] ?>">provider page</a>. Co-pay / coverage items are listed in <a href="hmo.php">Providers → Coverage</a> (<?= (int)$sel['n_cov'] ?> item(s) now).</div>
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>"/><input type="hidden" name="id" value="<?= (int)$sel['id'] ?>"/>
    <div class="hx-grid3">
      <div><label class="hx-lbl">LOA / LOG</label><select class="hx-in" name="loa_requirement">
        <?php foreach (['required' => 'Required before booking', 'optional' => 'Optional (staff ask the HMO if none)', 'not_applicable' => 'Not applicable'] as $k => $l): ?><option value="<?= $k ?>" <?= $rules['loa_requirement'] === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
      <div><label class="hx-lbl">Verification method</label><select class="hx-in" name="verification_method">
        <?php foreach (HMO_RULE_METHODS as $k => $l): ?><option value="<?= $k ?>" <?= $rules['verification_method'] === $k ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
      <div><label class="hx-lbl">Authorization validity (days)</label><input class="hx-in" type="number" min="1" max="3650" name="validity_days" value="<?= h($rules['validity_days'] ?? '') ?>" placeholder="e.g. 30"/></div>
    </div>
    <label class="hx-lbl" style="margin-top:.8rem">Required documents (patient cannot submit without them)</label>
    <div class="hx-checks"><?php foreach (HMO_RULE_DOCS as $k => $l): ?><span><label><input type="checkbox" name="required_docs[]" value="<?= $k ?>" <?= in_array($k, $rules['required_docs'], true) ? 'checked' : '' ?>/> <?= h($l) ?></label></span><?php endforeach; ?></div>
    <label class="hx-lbl" style="margin-top:.8rem">Required member information (Member ID and principal member are always required)</label>
    <div class="hx-checks"><?php foreach (HMO_RULE_FIELDS as $k => $l): ?><span><label><input type="checkbox" name="member_fields[]" value="<?= $k ?>" <?= in_array($k, $rules['member_fields'], true) ? 'checked' : '' ?>/> <?= h($l) ?></label></span><?php endforeach; ?></div>
    <label class="hx-lbl" style="margin-top:.8rem">Supported consultation types</label>
    <div class="hx-checks"><?php foreach (HMO_CONSULT_TYPES as $k): ?><span><label><input type="checkbox" name="consult_types[]" value="<?= h($k) ?>" <?= in_array($k, $rules['consult_types'], true) ? 'checked' : '' ?>/> <?= h($k) ?></label></span><?php endforeach; ?></div>
    <div class="hx-grid2" style="margin-top:.8rem">
      <div><label class="hx-lbl">Co-pay / coverage information (only if confirmed)</label><input class="hx-in" name="copay_note" maxlength="255" value="<?= h($rules['copay_note']) ?>" placeholder="e.g. Teleconsultation covered up to ₱500, no co-pay"/></div>
      <div><label class="hx-lbl">Effective from</label><input class="hx-in" type="date" name="effective_from" value="<?= h($conn->query("SELECT effective_from FROM hmo_rules WHERE provider_id = " . (int)$sel['id'])->fetch_row()[0] ?? '') ?>"/></div>
    </div>
    <div style="margin-top:1rem"><button class="hx-btn" type="submit">Save rules</button></div>
  </form>
</div>
<?php endif; ?>
<?php hmo_admin_foot();
