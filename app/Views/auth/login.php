<?php require __DIR__.'/../layouts/header.php'; ?>
<main class="auth-wrap">
  <h1>Anton Lens Login</h1>
  <?php if ($error): ?><p class="error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="card">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" required></label>
    <button class="btn-primary" type="submit">Login</button>
  </form>
</main>
<?php require __DIR__.'/../layouts/footer.php'; ?>
