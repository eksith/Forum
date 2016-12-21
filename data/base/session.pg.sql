CREATE TABLE sessions (
	id TEXT PRIMARY KEY NOT NULL,
	skey TEXT DEFAULT '',			-- Composite key
	sdata TEXT DEFAULT NULL,		-- Encrypted session data
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_sessions_on_updated_at ON sessions ( updated_at DESC );

CREATE OR REPLACE FUNCTION update_modified() RETURNS TRIGGER 
LANGUAGE plpgsql AS $$
BEGIN
	IF ( NEW != OLD ) THEN 
		NEW.updated_at = CURRENT_TIMESTAMP;
		RETURN NEW;
	END IF;
	RETURN OLD;
END
$$;

-- Expire session after 1 hour of inactivity
CREATE OR REPLACE FUNCTION session_gc() RETURNS TRIGGER 
LANGUAGE plpgsql AS $$
BEGIN
	IF ( ( floor( random() * 49 ) + 1 ) % 5 = 0 ) THEN
		DELETE FROM sessions WHERE updated_at < ( now() - 3600 );
	END IF;
	RETURN NULL;
END
$$;

CREATE TRIGGER session_before_update BEFORE UPDATE ON sessions FOR EACH ROW
	EXECUTE PROCEDURE update_modified();
	
CREATE TRIGGER session_after_update AFTER UPDATE ON sessions FOR EACH ROW
	EXECUTE PROCEDURE session_gc();

