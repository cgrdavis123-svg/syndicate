<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/booking.php';
$user = require_user();
$id = (int)get('id');
$t = ['slug' => '', 'name' => '', 'brand' => 'Syndicate Marketing', 'description' => '', 'minutes' => 30, 'price' => 0, 'location' => 'video', 'location_detail' => '', 'question' => 'What would you like to talk about?', 'public' => 1, 'active' => 1, 'max_per_day' => 0, 'kind' => 'lead', 'position' => (int)val('SELECT COALESCE(MAX(position),0)+10 FROM meeting_types')];
if ($id) { $t = one('SELECT * FROM meeting_types WHERE id = ?', [$id]); if (!$t) { flash('That meeting type no longer exists.'); redirect('booking-setup.php'); } }
$error = '';
if (is_post()) {
    if (post('action') === 'delete' && $id) {
        if (val('SELECT 1 FROM bookings WHERE type_id = ?', [$id])) { q('UPDATE meeting_types SET active = 0 WHERE id = ?', [$id]); flash('It has bookings, so it was turned off instead of deleted.'); }
        else { q('DELETE FROM meeting_types WHERE id = ?', [$id]); flash('Meeting type deleted.'); }
        redirect('booking-setup.php');
    }
    $t = array_merge($t, ['name' => mb_substr(post('name'), 0, 80), 'brand' => in_array(post('brand'), BRANDS, true) ? post('brand') : '', 'description' => mb_substr(post('description'), 0, 1000),
        'minutes' => max(10, min(480, (int)post('minutes'))), 'price' => round(max(0, (float)post('price')), 2), 'location' => isset(BOOK_LOCATIONS[post('location')]) ? post('location') : 'video',
        'location_detail' => mb_substr(post('location_detail'), 0, 300), 'question' => mb_substr(post('question'), 0, 200), 'public' => post('public') === '1' ? 1 : 0, 'active' => post('active') === '1' ? 1 : 0,
        'max_per_day' => max(0, min(20, (int)post('max_per_day'))), 'kind' => isset(BOOK_KINDS[post('kind')]) ? post('kind') : 'lead', 'position' => (int)post('position')]);
    $slug = $id ? $t['slug'] : strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', post('slug') ?: $t['name']), '-'));
    if (mb_strlen($t['name']) < 2) $error = 'Give it a name, like “Discovery call”.';
    elseif (!$id && ($slug === '' || val('SELECT 1 FROM meeting_types WHERE slug = ?', [$slug]))) $error = 'That link name is already used. Choose another.';
    elseif ($t['price'] > 0 && $t['price'] < 1) $error = 'Paid meetings need a price of at least $1.';
    else {
        $cols = ['name', 'brand', 'description', 'minutes', 'price', 'location', 'location_detail', 'question', 'public', 'active', 'max_per_day', 'kind', 'position'];
        $vals = array_map(function ($c) use ($t) { return $t[$c]; }, $cols);
        if ($id) q('UPDATE meeting_types SET ' . implode(', ', array_map(function ($c) { return "$c = ?"; }, $cols)) . ', updated_at = ? WHERE id = ?', array_merge($vals, [now(), $id]));
        else q('INSERT INTO meeting_types (slug, ' . implode(', ', $cols) . ', created_at, updated_at) VALUES (?' . str_repeat(',?', count($cols)) . ',?,?)', array_merge([$slug], $vals, [now(), now()]));
        flash('Saved.'); redirect('booking-setup.php');
    }
}
layout_top($id ? $t['name'] : 'New meeting type', 'bookings', $user);
?>
<div class="page-head"><div><a class="muted small" href="booking-setup.php">← Hours &amp; meeting types</a><h1><?= $id ? e($t['name']) : 'New meeting type' ?></h1><?php if ($id): ?><p class="muted small"><?= e(book_link($t['slug'])) ?></p><?php endif; ?></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<form method="post" class="card form-grid" style="max-width:860px">
  <?= csrf_field() ?>
  <label class="f"><span>Name</span><input name="name" required value="<?= e($t['name']) ?>" placeholder="Discovery call"></label>
  <label class="f"><span>Brand</span><select name="brand"><?php foreach (BRANDS as $br): ?><option<?= selected($br, $t['brand']) ?>><?= e($br) ?></option><?php endforeach; ?></select></label>
  <label class="f span2"><span>Description <small class="muted">(shown on the booking page)</small></span><textarea name="description" rows="3"><?= e($t['description']) ?></textarea></label>
  <label class="f"><span>Length (minutes)</span><input type="number" name="minutes" min="10" max="480" step="5" value="<?= (int)$t['minutes'] ?>"></label>
  <label class="f"><span>Price ($) <small class="muted">(0 for free; paid ones go through Stripe)</small></span><input type="number" name="price" min="0" step="0.01" value="<?= e((float)$t['price']) ?>"></label>
  <label class="f"><span>Where</span><select name="location"><?php foreach (BOOK_LOCATIONS as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $t['location']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Address or meeting link <small class="muted">(optional; overrides the default video link)</small></span><input name="location_detail" value="<?= e($t['location_detail']) ?>"></label>
  <label class="f span2"><span>Question to ask <small class="muted">(optional)</small></span><input name="question" value="<?= e($t['question']) ?>"></label>
  <label class="f"><span>What it’s for</span><select name="kind"><?php foreach (BOOK_KINDS as $k => $l): ?><option value="<?= $k ?>"<?= selected($k, $t['kind']) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
  <label class="f"><span>Most per day <small class="muted">(0 for no limit)</small></span><input type="number" name="max_per_day" min="0" max="20" value="<?= (int)$t['max_per_day'] ?>"></label>
  <?php if (!$id): ?><label class="f"><span>Link name <small class="muted">(optional; can’t change later)</small></span><input name="slug" pattern="[a-z0-9-]+" placeholder="discovery-call"></label><?php endif; ?>
  <label class="f"><span>Sort order</span><input type="number" name="position" value="<?= (int)$t['position'] ?>"></label>
  <label class="chk span2"><input type="checkbox" name="public" value="1"<?= $t['public'] ? ' checked' : '' ?>> <span>Show it on the main booking page (otherwise only people with the link can book it)</span></label>
  <label class="chk span2"><input type="checkbox" name="active" value="1"<?= $t['active'] ? ' checked' : '' ?>> <span>Taking bookings</span></label>
  <div class="save-bar span2"><?php if ($id): ?><button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this meeting type?">Delete</button><?php else: ?><span></span><?php endif; ?><button class="btn primary sm" type="submit" name="action" value="save">Save</button></div>
</form>
<?php layout_bottom(); ?>
