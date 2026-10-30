<?php
declare(strict_types=1);

function show_review(): void {
    $u=require_role(['admin','moderator']);
    $rows=db()->query("SELECT p.*,u.full_name FROM projects p JOIN users u ON u.id=p.owner_id WHERE p.submission_status IN ('pending','under_review','revision_requested') ORDER BY p.created_at ASC")->fetchAll();
    layout_header('Moderasi proyek'); ?>
<div class="page-heading"><div><p class="eyebrow">PUSAT MODERASI</p><h1>Peninjauan proyek</h1><p class="muted">Tinjau proposal sebelum tampil di halaman eksplorasi.</p></div></div>
<?php foreach($rows as $p): ?><section class="panel review-card"><div class="section-head"><div><span class="pill"><?= e($p['submission_status']) ?></span><h2><?= e($p['title']) ?></h2><p class="muted small">Pengaju: <?= e($p['full_name']) ?></p></div></div><p><?= e($p['summary']) ?></p><details><summary>Lihat deskripsi lengkap</summary><p class="preline"><?= e($p['description']) ?></p></details>
<form method="post" action="/?action=project-review" class="review-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>"><label>Catatan / alasan<textarea name="note" rows="2" placeholder="Wajib diisi untuk penolakan atau revisi"></textarea></label><div class="action-row"><button class="btn primary" name="decision" value="approved">Setujui</button><button class="btn light" name="decision" value="revision_requested">Minta revisi</button><button class="btn danger" name="decision" value="rejected">Tolak</button></div></form></section><?php endforeach; ?>
<?php if(!$rows): ?><div class="empty-state">Tidak ada pengajuan yang menunggu peninjauan.</div><?php endif; ?>
<?php layout_footer();
}
function handle_project_review(): void {
    $u=require_role(['admin','moderator']);verify_csrf();$id=(int)($_POST['project_id']??0);$decision=post_string('decision',30);$note=post_string('note',3000);
    if(!in_array($decision,['approved','revision_requested','rejected'],true)){flash('error','Keputusan tidak valid.');redirect('/?page=review');}
    if($decision!=='approved' && strlen($note)<5){flash('error','Berikan alasan minimal 5 karakter.');redirect('/?page=review');}
    $pdo=db();$pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare("SELECT owner_id,title FROM projects WHERE id=? FOR UPDATE");$stmt->execute([$id]);$p=$stmt->fetch();
        if(!$p)throw new RuntimeException('Proyek tidak ditemukan.');
        $pdo->prepare("UPDATE projects SET submission_status=?,reviewed_by=?,reviewed_at=NOW(),review_note=? WHERE id=?")->execute([$decision,$u['id'],$note,$id]);
        notify_user((int)$p['owner_id'],'Status pengajuan proyek diperbarui','Pengajuan '.$p['title'].': '.$decision.'. '.$note);
        audit('project_'.$decision,'project',$id,$note);$pdo->commit();
        flash('success','Keputusan moderasi tersimpan.');
    }catch(Throwable $e){$pdo->rollBack();flash('error',$e->getMessage());}
    redirect('/?page=review');
}
