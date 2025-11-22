<?php
function admin_audit(mysqli $conn, int $adminId, string $action, string $entity, int $entityId, array $meta=[]): void {
  $js = json_encode($meta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $stmt = $conn->prepare("INSERT INTO admin_audit (admin_id,action,entity,entity_id,meta_json) VALUES (?,?,?,?,?)");
  $stmt->bind_param('issis',$adminId,$action,$entity,$entityId,$js);
  $stmt->execute();
}
