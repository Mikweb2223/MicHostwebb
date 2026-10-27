<?php
require_once __DIR__.'/../config/bootstrap.php';$id=requireLogin();
$stmt=$pdo->prepare('SELECT id,role,first_name,last_name,hospital_name,email,phone,profile_image,blood_group,language,dark_mode,created_at FROM users WHERE id=? LIMIT 1');
$stmt->execute([$id]);$u=$stmt->fetch();if(!$u){http_response_code(404);exit(json_encode(['success'=>false,'message'=>'Profile not found.']));}
$u['name']=$u['role']==='hospital'?$u['hospital_name']:trim($u['first_name'].' '.$u['last_name']);echo json_encode(['success'=>true,'user'=>$u]);
