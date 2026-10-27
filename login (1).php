<?php
require_once __DIR__.'/../config/bootstrap.php';$id=requireLogin();
if(($_SESSION['role']??'')!=='hospital'){http_response_code(403);exit(json_encode(['success'=>false,'message'=>'Hospital account required.']));}
$group=$_POST['blood_group']??'';$units=filter_var($_POST['units']??null,FILTER_VALIDATE_INT);
$allowed=['A+','A-','B+','B-','AB+','AB-','O+','O-'];
if(!in_array($group,$allowed,true)||$units===false||$units<1){http_response_code(400);exit(json_encode(['success'=>false,'message'=>'Invalid blood data.']));}
$stmt=$pdo->prepare("SELECT id FROM hospitals WHERE user_id=? AND verification_status='verified' LIMIT 1");$stmt->execute([$id]);$h=$stmt->fetch();
if(!$h){http_response_code(403);exit(json_encode(['success'=>false,'message'=>'Hospital verification required.']));}
$stmt=$pdo->prepare("INSERT INTO blood_inventory(hospital_id,blood_group,units) VALUES(?,?,?) ON DUPLICATE KEY UPDATE units=units+VALUES(units)");
$stmt->execute([(int)$h['id'],$group,$units]);echo json_encode(['success'=>true,'message'=>'Inventory updated.']);
