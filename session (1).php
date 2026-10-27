<?php
require_once __DIR__.'/../config/bootstrap.php';$id=requireLogin();
if(($_SESSION['role']??'')!=='hospital'){http_response_code(403);exit(json_encode(['success'=>false,'message'=>'Hospital account required.']));}
$stmt=$pdo->prepare("SELECT bi.blood_group,bi.units FROM blood_inventory bi INNER JOIN hospitals h ON h.id=bi.hospital_id WHERE h.user_id=? ORDER BY bi.blood_group");
$stmt->execute([$id]);echo json_encode(['success'=>true,'inventory'=>$stmt->fetchAll()]);
