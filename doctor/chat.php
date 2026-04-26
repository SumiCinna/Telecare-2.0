<?php
// WALA TO DISPLAY LANG KASI AKALA KO NEED NA FUNCTION BUT STAY KO LANG DITO
require_once 'includes/auth.php';

// Get the patient to chat with
$selected_patient_id = (int)($_GET['patient_id'] ?? 0);

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msg = trim($_POST['message'] ?? '');
    $pid = (int)$_POST['patient_id'];
    if ($msg && $pid) {
        $sender_type = "doctor";
        $receiver_type = "patient";
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, sender_type, receiver_id, receiver_type, message, sent_at) VALUES (?,?,?,?,?,NOW())");
        $stmt->bind_param("isiss", $doctor_id, $sender_type, $pid, $receiver_type, $msg);
        $stmt->execute();
    }
    header("Location: chat.php?patient_id=$pid"); exit;
}

// Get all patients this doctor can chat with
$my_patients = $conn->query("
    SELECT p.id, p.full_name, p.profile_photo,
        (SELECT message FROM messages
         WHERE (sender_id=$doctor_id AND sender_type='doctor' AND receiver_id=p.id AND receiver_type='patient')
            OR (sender_id=p.id AND sender_type='patient' AND receiver_id=$doctor_id AND receiver_type='doctor')
         ORDER BY sent_at DESC LIMIT 1) AS last_msg,
        (SELECT sent_at FROM messages
         WHERE (sender_id=$doctor_id AND sender_type='doctor' AND receiver_id=p.id AND receiver_type='patient')
            OR (sender_id=p.id AND sender_type='patient' AND receiver_id=$doctor_id AND receiver_type='doctor')
         ORDER BY sent_at DESC LIMIT 1) AS last_time,
        (SELECT COUNT(*) FROM messages
         WHERE sender_id=p.id AND sender_type='patient' AND receiver_id=$doctor_id AND receiver_type='doctor' AND is_read=0) AS unread
    FROM patients p
    JOIN patient_doctors pd ON pd.patient_id=p.id
    WHERE pd.doctor_id=$doctor_id
    ORDER BY last_time DESC, p.full_name ASC
");

// Load conversation
$messages      = [];
$selected_pat  = null;
if ($selected_patient_id) {
    $sp = $conn->prepare("SELECT * FROM patients WHERE id=? LIMIT 1");
    $sp->bind_param("i", $selected_patient_id);
    $sp->execute();
    $selected_pat = $sp->get_result()->fetch_assoc();

    // Mark as read
    $conn->query("UPDATE messages SET is_read=1 WHERE sender_id=$selected_patient_id AND sender_type='patient' AND receiver_id=$doctor_id AND receiver_type='doctor'");

    $cm = $conn->prepare("
        SELECT * FROM messages
        WHERE (sender_id=? AND sender_type='doctor' AND receiver_id=? AND receiver_type='patient')
           OR (sender_id=? AND sender_type='patient' AND receiver_id=? AND receiver_type='doctor')
        ORDER BY sent_at ASC
    ");
    $cm->bind_param("iiii", $doctor_id, $selected_patient_id, $selected_patient_id, $doctor_id);
    $cm->execute();
    $messages = $cm->get_result()->fetch_all(MYSQLI_ASSOC);
}

$page_title       = 'Chat — TELE-CARE';
$page_title_short = $selected_pat ? htmlspecialchars($selected_pat['full_name']) : 'Messages';
$active_nav       = 'chat';
require_once 'includes/header.php';
?>

<style>
  .chat-list-item{display:flex;align-items:center;gap:0.8rem;padding:0.85rem 1rem;border-bottom:1px solid rgba(36,68,65,0.06);text-decoration:none;color:var(--green);transition:background 0.15s;}
  .chat-list-item:hover,.chat-list-item.active{background:rgba(36,68,65,0.04);}
  .chat-list-item.active{border-left:3px solid var(--green);}
  .unread-dot{width:8px;height:8px;border-radius:50%;background:var(--blue);flex-shrink:0;}
  .msg-bubble{max-width:75%;padding:0.65rem 0.9rem;border-radius:16px;font-size:0.88rem;line-height:1.5;word-break:break-word;}
  .msg-out{background:var(--green);color:#fff;border-bottom-right-radius:4px;margin-left:auto;}
  .msg-in{background:#fff;color:var(--green);border:1px solid rgba(36,68,65,0.1);border-bottom-left-radius:4px;}
  .msg-time{font-size:0.65rem;color:var(--muted);margin-top:0.2rem;}
  .chat-input-bar{position:fixed;bottom:70px;left:0;right:0;background:#fff;border-top:1px solid rgba(36,68,65,0.1);padding:0.7rem 1rem;display:flex;gap:0.5rem;z-index:50;}
  .chat-input{flex:1;padding:0.65rem 1rem;border:1.5px solid rgba(36,68,65,0.12);border-radius:50px;font-family:'DM Sans',sans-serif;font-size:0.88rem;color:var(--green);outline:none;}
  .chat-input:focus{border-color:var(--blue);}
  .chat-send{background:var(--green);color:#fff;border:none;border-radius:50%;width:40px;height:40px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
</style>

<?php if ($selected_pat): ?>
<!-- ── CONVERSATION VIEW ── -->
<div style="padding:0 1rem 140px;max-width:600px;margin:0 auto;" id="chatMessages">
  <div style="padding:0.8rem 0 0.5rem;display:flex;align-items:center;gap:0.7rem;border-bottom:1px solid rgba(36,68,65,0.08);margin-bottom:0.8rem;">
    <a href="chat.php" style="color:var(--muted);text-decoration:none;">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <div class="pat-avatar" style="width:36px;height:36px;font-size:0.8rem;">
      <?php if (!empty($selected_pat['profile_photo'])): ?>
        <img src="../../<?= htmlspecialchars($selected_pat['profile_photo']) ?>"/>
      <?php else: echo strtoupper(substr($selected_pat['full_name'],0,2)); endif; ?>
    </div>
    <div>
      <div style="font-weight:700;font-size:0.92rem;"><?= htmlspecialchars($selected_pat['full_name']) ?></div>
      <div style="font-size:0.72rem;color:var(--muted);">Patient</div>
    </div>
  </div>

  <?php if (empty($messages)): ?>
  <div class="empty-state" style="margin-top:3rem;">Start the conversation with <?= htmlspecialchars(explode(' ',$selected_pat['full_name'])[0]) ?>.</div>
  <?php else: ?>
  <?php
  $shown_day = '';
  foreach ($messages as $m):
    $day = date('M j, Y', strtotime($m['sent_at']));
    if ($day !== $shown_day): $shown_day = $day;
  ?>
  <div style="text-align:center;margin:0.8rem 0;font-size:0.68rem;color:var(--muted);font-weight:600;"><?= $day === date('M j, Y') ? 'Today' : $day ?></div>
  <?php endif;
    $is_mine = ($m['sender_type'] === 'doctor');
  ?>
  <div style="display:flex;flex-direction:column;align-items:<?= $is_mine?'flex-end':'flex-start' ?>;margin-bottom:0.5rem;">
    <div class="msg-bubble <?= $is_mine?'msg-out':'msg-in' ?>"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
    <div class="msg-time" style="<?= $is_mine?'text-align:right':'' ?>"><?= date('g:i A', strtotime($m['sent_at'])) ?></div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Input Bar -->
<form method="POST" class="chat-input-bar">
  <input type="hidden" name="patient_id" value="<?= $selected_patient_id ?>"/>
  <input type="text" name="message" class="chat-input" placeholder="Type a message..." autocomplete="off" required/>
  <button type="submit" class="chat-send">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
  </button>
</form>

<script>
  const chat = document.getElementById('chatMessages');
  if (chat) chat.scrollTop = chat.scrollHeight;
</script>

<?php else: ?>
<!-- ── PATIENT LIST ── -->
<div style="max-width:600px;margin:0 auto;">
  <div style="padding:1rem 1rem 0.5rem;font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--muted);">Messages</div>
  <?php if ($my_patients && $my_patients->num_rows > 0):
    while ($pt = $my_patients->fetch_assoc()): ?>
  <a href="chat.php?patient_id=<?= $pt['id'] ?>" class="chat-list-item">
    <div class="pat-avatar">
      <?php if (!empty($pt['profile_photo'])): ?>
        <img src="../../<?= htmlspecialchars($pt['profile_photo']) ?>"/>
      <?php else: echo strtoupper(substr($pt['full_name'],0,2)); endif; ?>
    </div>
    <div style="flex:1;min-width:0;">
      <div style="font-weight:700;font-size:0.9rem;"><?= htmlspecialchars($pt['full_name']) ?></div>
      <div style="font-size:0.77rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
        <?= $pt['last_msg'] ? htmlspecialchars(substr($pt['last_msg'],0,45)).'...' : 'No messages yet' ?>
      </div>
    </div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0.3rem;flex-shrink:0;">
      <?php if ($pt['last_time']): ?>
      <div style="font-size:0.68rem;color:var(--muted);"><?= date('g:i A', strtotime($pt['last_time'])) ?></div>
      <?php endif; ?>
      <?php if ($pt['unread'] > 0): ?>
      <div style="background:var(--blue);color:#fff;border-radius:50%;width:18px;height:18px;font-size:0.62rem;font-weight:700;display:flex;align-items:center;justify-content:center;"><?= $pt['unread'] ?></div>
      <?php endif; ?>
    </div>
  </a>
  <?php endwhile; else: ?>
  <div class="card" style="margin:1rem;"><div class="empty-state">No patients to message yet.</div></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>