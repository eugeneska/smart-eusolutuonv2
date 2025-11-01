<?php
// contact-submit.php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');

require __DIR__ . '/config.php';

// CORS (нужно только если отправляете с другого домена)
if (!empty($_SERVER['HTTP_ORIGIN'])) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST   _METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

$respondJson = str_contains(($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
    || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

function jsonOut(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($respondJson) {
        jsonOut(405, ['ok' => false, 'error' => 'Method Not Allowed']);
    } else {
        http_response_code(405);
        echo 'Method Not Allowed';
        exit;
    }
}

// reCaptcha v2
$error = true; $secret = '6LcALuUrAAAAABA-Ye804wUjVi9LoU6W6-uIMa0r'; if (!empty($_POST['g-recaptcha-response'])) { $curl = curl_init('https://www.google.com/recaptcha/api/siteverify'); curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, 'secret=' . $secret . '&response=' . $_POST['g-recaptcha-response']); $out = curl_exec($curl); curl_close($curl); $out = json_decode($out); if ($out->success == true) { $error = false; } } if ($error) { echo 'Ошибка заполнения капчи.'; }

// --- Ввод ---
$name    = trim((string)($_POST['name']    ?? ''));
$email   = trim((string)($_POST['email']   ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

// Анти-бот (honeypot): скрытое поле, должно быть пустым
$website = trim((string)($_POST['website'] ?? ''));

// Простая проверка времени заполнения формы (>= 3 сек)
$formTs  = isset($_POST['form_ts']) ? (int)$_POST['form_ts'] : 0;
$tooFast = $formTs > 0 && (time() - $formTs) < 3;

// Валидация
$errors = [];

if ($website !== '') {
    $errors[] = 'spam_detected';
}
if ($tooFast) {
    $errors[] = 'too_fast';
}
if ($name === '') {
    $errors[] = 'name_required';
}
if ($message === '' || mb_strlen($message) < 5) {
    $errors[] = 'message_too_short';
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'email_invalid';
}

if ($errors) {
    $out = ['ok' => false, 'errors' => $errors];
    $respondJson ? jsonOut(422, $out) : (print 'Ошибка: ' . htmlspecialchars(implode(', ', $errors), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'));
    exit;
}

// --- Простейший rate limit по IP: не чаще одной заявки в 30 сек ---
$ipBin = inet_pton($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') ?: str_repeat("\0", 16);
$pdo   = pdo();

$recent = $pdo->prepare("SELECT COUNT(*) FROM contact_requests WHERE ip = :ip AND created_at >= (NOW() - INTERVAL 30 SECOND)");
$recent->execute([':ip' => $ipBin]);
if ((int)$recent->fetchColumn() > 0) {
    $out = ['ok' => false, 'errors' => ['rate_limited']];
    $respondJson ? jsonOut(429, $out) : (print 'Слишком часто. Попробуйте через минуту.');
    exit;
}

// Сбор метаданных
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
$meta = [
    'referer'  => $_SERVER['HTTP_REFERER']  ?? null,
    'page'     => $_POST['page']            ?? null, // можно передавать с фронта, на какой странице отправили
    'utm'      => $_POST['utm']             ?? null, // сюда можно класть массив UTM-меток
];

// Данные калькулятора (если были переданы)
$calcUsed = isset($_POST['calc_used']) ? (int)$_POST['calc_used'] : 0;
if ($calcUsed === 1) {
    $calcProgram = $_POST['calc_program'] ?? null; // 'regular' | 'fast'
    $calcBase    = isset($_POST['calc_base_cost']) ? (int)$_POST['calc_base_cost'] : null; // тыс. €
    $calcChildren= isset($_POST['calc_children']) ? (int)$_POST['calc_children'] : null;
    $calcChildPer= isset($_POST['calc_child_cost_per']) ? (int)$_POST['calc_child_cost_per'] : null; // тыс. €
    $calcTotal   = isset($_POST['calc_total']) ? (int)$_POST['calc_total'] : null; // тыс. €

    $meta['calculator'] = [
        'program'      => $calcProgram,
        'base_cost'    => $calcBase,
        'children'     => $calcChildren,
        'child_cost'   => $calcChildPer,
        'total'        => $calcTotal,
        'currency'     => 'kEUR', // тысячи евро, чтобы не путать
    ];
}

// Запись в БД
$stmt = $pdo->prepare("
    INSERT INTO contact_requests (name, email, message, ip, user_agent, meta)
    VALUES (:name, :email, :message, :ip, :ua, :meta)
");

$stmt->execute([
    ':name'    => $name,
    ':email'   => ($email !== '' ? $email : null),
    ':message' => $message,
    ':ip'      => $ipBin,
    ':ua'      => $userAgent,
    ':meta'    => json_encode($meta, JSON_UNESCAPED_UNICODE),
]);

// Кому отправлять
$to = "smart-eu-decision@proton.me";

// Тема письма
$subject = "Новая заявка с сайта";

// Проверка заполненности
if (empty($name) || empty($email) || empty($message)) {
    die("Ошибка: заполните все поля.");
}

// Формирование письма
$body = "Имя: $name\nEmail: $email\n\nСообщение:\n$message";

// Заголовки
$headers = "From: $email\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "Content-Type: text/plain; charset=utf-8\r\n";

// Отправка
mail($to, $subject, $body, $headers);

// Успех
if ($respondJson) {
    jsonOut(200, ['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
} else {
    // Фолбэк для обычной формы без JS
    echo 'Спасибо! Ваша заявка принята.';
}