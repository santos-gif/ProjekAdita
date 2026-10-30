<?php
declare(strict_types=1);

function show_projects(): void {
    $q=trim((string)($_GET['q']??'')); $program=(int)($_GET['program']??0); $category=(string)($_GET['category']??'');
    $sql="SELECT p.*,u.full_name,sp.name AS program_name,
      (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id=p.id) AS member_count,
      (SELECT COUNT(*) FROM join_requests jr WHERE jr.project_id=p.id AND jr.status='pending') AS applicant_count
      FROM projects p JOIN users u ON u.id=p.owner_id LEFT JOIN study_programs sp ON sp.id=u.study_program_id
      WHERE p.submission_status='approved' AND p.visibility IN ('campus','public')";
    $params=[];
    if($q!==''){ $sql.=" AND (p.title LIKE ? OR p.summary LIKE ? OR p.description LIKE ?)"; $like="%{$q}%"; array_push($params,$like,$like,$like); }
    if($program){$sql.=" AND u.study_program_id=?";$params[]=$program;}
    if(in_array($category,['small','medium','research','creative','competition'],true)){$sql.=" AND p.category=?";$params[]=$category;}
    $sql.=" ORDER BY p.created_at DESC LIMIT 60";
    $stmt=db()->prepare($sql);$stmt->execute($params);$projects=$stmt->fetchAll();
    $programs=db()->query("SELECT id,name FROM study_programs WHERE is_active=1 ORDER BY name")->fetchAll();
    layout_header('Jelajahi Proyek'); ?>
<div class="page-heading"><div><p class="eyebrow">TEMUKAN KOLABORASI</p><h1>Proyek untuk ide hebatmu.</h1><p class="muted">Temukan tim, kembangkan kemampuan, dan hasilkan karya nyata.</p></div><?php if(current_user()): ?><a class="btn primary" href="/?page=project-create">+ Ajukan proyek</a><?php endif; ?></div>
<form class="filter-bar" method="get"><input type="hidden" name="page" value="projects"><input name="q" placeholder="Cari judul, skill, atau deskripsi..." value="<?= e($q) ?>">
<select name="program"><option value="">Semua program studi</option><?php foreach($programs as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $program===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select>
<select name="category"><option value="">Semua kategori</option><?php foreach(['small'=>'Proyek kecil','medium'=>'Proyek medium','research'=>'Penelitian','creative'=>'Kreatif','competition'=>'Kompetisi'] as $k=>$v): ?><option value="<?= $k ?>" <?= $category===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select><button class="btn dark">Cari</button><a class="btn light" href="/?page=projects">Reset</a></form>
<div class="project-grid"><?php foreach($projects as $p): ?><article class="project-card"><div class="card-top"><span class="pill"><?= e(ucfirst($p['category'])) ?></span><span class="status"><?= e($p['status']) ?></span></div><h3><a href="/?page=project&id=<?= (int)$p['id'] ?>"><?= e($p['title']) ?></a></h3><p><?= e($p['summary']) ?></p><div class="card-meta"><span><?= e($p['program_name']??'Lintas program studi') ?></span><span><?= (int)$p['member_count'] ?> anggota</span></div><div class="card-bottom"><span class="muted small">Oleh <?= e($p['full_name']) ?></span><a href="/?page=project&id=<?= (int)$p['id'] ?>">Lihat detail →</a></div></article><?php endforeach; ?>
<?php if(!$projects): ?><div class="empty-state">Belum ada proyek yang cocok. Coba ubah kata kunci atau filter.</div><?php endif; ?></div>
<?php layout_footer();
}

function show_project_create(): void {
    require_login(); layout_header('Ajukan Proyek'); ?>
<div class="form-page"><p class="eyebrow">BAGIKAN IDEMU</p><h1>Ajukan proyek baru</h1><p class="muted">Pengajuan akan ditinjau moderator/admin sebelum diterbitkan.</p>
<form class="panel form-grid" method="post" action="/?action=project-create"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<label class="span-2">Judul proyek<input name="title" maxlength="200" required></label>
<label>Kategori<select name="category"><option value="small">Proyek kecil</option><option value="medium">Proyek medium</option><option value="research">Penelitian</option><option value="creative">Kreatif</option><option value="competition">Kompetisi</option></select></label>
<label>Jumlah anggota dibutuhkan<input type="number" name="members_needed" min="1" max="50" value="2" required></label>
<label class="span-2">Ringkasan<textarea name="summary" maxlength="500" rows="2" required></textarea></label>
<label class="span-2">Deskripsi dan permasalahan<textarea name="description" rows="5" required></textarea></label>
<label>Keahlian dibutuhkan <small>Pisahkan dengan koma</small><input name="skills" placeholder="PHP, UI/UX, Database"></label>
<label>Tenggat waktu<input type="date" name="deadline" min="<?= date('Y-m-d') ?>"></label>
<label>Durasi (minggu)<input type="number" name="duration_weeks" min="1" max="104"></label>
<label>Dosen pembimbing (opsional)<input name="supervisor_name"></label>
<label class="span-2">Luaran yang diharapkan<textarea name="expected_output" rows="3"></textarea></label>
<div class="span-2"><button class="btn primary">Kirim untuk ditinjau</button></div></form></div>
<?php layout_footer();
}

function handle_project_create(): void {
    $u=require_login(); verify_csrf();
    $title=post_string('title',200);$summary=post_string('summary',500);$description=post_string('description',12000);
    $category=post_string('category',30);$members=max(1,min(50,(int)($_POST['members_needed']??1)));
    if(strlen($title)<5 || strlen($summary)<10 || strlen($description)<20 || !in_array($category,['small','medium','research','creative','competition'],true)){
        flash('error','Data proyek belum memenuhi ketentuan.');redirect('/?page=project-create');
    }
    $slug=strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/','-',iconv('UTF-8','ASCII//TRANSLIT',$title)),'-')).'-'.bin2hex(random_bytes(3));
    $skills=array_values(array_filter(array_map('trim',explode(',',post_string('skills',500)))));
    $deadline=post_string('deadline',10) ?: null;
    $stmt=db()->prepare("INSERT INTO projects(owner_id,title,slug,category,summary,description,expected_output,required_skills,members_needed,duration_weeks,deadline,supervisor_name)
      VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$u['id'],$title,$slug,$category,$summary,$description,post_string('expected_output',3000),json_encode($skills),$members,(int)($_POST['duration_weeks']??0) ?: null,$deadline,post_string('supervisor_name',150) ?: null]);
    $id=(int)db()->lastInsertId(); audit('project_submitted','project',$id); flash('success','Pengajuan proyek dikirim dan menunggu peninjauan.');redirect('/?page=dashboard');
}

function show_project_detail(): void {
    $id=(int)($_GET['id']??0);
    $stmt=db()->prepare("SELECT p.*,u.full_name,u.id AS owner_id,sp.name AS program_name FROM projects p JOIN users u ON u.id=p.owner_id LEFT JOIN study_programs sp ON sp.id=u.study_program_id WHERE p.id=? AND p.submission_status='approved'");
    $stmt->execute([$id]);$p=$stmt->fetch();if(!$p){http_response_code(404);layout_header('Tidak ditemukan');echo '<div class="empty-state">Proyek tidak ditemukan.</div>';layout_footer();return;}
    $m=db()->prepare("SELECT u.full_name,pm.member_role FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=?");$m->execute([$id]);$members=$m->fetchAll();
    layout_header($p['title']); ?>
<a class="back-link" href="/?page=projects">← Kembali ke proyek</a><section class="detail-hero"><span class="pill"><?= e($p['category']) ?></span><h1><?= e($p['title']) ?></h1><p class="lead"><?= e($p['summary']) ?></p><div class="card-meta"><span>Oleh <?= e($p['full_name']) ?></span><span><?= e($p['program_name']??'Lintas prodi') ?></span><span><?= count($members) ?> anggota</span></div></section>
<div class="detail-layout"><article class="panel"><h2>Tentang proyek</h2><p class="preline"><?= e($p['description']) ?></p><?php if($p['expected_output']): ?><h3>Luaran</h3><p><?= e($p['expected_output']) ?></p><?php endif; ?><h3>Keahlian yang dibutuhkan</h3><div class="tag-list"><?php foreach((json_decode($p['required_skills']??'[]',true)?:[]) as $s): ?><span class="tag"><?= e($s) ?></span><?php endforeach; ?></div><h3>Anggota tim</h3><?php foreach($members as $m): ?><div class="member-row"><span class="avatar"><?= e(strtoupper(substr($m['full_name'],0,1))) ?></span><?= e($m['full_name']) ?><span class="muted small"><?= e($m['member_role']) ?></span></div><?php endforeach; ?></article>
<aside class="panel join-panel"><h3>Ingin bergabung?</h3><p class="muted small">Kirim motivasi dan keahlianmu kepada pemimpin proyek.</p><?php if(current_user() && current_user()['id']!=$p['owner_id']): ?><form method="post" action="/?action=join"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="project_id" value="<?= $id ?>"><label>Motivasi<textarea name="motivation" rows="4" required></textarea></label><label>Keahlian relevan<textarea name="skills" rows="2"></textarea></label><label>Ketersediaan<input name="availability" placeholder="Contoh: 6 jam/minggu"></label><button class="btn primary full">Ajukan bergabung</button></form><?php elseif(!current_user()): ?><a class="btn primary full" href="/?page=login">Login untuk bergabung</a><?php else: ?><p class="small muted">Kamu adalah pemimpin proyek ini.</p><?php endif; ?><hr><p class="small muted">Status: <?= e($p['status']) ?><br>Anggota dibutuhkan: <?= (int)$p['members_needed'] ?><br>Tenggat: <?= e($p['deadline']??'Belum ditentukan') ?></p></aside></div>
<?php layout_footer();
}

function handle_join_request(): void {
    $u=require_login();verify_csrf();$project=(int)($_POST['project_id']??0);
    $stmt=db()->prepare("SELECT p.*, (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id=p.id) AS total_members FROM projects p WHERE p.id=? AND p.submission_status='approved' AND p.status='recruiting'");
    $stmt->execute([$project]);$p=$stmt->fetch();
    if(!$p || $p['owner_id']==$u['id'] || (int)$p['total_members'] >= (int)$p['members_needed']+1){flash('error','Proyek tidak tersedia untuk permintaan bergabung.');redirect('/?page=projects');}
    try {
        $stmt=db()->prepare("INSERT INTO join_requests(project_id,user_id,motivation,skills,availability) VALUES(?,?,?,?,?)");
        $stmt->execute([$project,$u['id'],post_string('motivation',3000),post_string('skills',1500),post_string('availability',150)]);
        notify_user((int)$p['owner_id'],'Permintaan anggota baru',$u['full_name'].' ingin bergabung dengan '.$p['title'].'.');
        flash('success','Permintaan bergabung berhasil dikirim.');
    } catch(PDOException $e){flash('error','Kamu sudah pernah mengajukan permintaan untuk proyek ini.');}
    redirect('/?page=project&id='.$project);
}
