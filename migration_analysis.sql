-- Migration Analysis Report
-- Source (DEV): c:\AppServ\www\registroexpositoresonexpo.bycri.com\sql\crivirtual_registro_onexpo2026DESARROLLO.sql
-- Target (PROD): c:\AppServ\www\registroexpositoresonexpo.bycri.com\sql\crivirtual_registro_onexpo2026PRODUCCION.sql
-- Date: 2026-02-19

-- Summary:
-- No missing tables found in Production.
-- No missing columns found in Production.
-- The database structures are functionally identical.

-- Minor differences observed (likely due to export settings/versions):
-- 1. Character Set/Collation: DEV specifies `utf8mb4_general_ci` or `utf8mb4_unicode_ci` explicitly, PROD uses implicit defaults.
-- 2. Default Values: DEV uses `DEFAULT '0'`, PROD uses `DEFAULT 0`.
-- 3. Timestamp: DEV uses `CURRENT_TIMESTAMP`, PROD uses `current_timestamp()`.

-- No action is required to synchronize the schema structure.
