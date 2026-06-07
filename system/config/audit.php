<?php

declare(strict_types=1);

/**
 * Audit-log connection routing.
 *
 * To enforce DB-level immutability on audit_log:
 *
 *   1. In production MySQL, create a dedicated user, e.g.
 *
 *        CREATE USER 'shipflow_audit_admin'@'app-host' IDENTIFIED BY '...';
 *        GRANT INSERT, SELECT, DELETE ON ship_system.audit_log
 *              TO 'shipflow_audit_admin'@'app-host';
 *
 *      And restrict the main app user:
 *
 *        REVOKE DELETE, UPDATE ON ship_system.audit_log
 *               FROM 'shipflow_app'@'app-host';
 *        GRANT INSERT, SELECT ON ship_system.audit_log
 *              TO 'shipflow_app'@'app-host';
 *
 *   2. Set in production .env:
 *
 *        AUDIT_ARCHIVE_CONNECTION=audit_admin
 *        AUDIT_ADMIN_USER=shipflow_audit_admin
 *        AUDIT_ADMIN_PASSWORD=...
 *
 * In dev / CI / test the connection name stays 'mysql' (same pool as
 * the app) so tests run with their normal DatabaseTransactions.
 *
 * See docs/GAPS.md gap #10 and docs/MANUAL.md §25.9.
 */

return [
    'archive_connection' => env('AUDIT_ARCHIVE_CONNECTION', 'mysql'),
];
