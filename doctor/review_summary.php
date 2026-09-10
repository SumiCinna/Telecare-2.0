
<div class="page">
  <?php if ($success): ?><div class="alert-success">✓ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

  <div class="card">
    <div class="section-label">Consultation Summary Review</div>
    <div style="font-size:0.84rem;color:var(--muted);margin-bottom:0.8rem;line-height:1.6;">
      Patient: <strong style="color:var(--green)"><?= htmlspecialchars($appt['patient_name']) ?></strong><br>
      Schedule: <?= date('F j, Y', strtotime($appt['appointment_date'])) ?> · <?= date('g:i A', strtotime($appt['appointment_time'])) ?>
    </div>
    <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.22);color:#92400e;border-radius:12px;padding:0.65rem 0.8rem;font-size:0.78rem;margin-bottom:0.9rem;">
      Review carefully. You may edit any section before publishing to the patient side.
    </div>

    <form method="POST">
      <input type="hidden" name="appt_id" value="<?= $appt_id ?>">
      <div class="form-field">
        <label class="field-label">Editable Summary Text</label>
        <textarea class="field-input" name="summary_text" rows="16" required style="font-family:'DM Sans',sans-serif;line-height:1.55;"><?= htmlspecialchars($appt['consultation_summary'] ?? '') ?></textarea>
      </div>

      <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-top:0.4rem;">
        <button type="submit" name="action" value="save_draft" class="btn-submit" style="flex:1;min-width:180px;background:var(--blue);">Save Draft</button>
        <button type="submit" name="action" value="publish" class="btn-submit" style="flex:1;min-width:180px;">Confirm & Publish</button>
      </div>
    </form>

    <a href="appointments.php" style="display:inline-flex;margin-top:0.9rem;color:var(--muted);text-decoration:none;font-weight:600;font-size:0.82rem;">← Back to Appointments</a>
  </div>
</div>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>