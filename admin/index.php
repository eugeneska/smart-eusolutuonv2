<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
requireAdmin();

$settingsFile = __DIR__ . '/../site_settings.json';

// CSRF токен нужен для POST — получим заранее
$csrf = csrfToken();

// Обработка сохранения параметров из модального окна (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    // Проверка CSRF
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $csrf) {
        http_response_code(400);
        echo 'Invalid CSRF';
        exit;
    }

    // Получаем и валидируем/очищаем поля
    $phone = trim((string)($_POST['phone'] ?? ''));
    $emailRaw = trim((string)($_POST['email'] ?? ''));
    $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
    
    // Новые поля для управления кнопками
    $show_phone_btn = isset($_POST['show_phone_btn']) ? 1 : 0;
    $show_email_btn = isset($_POST['show_email_btn']) ? 1 : 0;
    $show_wa_btn = isset($_POST['show_wa_btn']) ? 1 : 0;

    // Параметры цен калькулятора (в тысячах евро)
    $calc_base_regular = (int)($_POST['calc_base_regular'] ?? 150);
    $calc_base_fast = (int)($_POST['calc_base_fast'] ?? 250);
    $calc_child_cost = (int)($_POST['calc_child_cost'] ?? 20);

    // Простая валидация значений (границы и целые числа)
    $calc_base_regular = max(0, min(10000, $calc_base_regular));
    $calc_base_fast = max(0, min(10000, $calc_base_fast));
    $calc_child_cost = max(0, min(10000, $calc_child_cost));

    // Небольшая валидация
    $phone = mb_substr($phone, 0, 50);
    $whatsapp = mb_substr($whatsapp, 0, 50);

    $email = null;
    if ($emailRaw !== '') {
        $emailFiltered = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
        if ($emailFiltered === false) {
            // невалидный email — перенаправим с ошибкой
            header('Location: /admin/index.php?settings_saved=0&err=bad_email');
            exit;
        }
        $email = $emailFiltered;
    }

    $data = [
        'phone' => $phone,
        'email' => $email,
        'whatsapp' => $whatsapp,
        'show_phone_btn' => $show_phone_btn,
        'show_email_btn' => $show_email_btn,
        'show_wa_btn' => $show_wa_btn,
        'calc_base_regular' => $calc_base_regular,
        'calc_base_fast' => $calc_base_fast,
        'calc_child_cost' => $calc_child_cost
    ];

    // Сохраняем в JSON-файл в корне сайта (чтобы публичная страница могла его запрашивать)
    // ВАЖНО: убедитесь, что веб-сервер имеет права на запись в родительскую директорию.
    $written = @file_put_contents($settingsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($written === false) {
        header('Location: /admin/index.php?settings_saved=0&err=write_failed');
        exit;
    }

    // PRG — редирект назад с успехом
    header('Location: /admin/index.php?settings_saved=1');
    exit;
}

// Подгружаем настройки для предварительного заполнения модалки
$settings = [
    'phone' => '', 
    'email' => '', 
    'whatsapp' => '',
    'show_phone_btn' => 1,
    'show_email_btn' => 1,
    'show_wa_btn' => 1,
    'calc_base_regular' => 150,
    'calc_base_fast' => 250,
    'calc_child_cost' => 20
];
if (file_exists($settingsFile)) {
    $raw = @file_get_contents($settingsFile);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $settings = array_merge($settings, $decoded);
        }
    }
}

$pdo = pdo();

// фильтры/пагинация
$status = $_GET['status'] ?? 'new'; // new | processed | spam | all
$perPage = max(10, min(100, (int)($_GET['pp'] ?? 20)));
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$params = [];
$where = '1';
if ($status !== 'all') {
    $where .= ' AND status = :status';
    $params[':status'] = $status;
}
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $where .= ' AND (name LIKE :q OR email LIKE :q OR message LIKE :q)';
    $params[':q'] = "%$q%";
}

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM contact_requests WHERE $where")
                 ->execute($params) ?: 0;
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM contact_requests WHERE $where");
$stmtTotal->execute($params);
$total = (int)$stmtTotal->fetchColumn();

$sql = "SELECT id, name, email, message, user_agent, INET6_NTOA(ip) AS ip,
               created_at, status, meta
        FROM contact_requests
        WHERE $where
        ORDER BY created_at DESC
        LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

function urlKeep(array $extra): string {
    $base = array_merge($_GET, $extra);
    return '/admin/index.php?' . http_build_query($base);
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <title>Заявки — админка</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .msg{white-space:pre-wrap}
    .badge-status{font-size:0.75em}
    .settings-section {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 1rem;
        margin-bottom: 1rem;
        background: #f8f9fa;
    }
    .settings-section h6 {
        margin-bottom: 1rem;
        color: #495057;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.5rem;
    }
    .form-check-label {
        font-weight: 500;
    }
  </style>
</head>
<body class="bg-light">
<div class="container-fluid py-4">
  <div class="row">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0">
          <i class="bi bi-inbox me-2"></i>Заявки
          <!-- Кнопка Параметры рядом со словом "Заявки" -->
          <button class="btn btn-sm btn-outline-secondary ms-3" data-bs-toggle="modal" data-bs-target="#settingsModal" title="Параметры">
            <i class="bi bi-gear"></i> Параметры
          </button>
        </h1>
        <a href="/admin/logout.php" class="btn btn-outline-danger">
          <i class="bi bi-box-arrow-right me-1"></i>Выйти
        </a>
      </div>

      <!-- Показываем уведомление об успехе/ошибке сохранения настроек -->
      <?php if (isset($_GET['settings_saved'])): ?>
        <?php if ((int)$_GET['settings_saved'] === 1): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            Параметры успешно сохранены.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
          </div>
        <?php else: ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Ошибка при сохранении параметров. <?php if (!empty($_GET['err'])) echo h($_GET['err']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Закрыть"></button>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <div class="card mb-4">
        <div class="card-body">
          <form method="get" action="/admin/index.php" class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Статус:</label>
              <select name="status" class="form-select" onchange="this.form.submit()">
                <option value="new" <?= $status==='new'?'selected':'' ?>>Новые</option>
                <option value="processed" <?= $status==='processed'?'selected':'' ?>>Обработанные</option>
                <option value="spam" <?= $status==='spam'?'selected':'' ?>>Спам</option>
                <option value="all" <?= $status==='all'?'selected':'' ?>>Все</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">На странице:</label>
              <select name="pp" class="form-select" onchange="this.form.submit()">
                <?php foreach ([20,50,100] as $pp): ?>
                  <option value="<?=$pp?>" <?= $perPage===$pp?'selected':'' ?>><?=$pp?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label">Поиск:</label>
              <input type="search" name="q" class="form-control" value="<?=h($q)?>" placeholder="Поиск (имя, email, сообщение)">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-search me-1"></i>Искать
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th class="text-center" style="width: 60px;">ID</th>
                  <th style="width: 140px;">Дата</th>
                  <th style="width: 200px;">Контакт</th>
                  <th>Сообщение</th>
                  <th style="width: 200px;">Тех.инфо</th>
                  <th class="text-center" style="width: 100px;">Статус</th>
                  <th class="text-center" style="width: 200px;">Действия</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td class="text-center">
                    <span class="badge bg-secondary"><?= (int)$r['id'] ?></span>
                  </td>
                  <td>
                    <small class="text-muted"><?= h($r['created_at']) ?></small>
                  </td>
                  <td>
                    <div class="fw-bold"><?= h($r['name']) ?></div>
                    <?php if ($r['email']): ?>
                      <div class="small text-primary">
                        <i class="bi bi-envelope me-1"></i><?= h($r['email']) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="msg">
                    <?= h($r['message']) ?>
                    <?php 
                      // Вывод данных калькулятора, если есть в meta
                      $calcHtml = '';
                      if (!empty($r['meta'])) {
                        $metaArr = json_decode((string)$r['meta'], true);
                        if (is_array($metaArr) && isset($metaArr['calculator']) && is_array($metaArr['calculator'])) {
                          $c = $metaArr['calculator'];
                          $program = ($c['program'] ?? '') === 'fast' ? 'Ускоренная' : 'Обычная';
                          $base = isset($c['base_cost']) ? (int)$c['base_cost'] : null;
                          $children = isset($c['children']) ? (int)$c['children'] : null;
                          $childPer = isset($c['child_cost']) ? (int)$c['child_cost'] : null;
                          $total = isset($c['total']) ? (int)$c['total'] : null;
                          $currencyLabel = 'тыс. €';
                          $calcHtml = '<div class="mt-2 p-2 border rounded bg-light">'
                                   . '<div class="small text-muted mb-1">Калькулятор</div>'
                                   . '<div class="small">Программа: <strong>' . h($program) . '</strong></div>'
                                   . ($base !== null ? '<div class="small">База: <strong>' . h((string)$base) . ' ' . $currencyLabel . '</strong></div>' : '')
                                   . ($children !== null ? '<div class="small">Детей: <strong>' . h((string)$children) . '</strong></div>' : '')
                                   . ($childPer !== null ? '<div class="small">За ребенка: <strong>' . h((string)$childPer) . ' ' . $currencyLabel . '</strong></div>' : '')
                                   . ($total !== null ? '<div class="small">Итого: <strong>' . h((string)$total) . ' ' . $currencyLabel . '</strong></div>' : '')
                                   . '</div>';
                        }
                      }
                      echo $calcHtml;
                    ?>
                  </td>
                  <td>
                    <div class="small">
                      <div><strong>IP:</strong> <?= h($r['ip'] ?? '') ?></div>
                      <div class="text-break" style="max-width:180px;">
                        <strong>UA:</strong> <?= h($r['user_agent']) ?>
                      </div>
                    </div>
                  </td>
                  <td class="text-center">
                    <?php
                      $badgeClass = match($r['status']) {
                        'new' => 'bg-primary',
                        'processed' => 'bg-success',
                        'spam' => 'bg-danger',
                        default => 'bg-secondary'
                      };
                      echo '<span class="badge '.$badgeClass.' badge-status">'.h($r['status']).'</span>';
                    ?>
                  </td>
                  <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                      <form method="post" action="/admin/update.php" class="d-inline" onsubmit="return confirm('Отметить как обработано?')">
                        <input type="hidden" name="csrf" value="<?=$csrf?>">
                        <input type="hidden" name="id" value="<?=$r['id']?>">
                        <input type="hidden" name="action" value="processed">
                        <button type="submit" class="btn btn-success btn-sm">
                          <i class="bi bi-check"></i>
                        </button>
                      </form>
                      <form method="post" action="/admin/update.php" class="d-inline" onsubmit="return confirm('Пометить как спам?')">
                        <input type="hidden" name="csrf" value="<?=$csrf?>">
                        <input type="hidden" name="id" value="<?=$r['id']?>">
                        <input type="hidden" name="action" value="spam">
                        <button type="submit" class="btn btn-warning btn-sm">
                          <i class="bi bi-exclamation-triangle"></i>
                        </button>
                      </form>
                      <form method="post" action="/admin/update.php" class="d-inline" onsubmit="return confirm('Удалить запись навсегда?')">
                        <input type="hidden" name="csrf" value="<?=$csrf?>">
                        <input type="hidden" name="id" value="<?=$r['id']?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-danger btn-sm">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <?php
      $pages = max(1, (int)ceil($total / $perPage));
      ?>
      <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="text-muted">
          <i class="bi bi-info-circle me-1"></i>Всего записей: <strong><?=$total?></strong>
        </div>
        <?php if ($pages > 1): ?>
          <nav aria-label="Пагинация">
            <ul class="pagination pagination-sm mb-0">
              <?php if ($page > 1): ?>
                <li class="page-item">
                  <a class="page-link" href="<?=urlKeep(['page'=>$page-1])?>">
                    <i class="bi bi-chevron-left"></i>
                  </a>
                </li>
              <?php endif; ?>
              
              <li class="page-item active">
                <span class="page-link"><?=$page?> / <?=$pages?></span>
              </li>
              
              <?php if ($page < $pages): ?>
                <li class="page-item">
                  <a class="page-link" href="<?=urlKeep(['page'=>$page+1])?>">
                    <i class="bi bi-chevron-right"></i>
                  </a>
                </li>
              <?php endif; ?>
            </ul>
          </nav>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Параметры сайта -->
<div class="modal fade" id="settingsModal" tabindex="-1" aria-labelledby="settingsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <input type="hidden" name="csrf" value="<?=$csrf?>">
      <input type="hidden" name="action" value="save_settings">
      <div class="modal-header">
        <h5 class="modal-title" id="settingsModalLabel">Параметры сайта</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
      </div>
      <div class="modal-body">
        <!-- Секция контактных данных -->
        <div class="settings-section">
          <h6>Контактные данные</h6>
          <div class="mb-3">
            <label class="form-label">Номер телефона (публично отображаемый)</label>
            <input type="text" name="phone" class="form-control" value="<?=h($settings['phone'] ?? '')?>" placeholder="+7 (495) 1-23-456">
            <div class="form-text">Введите в любом удобном формате — он будет подставлен в ссылку <code>tel:</code>.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?=h($settings['email'] ?? '')?>" placeholder="info@example.com">
          </div>
          <div class="mb-3">
            <label class="form-label">WhatsApp (номер для ссылки)</label>
            <input type="text" name="whatsapp" class="form-control" value="<?=h($settings['whatsapp'] ?? '')?>" placeholder="+7 495 123-45-67">
            <div class="form-text">Номер будет преобразован в формат для <code>https://wa.me/</code> (только цифры).</div>
          </div>
        </div>

        <!-- Секция управления кнопками -->
        <div class="settings-section">
          <h6>Управление отображением кнопок</h6>
          <p class="text-muted small mb-3">Управляйте видимостью кнопок связи в разделе после калькулятора</p>
          
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="show_phone_btn" id="show_phone_btn" <?= ($settings['show_phone_btn'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="show_phone_btn">
              Показывать кнопку "Позвонить"
            </label>
            <div class="form-text">Отображает кнопку для звонка по указанному телефону</div>
          </div>
          
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="show_email_btn" id="show_email_btn" <?= ($settings['show_email_btn'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="show_email_btn">
              Показывать кнопку "Написать e-mail"
            </label>
            <div class="form-text">Отображает кнопку для отправки email на указанный адрес</div>
          </div>
          
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="show_wa_btn" id="show_wa_btn" <?= ($settings['show_wa_btn'] ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="show_wa_btn">
              Показывать кнопку "Написать в WhatsApp"
            </label>
            <div class="form-text">Отображает кнопку для связи через WhatsApp</div>
          </div>
        </div>

        <!-- Секция цен калькулятора -->
        <div class="settings-section">
          <h6>Цены калькулятора</h6>
          <p class="text-muted small mb-3">Все значения указываются в тысячах евро</p>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Базовая стоимость — Обычная программа</label>
              <div class="input-group">
                <input type="number" min="0" step="1" name="calc_base_regular" class="form-control" value="<?= (int)($settings['calc_base_regular'] ?? 150) ?>">
                <span class="input-group-text">тыс. €</span>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Базовая стоимость — Ускоренная программа</label>
              <div class="input-group">
                <input type="number" min="0" step="1" name="calc_base_fast" class="form-control" value="<?= (int)($settings['calc_base_fast'] ?? 250) ?>">
                <span class="input-group-text">тыс. €</span>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Доплата за ребенка</label>
              <div class="input-group">
                <input type="number" min="0" step="1" name="calc_child_cost" class="form-control" value="<?= (int)($settings['calc_child_cost'] ?? 20) ?>">
                <span class="input-group-text">тыс. €</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
        <button type="submit" class="btn btn-primary">Сохранить</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>