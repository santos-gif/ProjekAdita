<?php
declare(strict_types=1);

function show_dashboard(): void {
    $u=require_login();
    $mine=db()->prepare("SELECT * FROM projects WHERE owner_id=? ORDER BY created_at DESC LIMIT 8");$mine->execute([$u['id']]);$projects=$mine->fetchAll();
    $joins=db()->prepare("SELECT jr.*,p.title,p.id AS project_id,u.full_name FROM join_requests jr JOIN projects p ON p.id=jr.project_id JOIN users u ON u.id=jr.user_id WHERE p.owner_id=? AND jr.status='pending' ORDER BY jr.created_at DESC");$joins->execute([$u['id']]);$requests=$joins->fetchAll();
    $notif=db()->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5");$notif->execute([$u['id']]);$notifications=$notif->fetchAll();
    layout_header('Dashboard'); ?>
<div class="page-heading"><div><p class="eyebrow">DASHBOARD</p><h1>Halo, <?= e(explode(' ',$u['full_name'])[0]) ?> 👋</h1><p class="muted">Pantau aktivitas dan lanjutkan kolaborasimu.</p></div><a class="btn primary" href="/?page=project-create">+ Buat proyek</a></div>
<div class="stats-grid"><div class="stat-card"><span>Proyek dibuat</span><strong><?= count($projects) ?></strong></div><div class="stat-card"><span>Permintaan anggota</span><strong><?= count($requests) ?></strong></div><div class="stat-card"><span>Notifikasi terbaru</span><strong><?= count($notifications) ?></strong></div></div>
<div class="two-col"><section class="panel"><div class="section-head"><h2>Proyek saya</h2><a href="/?page=projects">Jelajahi semua →</a></div><?php foreach($projects as $p): ?><div class="list-row"><div><strong><?= e($p['title']) ?></strong><p class="small muted"><?= e($p['submission_status']) ?> · <?= e($p['status']) ?></p></div><?php if($p['submission_status']==='approved'): ?><a href="/?page=project&id=<?= (int)$p['id'] ?>">Detail</a><?php endif; ?></div><?php endforeach; ?><?php if(!$projects): ?><p class="muted">Kamu belum membuat proyek.</p><?php endif; ?></section>
<section class="panel"><h2>Notifikasi</h2><?php foreach($notifications as $n): ?><div class="list-row"><div><strong><?= e($n['title']) ?></strong><p class="small muted"><?= e($n['message']) ?></p></div></div><?php endforeach; ?><?php if(!$notifications): ?><p class="muted">Belum ada notifikasi.</p><?php endif; ?></section></div>
<?php if($requests): ?><section class="panel section-gap"><h2>Permintaan bergabung ke proyekmu</h2><?php foreach($requests as $r): ?><div class="list-row"><div><strong><?= e($r['full_name']) ?></strong><p class="small muted"><?= e($r['title']) ?> · <?= e($r['motivation']) ?></p></div><div class="action-row"><form method="post" action="/?action=join-review"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="accepted"><button class="btn tiny primary">Terima</button></form><form method="post" action="/?action=join-review"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="rejected"><button class="btn tiny light">Tolak</button></form></div></div><?php endforeach; ?></section><?php endif; ?>
<?php layout_footer();
}

function handle_join_review(): void {
    $u=require_login();verify_csrf();$id=(int)($_POST['request_id']??0);$decision=post_string('decision',20);
    if(!in_array($decision,['accepted','rejected'],true)){flash('error','Keputusan tidak valid.');redirect('/?page=dashboard');}
    $pdo=db();$pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare("SELECT jr.*,p.owner_id,p.id AS pid,p.title,p.members_needed,(SELECT COUNT(*) FROM project_members pm WHERE pm.project_id=p.id) AS total_members FROM join_requests jr JOIN projects p ON p.id=jr.project_id WHERE jr.id=? FOR UPDATE");
        $stmt->execute([$id]);$r=$stmt->fetch();
        if(!$r || (int)$r['owner_id']!==(int)$u['id'] || $r['status']!=='pending') throw new RuntimeException('Permintaan tidak dapat diproses.');
        if($decision==='accepted'){
            if((int)$r['total_members'] >= (int)$r['members_needed']+1) throw new RuntimeException('Kuota anggota telah penuh.');
            $pdo->prepare("INSERT IGNORE INTO project_members(project_id,user_id) VALUES(?,?)")->execute([$r['pid'],$r['user_id']]);
        }
        $pdo->prepare("UPDATE join_requests SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$decision,$u['id'],$id]);
        notify_user((int)$r['user_id'],'Permintaan bergabung diperbarui','Permintaanmu untuk '.$r['title'].' '.$decision.'.');
        $pdo->commit();flash('success','Permintaan berhasil diproses.');
    }catch(Throwable $e){$pdo->rollBack();flash('error',$e->getMessage());}
    redirect('/?page=dashboard');
}

function show_profile(): void {
    $u=require_login();layout_header('Profil Saya'); ?>
<div class="form-page"><p class="eyebrow">AKUN DAN IDENTITAS</p><h1>Profil saya</h1><form class="panel form-grid" method="post" action="/?action=profile-update"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<label class="span-2">Nama lengkap<input name="full_name" value="<?= e($u['full_name']) ?>" required></label>
<label>NIM<input name="nim" value="<?= e($u['nim']??'') ?>"></label><label>Semester<input type="number" min="1" max="20" name="semester" value="<?= e((string)($u['semester']??'')) ?>"></label>
<label class="span-2">Bio<textarea name="bio" rows="4"><?= e($u['bio']??'') ?></textarea></label>
<label>Visibilitas profil<select name="profile_visibility"><option value="public" <?= $u['profile_visibility']==='public'?'selected':'' ?>>Publik</option><option value="campus" <?= $u['profile_visibility']==='campus'?'selected':'' ?>>Pengguna kampus</option><option value="private" <?= $u['profile_visibility']==='private'?'selected':'' ?>>Privat</option></select></label>
<div class="span-2"><button class="btn primary">Simpan profil</button></div></form></div>
<?php layout_footer();
}
function handle_profile_update(): void {
    $u=require_login();verify_csrf();
    $stmt=db()->prepare("UPDATE users SET full_name=?,nim=?,semester=?,bio=?,profile_visibility=? WHERE id=?");
    $visibility=post_string('profile_visibility',20);if(!in_array($visibility,['public','campus','private'],true))$visibility='campus';
    $semester=(int)($_POST['semester']??0);
    $stmt->execute([post_string('full_name',150),post_string('nim',40)?:null,$semester?:null,post_string('bio',2000),$visibility,$u['id']]);
    flash('success','Profil berhasil diperbarui.');redirect('/?page=profile');
}
