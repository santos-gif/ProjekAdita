<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('kampus_session');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $isHttps,
    'httponly' => true, 'samesite' => 'Lax'
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $path): never {
    header('Location: ' . $path); exit;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verify_csrf(): void {
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419); exit('Sesi formulir tidak valid. Muat ulang halaman.');
    }
}
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}
function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare("SELECT u.id,u.full_name,u.email,u.role,u.nim,u.semester,u.bio,u.profile_visibility,
            sp.name AS program_name FROM users u LEFT JOIN study_programs sp ON sp.id=u.study_program_id
            WHERE u.id=? AND u.is_active=1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: false;
    }
    return $user ?: null;
}
function require_login(): array {
    $user = current_user();
    if (!$user) { flash('error','Silakan login terlebih dahulu.'); redirect('/?page=login'); }
    return $user;
}
function require_role(array $roles): array {
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) { http_response_code(403); exit('Akses ditolak.'); }
    return $user;
}
function audit(string $action, string $entity, ?int $id, ?string $reason = null): void {
    $stmt = db()->prepare("INSERT INTO audit_logs(actor_id,action,entity_type,entity_id,reason,ip_address) VALUES(?,?,?,?,?,?)");
    $stmt->execute([$_SESSION['user_id'] ?? null,$action,$entity,$id,$reason,$_SERVER['REMOTE_ADDR'] ?? null]);
}
function notify_user(int $userId, string $title, string $message): void {
    $stmt = db()->prepare("INSERT INTO notifications(user_id,title,message) VALUES(?,?,?)");
    $stmt->execute([$userId,$title,$message]);
}
function layout_header(string $title): void {
    $user = current_user();
    ?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title) ?> · KampusKolab</title><link rel="stylesheet" href="/assets/css/style.css"></head><body>
    <header class="topbar"><a class="brand" href="/"><span class="brand-mark">K</span>Kampus<span>Kolab</span></a>
    <nav><a href="/?page=projects">Jelajahi Proyek</a><?php if($user): ?><a href="/?page=dashboard">Dashboard</a><a href="/?page=profile">Profil</a>
    <?php if(in_array($user['role'],['admin','moderator'],true)): ?><a href="/?page=review">Moderasi</a><?php endif; ?>
    <form class="inline" method="post" action="/?action=logout"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="link-button">Keluar</button></form>
    <?php else: ?><a href="/?page=login">Masuk</a><a class="nav-cta" href="/?page=register">Daftar</a><?php endif; ?></nav></header><main class="container">
    <?php if (!empty($_SESSION['flash'])): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?>
      <div class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endif; ?>
    <?php
}
function layout_footer(): void {
    ?></main><footer class="footer">© <?= date('Y') ?> KampusKolab · Ruang kolaborasi akademik</footer><script src="/assets/js/app.js"></script></body></html><?php
}
function post_string(string $key, int $max=5000): string {
    $value = trim((string)($_POST[$key] ?? ''));
    return mb_substr($value,0,$max);
}
