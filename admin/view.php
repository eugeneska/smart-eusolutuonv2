<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { header('Location:/admin/index.php'); exit; }

$pdo = pdo();
$stmt = $pdo->prepare("SELECT id, name, email, phone, message, user_agent, INET6_NTOA(ip) AS ip, created_at, status, meta
                       FROM contact_requests WHERE id=:id");
$stmt->execute([':id'=>$id]);
$r = $stmt->fetch();
if (!$r) { http_response_code(404); exit('Not found'); }
$csrf = csrfToken();
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Заявка #<?= (int)$r['id'] ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container-fluid py-4">
    <div class="row">
      <div class="col-12">
        <div class="d-flex align-items-center mb-4">
          <a href="/admin/index.php" class="btn btn-outline-secondary me-3">
            <i class="bi bi-arrow-left me-1"></i>К списку
          </a>
          <h1 class="h2 mb-0">
            <i class="bi bi-file-text me-2"></i>Заявка #<?= (int)$r['id'] ?>
          </h1>
        </div>

        <div class="row">
          <div class="col-lg-8">
            <div class="card mb-4">
              <div class="card-header">
                <h5 class="card-title mb-0">
                  <i class="bi bi-person me-2"></i>Информация о заявке
                </h5>
              </div>
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-bold">Дата создания:</label>
                    <div class="text-muted">
                      <i class="bi bi-calendar me-1"></i><?=h($r['created_at'])?>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold">Статус:</label>
                    <div>
                      <?php
                        $badgeClass = match($r['status']) {
                          'new' => 'bg-primary',
                          'processed' => 'bg-success',
                          'spam' => 'bg-danger',
                          default => 'bg-secondary'
                        };
                        echo '<span class="badge '.$badgeClass.' fs-6">'.h($r['status']).'</span>';
                      ?>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold">Имя:</label>
                    <div>
                      <i class="bi bi-person me-1"></i><?=h($r['name'])?>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold">Email:</label>
                    <div>
                      <i class="bi bi-envelope me-1"></i>
                      <?php if ($r['email']): ?>
                        <a href="mailto:<?=h($r['email'])?>" class="text-decoration-none"><?=h($r['email'])?></a>
                      <?php else: ?>
                        <span class="text-muted">Не указан</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold">Телефон:</label>
                    <div>
                      <i class="bi bi-telephone me-1"></i>
                      <?php if ($r['phone']): ?>
                        <a href="tel:<?=h($r['phone'])?>" class="text-decoration-none"><?=h($r['phone'])?></a>
                      <?php else: ?>
                        <span class="text-muted">Не указан</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-bold">IP адрес:</label>
                    <div>
                      <i class="bi bi-globe me-1"></i><?=h($r['ip'])?>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="card mb-4">
              <div class="card-header">
                <h5 class="card-title mb-0">
                  <i class="bi bi-chat-text me-2"></i>Сообщение
                </h5>
              </div>
              <div class="card-body">
                <div class="bg-light p-3 rounded">
                  <pre class="mb-0" style="white-space:pre-wrap;word-wrap:break-word;"><?=h($r['message'])?></pre>
                </div>
              </div>
            </div>

            <?php if ($r['meta']): ?>
              <div class="card mb-4">
                <div class="card-header">
                  <h5 class="card-title mb-0">
                    <i class="bi bi-code me-2"></i>Дополнительные данные (JSON)
                  </h5>
                </div>
                <div class="card-body">
                  <div class="bg-light p-3 rounded">
                    <pre class="mb-0" style="white-space:pre-wrap;word-wrap:break-word;"><?=h($r['meta'])?></pre>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <div class="col-lg-4">
            <div class="card">
              <div class="card-header">
                <h5 class="card-title mb-0">
                  <i class="bi bi-gear me-2"></i>Техническая информация
                </h5>
              </div>
              <div class="card-body">
                <div class="mb-3">
                  <label class="form-label fw-bold">User-Agent:</label>
                  <div class="small text-break bg-light p-2 rounded">
                    <?=h($r['user_agent'])?>
                  </div>
                </div>
              </div>
            </div>

            <div class="card mt-4">
              <div class="card-header">
                <h5 class="card-title mb-0">
                  <i class="bi bi-tools me-2"></i>Действия
                </h5>
              </div>
              <div class="card-body">
                <form method="post" action="/admin/update.php" class="d-grid gap-2">
                  <input type="hidden" name="csrf" value="<?=$csrf?>">
                  <input type="hidden" name="id" value="<?=$r['id']?>">
                  
                  <button type="submit" name="action" value="processed" class="btn btn-success">
                    <i class="bi bi-check-circle me-2"></i>Отметить как обработано
                  </button>
                  
                  <button type="submit" name="action" value="spam" class="btn btn-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>Пометить как спам
                  </button>
                  
                  <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите удалить эту заявку?')">
                    <i class="bi bi-trash me-2"></i>Удалить заявку
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>