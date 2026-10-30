<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/core/bootstrap.php';
require_once __DIR__ . '/../app/controllers/AuthController.php';
require_once __DIR__ . '/../app/controllers/ProjectController.php';
require_once __DIR__ . '/../app/controllers/DashboardController.php';
require_once __DIR__ . '/../app/controllers/AdminController.php';

$action=$_GET['action']??null;
$actions=[
 'login'=>'handle_login','register'=>'handle_register','logout'=>'handle_logout',
 'project-create'=>'handle_project_create','join'=>'handle_join_request',
 'join-review'=>'handle_join_review','profile-update'=>'handle_profile_update',
 'project-review'=>'handle_project_review'
];
if($action && isset($actions[$action])) { $actions[$action](); exit; }

$page=$_GET['page']??'home';
switch($page){
 case 'login': show_login(); break;
 case 'register': show_register(); break;
 case 'projects': show_projects(); break;
 case 'project': show_project_detail(); break;
 case 'project-create': show_project_create(); break;
 case 'dashboard': show_dashboard(); break;
 case 'profile': show_profile(); break;
 case 'review': show_review(); break;
 case 'home':
 default:
   layout_header('Kolaborasi dimulai di sini'); ?>
   <section class="hero"><div class="hero-copy"><p class="eyebrow">SATU KAMPUS · BANYAK KEMUNGKINAN</p><h1>Ide hebat tumbuh saat kita <span>berkolaborasi.</span></h1><p>Temukan rekan lintas program studi, bangun proyek nyata, dan tampilkan karya terbaikmu dalam satu ruang.</p><div class="hero-actions"><a class="btn primary" href="/?page=projects">Jelajahi proyek</a><a class="btn light" href="/?page=register">Mulai bergabung</a></div><div class="hero-proof"><span>✦ 11 program studi</span><span>✦ Kolaborasi lintas disiplin</span><span>✦ Portofolio akademik</span></div></div><div class="hero-visual"><div class="floating-card card-a"><span class="bubble purple">✦</span><strong>Temukan tim</strong><small>Kolaborasi lintas prodi</small></div><div class="orbit"><div class="orbit-center">K</div><span class="orbit-dot dot1">💻</span><span class="orbit-dot dot2">🎨</span><span class="orbit-dot dot3">📊</span><span class="orbit-dot dot4">🏙️</span></div><div class="floating-card card-b"><span class="bubble green">✓</span><strong>Wujudkan karya</strong><small>Dari ide menjadi dampak</small></div></div></section>
   <section class="section-block"><div class="section-head"><div><p class="eyebrow">MULAI DARI SINI</p><h2>Satu platform, banyak peluang.</h2></div><a href="/?page=projects">Lihat semua proyek →</a></div><div class="feature-grid"><article class="feature"><span>01</span><h3>Temukan proyek</h3><p>Jelajahi ide menarik sesuai minat, skill, dan program studimu.</p></article><article class="feature"><span>02</span><h3>Bangun kolaborasi</h3><p>Ajukan diri bergabung dan temukan rekan dengan keahlian berbeda.</p></article><article class="feature"><span>03</span><h3>Tampilkan karya</h3><p>Bangun rekam jejak proyek dan portofolio akademik.</p></article></div></section>
   <?php layout_footer(); break;
}
