<?php require __DIR__.'/../layouts/header.php'; ?>
<main class="container">
  <h2>New Client</h2>
  <?php if (!empty($error)): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" action="/clients" class="card">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>Client Name <input name="name" required></label>
    <label>Contact Email (optional) <input type="email" name="contact_email"></label>
    <button class="btn-primary" type="submit">Create Client</button>
  </form>
  <p><a href="/projects/new">Back to New Project</a></p>
</main>
<?php require __DIR__.'/../layouts/footer.php'; ?>
