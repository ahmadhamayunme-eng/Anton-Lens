<?php require __DIR__.'/../layouts/header.php'; ?>
<header class="topbar"><h2>Anton Lens Projects</h2><form method="post" action="/logout"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><button class="btn-secondary">Logout</button></form></header>
<main class="container">
<a class="btn-primary" href="/projects/new">Create Project</a>
<div class="grid">
<?php foreach ($projects as $p): ?>
<article class="card">
<h3><a href="/projects/<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a></h3>
<p><?= e($p['client_name']) ?> · <?= e($p['base_url']) ?></p>
<p>Active <?= (int)$p['active_count'] ?> / Resolved <?= (int)$p['resolved_count'] ?></p>
</article>
<?php endforeach; ?>
</div>
</main>
<?php require __DIR__.'/../layouts/footer.php'; ?>
