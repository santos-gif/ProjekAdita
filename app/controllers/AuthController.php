<?php
declare(strict_types=1);

function show_login(): void { layout_header('Masuk'); ?>
<section class="auth-wrap"><div class="auth-card"><p class="eyebrow">SELAMAT DATANG KEMBALI</p><h1>Masuk ke KampusKolab</h1><p class="muted">Lanjutkan proyek dan kolaborasimu.</p>
<form method="post" action="/?action=login">
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<label>Email kampus<input type="email" name="email" required autocomplete="username"></label>
<label>Password<input type="password" name="password" required autocomplete="current-password"></label>
<button class="btn primary full">Masuk</button></form><p class="small center">Belum punya akun? <a href="/?page=register">Daftar sekarang</a></p></div></section>
<?php layout_footer(); }

function show_register(): void {
    $programs=db()->query("SELECT id,name FROM study_programs WHERE is_active=1 ORDER BY name")->fetchAll();
    layout_header('Daftar'); ?>
<section class="auth-wrap"><div class="auth-card"><p class="eyebrow">MULAI BERKOLABORASI</p><h1>Buat akun</h1><p class="muted">Gunakan email aktif. Verifikasi email kampus perlu diaktifkan sebelum produksi.</p>
<form method="post" action="/?action=register"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<label>Nama lengkap<input name="full_name" maxlength="150" required></label>
<label>Email<input type="email" name="email" maxlength="190" required></label>
<label>Program studi<select name="study_program_id"><option value="">Pilih program studi</option><?php foreach($programs as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></label>
<label>Password<input type="password" name="password" minlength="10" required><small>Minimal 10 karakter.</small></label>
<label>Konfirmasi password<input type="password" name="password_confirmation" minlength="10" required></label>
<button class="btn primary full">Buat akun</button></form><p class="small center">Sudah punya akun? <a href="/?page=login">Masuk</a></p></div></section>
<?php layout_footer(); }

function handle_login(): void {
    verify_csrf();
    $email=strtolower(post_string('email',190)); $password=(string)($_POST['password'] ?? '');
    $stmt=db()->prepare("SELECT * FROM users WHERE email=? AND is_active=1 LIMIT 1"); $stmt->execute([$email]); $u=$stmt->fetch();
    if (!$u || ($u['locked_until'] && strtotime($u['locked_until'])>time()) || !password_verify($password,$u['password_hash'])) {
        if($u) {
            $attempts=(int)$u['failed_login_attempts']+1;
            $locked=$attempts>=5 ? date('Y-m-d H:i:s',time()+900) : null;
            db()->prepare("UPDATE users SET failed_login_attempts=?,locked_until=? WHERE id=?")->execute([$attempts,$locked,$u['id']]);
        }
        flash('error','Email atau password tidak valid, atau akun sedang dibatasi.'); redirect('/?page=login');
    }
    db()->prepare("UPDATE users SET failed_login_attempts=0,locked_until=NULL WHERE id=?")->execute([$u['id']]);
    session_regenerate_id(true); $_SESSION['user_id']=$u['id']; $_SESSION['csrf']=bin2hex(random_bytes(32));
    redirect('/?page=dashboard');
}
function handle_register(): void {
    verify_csrf();
    $name=post_string('full_name',150); $email=strtolower(post_string('email',190));
    $pass=(string)($_POST['password']??''); $confirm=(string)($_POST['password_confirmation']??'');
    $program=(int)($_POST['study_program_id']??0);
    if(strlen($name)<3 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($pass)<10 || $pass!==$confirm) {
        flash('error','Periksa nama, email, dan password. Password minimal 10 karakter dan harus sama.'); redirect('/?page=register');
    }
    try {
        $stmt=db()->prepare("INSERT INTO users(full_name,email,password_hash,study_program_id) VALUES(?,?,?,?)");
        $stmt->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),$program ?: null]);
        session_regenerate_id(true); $_SESSION['user_id']=(int)db()->lastInsertId();
        flash('success','Akun berhasil dibuat. Lengkapi profilmu.'); redirect('/?page=dashboard');
    } catch(PDOException $e) {
        if($e->getCode()==='23000') { flash('error','Email sudah terdaftar.'); redirect('/?page=register'); }
        throw $e;
    }
}
function handle_logout(): void { verify_csrf(); $_SESSION=[]; session_destroy(); redirect('/?page=login'); }
