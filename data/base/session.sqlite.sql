PRAGMA encoding = utf8;
PRAGMA foreign_keys = OFF;

CREATE TABLE sessions (
	id TEXT PRIMARY KEY NOT NULL,
	skey TEXT DEFAULT '',			-- Composite key
	sdata TEXT DEFAULT NULL,		-- Encrypted session data
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_sessions_on_updated_at ON sessions ( updated_at DESC );

CREATE TRIGGER session_after_update AFTER UPDATE ON sessions FOR EACH ROW 
WHEN NEW.updated_at < OLD.updated_at
BEGIN
	UPDATE sessions SET updated_at = CURRENT_TIMESTAMP 
		WHERE rowid = NEW.rowid;
END;

-- Expire session after 1 hour of inactivity
CREATE TRIGGER session_gc AFTER UPDATE ON sessions FOR EACH ROW
WHEN ( ABS( RANDOM() ) % 49 + 1 ) % 5 = 0
BEGIN
	DELETE FROM sessions 
	WHERE strftime('%s', updated_at) < ( strftime('%s','now') - 3600 );
END;
