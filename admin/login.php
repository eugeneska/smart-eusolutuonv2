<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['user'] ?? '');
    $pass = (string)($_POST['pass'] ?? '');
    $csrf = $_POST['csrf'] ?? '';
    checkCsrf($csrf);

    if ($user === ADMIN_USER && $pass === 'sesadmin123') {
        $_SESSION['admin_logged_in'] = true;
        header('Location: /admin/index.php');
        exit;
    } else {
        $error = 'Неверные логин или пароль';
    }
}
$csrf = csrfToken();
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Вход в админку</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-4">
        <div class="card shadow mt-5">
          <div class="card-body p-5">
            <div class="text-center mb-4">
              <i class="bi bi-shield-lock text-primary" style="font-size: 3rem;"></i>
              <h2 class="h4 mt-3 mb-1">Админ-панель</h2>
              <p class="text-muted small">Войдите в систему управления</p>
            </div>
            
            <?php if (!empty($error)): ?>
              <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?=h($error)?>
              </div>
            <?php endif; ?>
            
            <form method="post" action="/admin/login.php" autocomplete="off">
              <input type="hidden" name="csrf" value="<?=$csrf?>">
              
              <div class="mb-3">
                <label for="user" class="form-label">
                  <i class="bi bi-person me-1"></i>Логин
                </label>
                <input type="text" class="form-control form-control-lg" id="user" name="user" required autofocus>
              </div>
              
              <div class="mb-4">
                <label for="pass" class="form-label">
                  <i class="bi bi-lock me-1"></i>Пароль
                </label>
                <input type="password" class="form-control form-control-lg" id="pass" name="pass" required>
              </div>
              
              <button type="submit" class="btn btn-primary btn-lg w-100">
                <i class="bi bi-box-arrow-in-right me-2"></i>Войти
              </button>
            </form>
          </div>
        </div>
        
        <div class="text-center mt-4">
          <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            Система управления заявками
          </small>
        </div>
      </div>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>