<?php
/* Global duplicate guard for requests replayed from the offline queue.
 * Add ONE line near the top of init.php (after the session/DB are ready):
 *     require_once __DIR__ . '/includes/offline_dedupe.php';
 * Delete the old includes/dedupe.php. The table is created automatically.
 * Adjust the DB variable below if your db_connect.php uses a different name.
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_SERVER['HTTP_X_CLIENT_REQUEST_ID'])) {
    $__rid = (string)$_SERVER['HTTP_X_CLIENT_REQUEST_ID'];
    if (preg_match('/^[A-Za-z0-9\-]{8,64}$/', $__rid)) {
        try {
            if (!isset($GLOBALS['conn']) && !isset($GLOBALS['pdo'])) { require_once dirname(__DIR__) . '/db_connect.php'; }
            $__db = $GLOBALS['conn'] ?? ($GLOBALS['pdo'] ?? null);
            $__dup = false;
            $__ddl = "CREATE TABLE IF NOT EXISTS offline_request_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_request_id VARCHAR(64) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_offline_req (client_request_id)
            ) ENGINE=InnoDB";
            if ($__db instanceof mysqli) {
                $__db->query($__ddl);
                $st = $__db->prepare('INSERT IGNORE INTO offline_request_log (client_request_id) VALUES (?)');
                $st->bind_param('s', $__rid);
                $st->execute();
                $__dup = ($st->affected_rows === 0);
            } elseif ($__db instanceof PDO) {
                $__db->exec($__ddl);
                $st = $__db->prepare('INSERT IGNORE INTO offline_request_log (client_request_id) VALUES (?)');
                $st->execute([$__rid]);
                $__dup = ($st->rowCount() === 0);
            }
            if ($__dup) {
                http_response_code(200);
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'duplicate' => true]);
                exit;
            }
            // If the real handler fails (HTTP >= 400), free the id so the client can retry
            register_shutdown_function(function () use ($__db, $__rid) {
                if (http_response_code() >= 400 && $__db) {
                    try {
                        if ($__db instanceof mysqli) { $d = $__db->prepare('DELETE FROM offline_request_log WHERE client_request_id=?'); $d->bind_param('s', $__rid); $d->execute(); }
                        elseif ($__db instanceof PDO) { $d = $__db->prepare('DELETE FROM offline_request_log WHERE client_request_id=?'); $d->execute([$__rid]); }
                    } catch (Throwable $e) {}
                }
            });
        } catch (Throwable $e) { /* never break the app because of the guard */ }
    }
}
