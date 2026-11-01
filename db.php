<?php
/**
 * BEASTz BATTLE - Database Connection Compatibility Layer
 * Resolves db.php missing references and bridges to TiDB Cloud config
 */
if (file_exists(__DIR__ . "/config.php")) {
    require_once __DIR__ . "/config.php";
} elseif (file_exists(__DIR__ . "/common/config.php")) {
    require_once __DIR__ . "/common/config.php";
}
?>