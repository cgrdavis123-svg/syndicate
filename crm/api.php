<?php
// Small JSON endpoints used by the board and task checkboxes.
require __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json');
function out(int $code, array $d): void { http_response_code($code); echo json_encode($d); exit; }
if (!current_user()) out(401, ['ok' => false, 'error' => 'Signed out']);
if (!is_post() || !csrf_ok()) out(400, ['ok' => false, 'error' => 'Refresh the page and try again.']);

switch (post('action')) {
    case 'an_live':
        session_write_close();
        require __DIR__ . '/app/analytics.php';
        out(200, ['ok' => true] + an_live((int)post('site')));
    case 'mail_sync':
        session_write_close(); // so other pages don't wait while mail is checked
        require __DIR__ . '/app/mail.php';
        $r = mail_sync(post('force') === '1');
        out(200, ['ok' => true, 'new' => $r['new'], 'unread' => mail_unread(), 'errors' => array_values($r['errors']), 'skipped' => $r['skipped']]);
    case 'move_deal':
        $id = (int)post('id'); $stage = post('stage');
        $deal = one('SELECT * FROM deals WHERE id = ?', [$id]);
        if (!$deal || !isset(STAGES[$stage])) out(422, ['ok' => false, 'error' => 'Unknown deal or stage']);
        if ($deal['stage'] !== $stage) {
            $closed = in_array($stage, ['won', 'lost'], true) ? now() : null;
            q('UPDATE deals SET stage = ?, closed_at = ?, updated_at = ? WHERE id = ?', [$stage, $closed, now(), $id]);
            log_activity('deal', $id, 'stage', 'Moved from ' . STAGES[$deal['stage']] . ' to ' . STAGES[$stage] . '.');
        }
        $order = array_map('intval', explode(',', post('order')));
        foreach ($order as $pos => $did) if ($did) q('UPDATE deals SET position = ? WHERE id = ? AND stage = ?', [$pos, $did, $stage]);
        out(200, ['ok' => true]);
    case 'toggle_task':
        $t = one('SELECT * FROM tasks WHERE id = ?', [(int)post('id')]);
        if (!$t) out(404, ['ok' => false, 'error' => 'Task not found']);
        $done = $t['done_at'] ? null : now();
        q('UPDATE tasks SET done_at = ? WHERE id = ?', [$done, $t['id']]);
        if ($done && $t['entity_type']) log_activity($t['entity_type'], (int)$t['entity_id'], 'task', 'Completed task: ' . $t['title']);
        out(200, ['ok' => true, 'done' => (bool)$done]);
}
out(400, ['ok' => false, 'error' => 'Unknown action']);
