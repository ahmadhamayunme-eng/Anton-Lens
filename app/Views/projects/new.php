<?php require __DIR__.'/../layouts/header.php'; ?>
<main class="container"><h2>New Project</h2>
<form method="post" action="/projects" class="card">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<label>Title <input name="title" required></label>
<label>Base URL <input name="base_url" placeholder="https://example.com" required></label>
<?php if (empty($clients)): ?>
  <p>No clients found yet. Please create a client first.</p>
  <p><a class="btn-secondary" href="/clients/new">Create Client</a></p>
<?php else: ?>
  <label>Client <select name="client_id" required><?php foreach($clients as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
<?php endif; ?>
<label>Status <select name="status"><option>active</option><option>paused</option><option>done</option><option>archived</option></select></label>
<button class="btn-primary"<?= empty($clients) ? ' disabled' : '' ?>>Create</button>
</form></main>
<?php require __DIR__.'/../layouts/footer.php'; ?>
