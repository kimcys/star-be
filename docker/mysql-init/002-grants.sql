-- The official MySQL image grants ALL PRIVILEGES to MYSQL_USER by
-- default. Narrow that back down to match the least-privilege grant
-- used in local (non-Docker) setup - the app only ever reads/writes
-- rows, it never needs to alter schema.
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'star_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON star_assessment.* TO 'star_app'@'%';
FLUSH PRIVILEGES;
