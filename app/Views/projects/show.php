<?php require __DIR__.'/../layouts/header.php'; ?>
<main class="container"><h2><?= e($project['title']) ?></h2>
<p>Client: <?= e($project['client_name']) ?></p>
<p>Base URL: <?= e($project['base_url']) ?></p>
<a class="btn-primary" href="/projects/<?= (int)$project['id'] ?>/view">Open Viewer</a>
</main>
<?php require __DIR__.'/../layouts/footer.php'; ?>
