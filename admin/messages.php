<?php
/* =========================================================
   admin/messages.php — messages from the contact form:
   mark as read / unread, delete.
   ========================================================= */
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

if (is_post()) {
    verify_csrf();
    $id = int_param($_POST, 'message_id');
    if (($_POST['action'] ?? '') === 'toggle') {
        db_exec('UPDATE contact_messages SET is_read = 1 - is_read WHERE id = ?', [$id]);
    } elseif (($_POST['action'] ?? '') === 'delete') {
        db_exec('DELETE FROM contact_messages WHERE id = ?', [$id]);
        flash('success', 'Message deleted');
    }
    redirect('admin/messages.php');
}

$messages = db_all('SELECT m.*, u.role FROM contact_messages m LEFT JOIN users u ON u.id = m.user_id
                    ORDER BY m.is_read ASC, m.created_at DESC');

admin_start('Messages', 'messages');
?>
<div class="panel">
  <?php if (!$messages): ?><p class="text-muted">No messages yet.</p><?php endif; ?>
  <ul class="messages">
    <?php foreach ($messages as $m): ?>
      <li class="message<?= $m['is_read'] ? '' : ' message--unread' ?>">
        <div class="message__head">
          <strong><?= h($m['name']) ?></strong>
          <a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a>
          <span class="text-muted small"><?= format_datetime($m['created_at']) ?><?= $m['user_id'] ? ' · registered user' : ' · guest' ?></span>
          <?php if (!$m['is_read']): ?><span class="badge badge--pending">new</span><?php endif; ?>
        </div>
        <p><?= nl2br(h($m['message'])) ?></p>
        <div class="actions">
          <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn--outline btn--small" type="submit"><?= $m['is_read'] ? 'Mark as unread' : 'Mark as read' ?></button></form>
          <form method="post" class="inline-form" data-confirm="Delete this message?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="message_id" value="<?= (int)$m['id'] ?>">
            <button class="btn btn--danger btn--small" type="submit">Delete</button></form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php admin_end(); ?>
