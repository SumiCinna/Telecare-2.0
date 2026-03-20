
<?php
// WALA TO DISPLAY LANG KASI AKALA KO NEED NA FUNCTION BUT STAY KO LANG DITO
require_once 'includes/auth.php';

// ── Fetch assigned doctor ──
$doc = null;
$dr  = $conn->query("
    SELECT d.* FROM doctors d
    JOIN patient_doctors pd ON pd.doctor_id = d.id
    WHERE pd.patient_id = $patient_id LIMIT 1
");
if ($dr && $dr->num_rows > 0) $doc = $dr->fetch_assoc();

// ── Handle send message ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $doc) {
    $msg = trim($_POST['message'] ?? '');
    if ($msg !== '') {
        $stmt = $conn->prepare("INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message) VALUES ('patient', ?, 'doctor', ?, ?)");
        $stmt->bind_param("iis", $patient_id, $doc['id'], $msg);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: chat.php');
    exit;
}

// ── Fetch messages + mark read ──
$chat_messages = null;
if ($doc) {
    $did           = $doc['id'];
    $chat_messages = $conn->query("
        SELECT * FROM messages
        WHERE (sender_type='patient' AND sender_id=$patient_id AND receiver_id=$did)
           OR (sender_type='doctor'  AND sender_id=$did AND receiver_id=$patient_id)
        ORDER BY sent_at ASC
    ");
    $conn->query("UPDATE messages SET is_read=1 WHERE sender_type='doctor' AND sender_id=$did AND receiver_id=$patient_id AND is_read=0");
}

$page_title = 'Chat — TELE-CARE';
$active_nav = 'chat';
require_once 'includes/header.php';
?>

<style>
  .chat-wrap {
    display:flex; flex-direction:column; gap:0.8rem;
    max-height:55vh; overflow-y:auto; padding:0.5rem 0 1rem;
  }
  .bubble {
    max-width:75%; padding:0.75rem 1rem; border-radius:18px;
    font-size:0.88rem; line-height:1.5;
  }
  .bubble.me   { background:var(--blue); color:#fff; border-bottom-right-radius:4px; align-self:flex-end; }
  .bubble.them { background:var(--blue-light); color:var(--text); border-bottom-left-radius:4px; align-self:flex-start; }
  .bubble-time { font-size:0.66rem; margin-top:0.2rem; }
  .bubble.me   .bubble-time { color:rgba(255,255,255,0.55); text-align:right; }
  .bubble.them .bubble-time { color:var(--muted); }

  .chat-input-row {
    display:flex; gap:0.7rem; align-items:center;
    background:var(--white); border:1.5px solid rgba(63,130,227,0.15);
    border-radius:50px; padding:0.5rem 0.5rem 0.5rem 1.2rem;
  }
  .chat-input-row input {
    flex:1; border:none; outline:none;
    font-family:'DM Sans',sans-serif; font-size:0.9rem;
    color:var(--text); background:transparent;
  }
  .chat-send {
    width:38px; height:38px; border-radius:50%;
    background:var(--blue); border:none; cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    flex-shrink:0; transition:background 0.2s;
  }
  .chat-send:hover { background:var(--blue-dark); }
</style>

<div class="page">
  <?php if ($doc): ?>

  <!-- Doctor strip -->
  <div style="display:flex;align-items:center;gap:0.9rem;margin-bottom:1.2rem;">
    <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,var(--blue),var(--blue-dark));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">
      <?= strtoupper(substr($doc['full_name'], 0, 2)) ?>
    </div>
    <div>
      <div style="font-weight:700;font-size:0.95rem;">Dr. <?= htmlspecialchars($doc['full_name']) ?></div>
      <div style="font-size:0.78rem;color:#9ab0ae;"><?= htmlspecialchars($doc['specialty'] ?? 'General Practitioner') ?></div>
    </div>
    <span style="margin-left:auto;" class="badge <?= !empty($doc['is_available']) ? 'badge-green' : 'badge-gray' ?>">
      <?= !empty($doc['is_available']) ? '● Online' : '○ Offline' ?>
    </span>
  </div>

  <!-- Messages -->
  <div class="card" style="padding:1rem;">
    <div class="chat-wrap" id="chatWrap">
      <?php
      $has = false;
      if ($chat_messages && $chat_messages->num_rows > 0):
        while ($msg = $chat_messages->fetch_assoc()):
          $has   = true;
          $is_me = ($msg['sender_type'] === 'patient');
      ?>
      <div style="display:flex;flex-direction:column;align-items:<?= $is_me ? 'flex-end' : 'flex-start' ?>;">
        <div class="bubble <?= $is_me ? 'me' : 'them' ?>">
          <?= nl2br(htmlspecialchars($msg['message'])) ?>
          <div class="bubble-time"><?= date('g:i A', strtotime($msg['sent_at'])) ?></div>
        </div>
      </div>
      <?php endwhile; endif; ?>
      <?php if (!$has): ?>
      <div class="empty-state" style="padding:2rem;">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:40px;height:40px;stroke:#c8d8d6;margin:0 auto 0.8rem;display:block;">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        <div style="font-size:0.85rem;">No messages yet. Say hi to your doctor!</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Send -->
  <form method="POST">
    <div class="chat-input-row">
      <input type="text" name="message" placeholder="Type a message..." autocomplete="off" required/>
      <button type="submit" name="send_message" class="chat-send">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
        </svg>
      </button>
    </div>
  </form>

  <?php else: ?>
  <div class="card">
    <div class="empty-state">No doctor assigned yet. You'll be able to chat once a doctor is assigned to you.</div>
  </div>
  <?php endif; ?>
</div>

<script>
  const cw = document.getElementById('chatWrap');
  if (cw) cw.scrollTop = cw.scrollHeight;
</script>

<?php require_once 'includes/nav.php'; ?>
</body>
</html>