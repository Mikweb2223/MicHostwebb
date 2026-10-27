<?php
require_once __DIR__.'/../config/bootstrap.php';requireLogin();
$lat=filter_var($_GET['latitude']??null,FILTER_VALIDATE_FLOAT);$lng=filter_var($_GET['longitude']??null,FILTER_VALIDATE_FLOAT);
if($lat===false||$lng===false){http_response_code(400);exit(json_encode(['success'=>false,'message'=>'Latitude and longitude are required.']));}
$sql="SELECT h.id,h.hospital_name,h.address,h.city,h.state,h.phone,h.email,h.latitude,h.longitude,
(6371*ACOS(LEAST(1,COS(RADIANS(?))*COS(RADIANS(h.latitude))*COS(RADIANS(h.longitude)-RADIANS(?))+SIN(RADIANS(?))*SIN(RADIANS(h.latitude))))) AS distance_km
FROM hospitals h WHERE h.verification_status='verified' AND h.latitude IS NOT NULL AND h.longitude IS NOT NULL
HAVING distance_km IS NOT NULL ORDER BY distance_km ASC LIMIT 50";
$stmt=$pdo->prepare($sql);$stmt->execute([$lat,$lng,$lat]);echo json_encode(['success'=>true,'hospitals'=>$stmt->fetchAll()]);
