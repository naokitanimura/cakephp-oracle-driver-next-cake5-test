-- container-registry.oracle.com/database/free:*-lite is a prebuilt-database
-- image: it has no "first boot only" hook, only /opt/oracle/scripts/startup,
-- which runs on every container start. This script must therefore be
-- idempotent (guard the CREATE USER instead of relying on run-once semantics).
--
-- Credentials here must match config/app_local.php / .env DB_USERNAME/DB_PASSWORD.
--
-- Startup scripts connect to the CDB root by default; without switching to
-- the PDB first, CREATE USER fails with ORA-65096 (common user/role name
-- must start with C##).
ALTER SESSION SET CONTAINER = FREEPDB1;

DECLARE
    user_count NUMBER;
BEGIN
    SELECT COUNT(*) INTO user_count FROM dba_users WHERE username = 'TESTUSER';
    IF user_count = 0 THEN
        EXECUTE IMMEDIATE 'CREATE USER testuser IDENTIFIED BY "TestUserPass123"';
    END IF;
END;
/

-- GRANTs are safe to repeat every startup (re-granting an already-held
-- privilege is a no-op, not an error). This image ships without a "USERS"
-- tablespace, so quota is handled via the UNLIMITED TABLESPACE system
-- privilege rather than "QUOTA UNLIMITED ON USERS".
GRANT CONNECT, RESOURCE TO testuser;
GRANT CREATE SESSION, CREATE TABLE, CREATE SEQUENCE, CREATE TRIGGER, CREATE VIEW TO testuser;
GRANT UNLIMITED TABLESPACE TO testuser;

EXIT;
