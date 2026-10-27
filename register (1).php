<?php
require_once __DIR__.'/../config/bootstrap.php';$uid=requireLogin();$nid=(int)($_POST['id']??0);
$stmt=$pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?');$stmt->execute([$nid,$uid]);echo json_encode(['success'=>true]);
