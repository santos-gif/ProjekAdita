<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/core/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
try {
    $q=trim((string)($_GET['q']??''));
    $stmt=db()->prepare("SELECT p.id,p.title,p.slug,p.category,p.summary,p.status,p.created_at,u.full_name,sp.name AS program
      FROM projects p JOIN users u ON u.id=p.owner_id LEFT JOIN study_programs sp ON sp.id=u.study_program_id
      WHERE p.submission_status='approved' AND p.visibility IN ('campus','public') AND (p.title LIKE ? OR p.summary LIKE ?)
      ORDER BY p.created_at DESC LIMIT 30");
    $like='%'.$q.'%';$stmt->execute([$like,$like]);
    echo json_encode(['success'=>true,'data'=>$stmt->fetchAll()],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
} catch(Throwable $e) {
    http_response_code(500);echo json_encode(['success'=>false,'message'=>'Terjadi kesalahan server.']);
}
