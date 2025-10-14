<?php
// /tools/label_lib.php
declare(strict_types=1);

/** Upsert a single order_meta key */
function od_meta_upsert(mysqli $conn, int $order_id, string $key, string $val): void {
    $sql = "INSERT INTO order_meta (order_id, meta_key, meta_value)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value)";
    $st = $conn->prepare($sql);
    $st->bind_param('iss', $order_id, $key, $val);
    $st->execute(); $st->close();
}

/** Read a single order_meta key (nullable) */
function od_meta_get(mysqli $conn, int $order_id, string $key): ?string {
    $st = $conn->prepare("SELECT meta_value FROM order_meta WHERE order_id=? AND meta_key=? LIMIT 1");
    $st->bind_param('is', $order_id, $key);
    $st->execute();
    $res = $st->get_result()->fetch_column();
    $st->close();
    return $res !== null ? (string)$res : null;
}

/** Convert phase -> meta key */
function label_phase_key(string $phase): string {
    return match ($phase) {
        'office'   => 'label_checked_out_at',
        'pickup'   => 'pickup_scanned_at',
        'delivery' => 'delivery_scanned_at',
        default    => 'pickup_scanned_at',
    };
}

/** Record the audit timestamp (and who scanned) */
function label_record_audit(mysqli $conn, int $order_id, string $phase, int $driver_id): void {
    $key = label_phase_key($phase);
    $now = date('Y-m-d H:i:s');
    od_meta_upsert($conn, $order_id, $key, $now);
    od_meta_upsert($conn, $order_id, $key.'_by', (string)$driver_id);
}

/**
 * Parse whatever the scanner gives us and extract order_id/label_id.
 * Supports:
 *   - Tiny route like /t/66-<32hex>
 *   - URL with order_id=66
 *   - Raw numeric like "66"
 */
function label_parse(string $raw): array {
    $raw = trim($raw);
    $order_id = 0; $label_id = '';

    if (preg_match('~(?:^|/|\\b)t/([0-9]+)-([A-Fa-f0-9]{32})\\b~', $raw, $m)) {
        $order_id = (int)$m[1]; $label_id = strtolower($m[2]);
    } elseif (preg_match('~order_id=([0-9]+)~i', $raw, $m)) {
        $order_id = (int)$m[1];
    } elseif (preg_match('~\\b([0-9]{1,10})\\b~', $raw, $m)) {
        $order_id = (int)$m[1];
    }
    return ['order_id'=>$order_id, 'label_id'=>$label_id];
}
