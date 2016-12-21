-- https://www.postgresql.org/docs/9.6/static/reference.html
-- Board tables
CREATE TABLE categories (
	id SERIAL PRIMARY KEY, 
	title VARCHAR(100) DEFAULT NULL,
	slug VARCHAR(100) NOT NULL,
	sort_order INTEGER DEFAULT 0,
	status INTEGER DEFAULT 0
);

CREATE UNIQUE INDEX idx_categories_on_slug ON categories ( slug ASC );
CREATE INDEX idx_categories_on_sort ON categories ( sort_order ASC );


CREATE TABLE boards (
	id SERIAL PRIMARY KEY,
	title VARCHAR(100) DEFAULT NULL,
	slug VARCHAR(100) NOT NULL,
	parent_id INTEGER DEFAULT NULL REFERENCES boards( id ) ON DELETE RESTRICT,
	category_id INTEGER NOT NULL REFERENCES categories( id ) ON DELETE RESTRICT,
	topic_count INTEGER DEFAULT 0,
	post_count INTEGER DEFAULT 0,
	sort_order INTEGER DEFAULT 0,
	description TEXT DEFAULT '',
	last_id INTEGER DEFAULT 0,
	status INTEGER DEFAULT 0
);

CREATE UNIQUE INDEX idx_boards_on_slug ON boards ( slug ASC );
CREATE INDEX idx_boards_on_parent_id ON boards ( parent_id ASC );
CREATE INDEX idx_boards_on_sort ON boards ( sort_order ASC, category_id ASC );
CREATE INDEX idx_boards_on_last_id ON boards ( last_id ASC );


-- User tables
CREATE TABLE users (
	id BIGSERIAL PRIMARY KEY,
	username VARCHAR(20) NOT NULL,
	password TEXT NOT NULL,
	bio TEXT DEFAULT NULL,
	email VARCHAR(180) DEFAULT NULL,
	avatar VARCHAR(255) DEFAULT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	status INTEGER DEFAULT 0
);

CREATE UNIQUE INDEX idx_users_on_username ON users ( username ASC );
CREATE INDEX idx_users_on_created_at ON users ( created_at ASC );
CREATE INDEX idx_users_on_status ON users ( status );

CREATE TABLE groups (
	id SERIAL PRIMARY KEY,
	name VARCHAR(60) NOT NULL,
	description TEXT DEFAULT NULL,
	status INTEGER DEFAULT 0
);

CREATE TABLE user_groups (
	id SERIAL PRIMARY KEY,
	group_id INTEGER NOT NULL REFERENCES groups( id ) ON DELETE CASCADE,
	user_Id INTEGER NOT NULL REFERENCES users( id ) ON DELETE CASCADE,
	status INTEGER DEFAULT 0
);

CREATE UNIQUE INDEX idx_user_groups ON user_groups ( group_id, user_id );

CREATE TABLE logins (
	id BIGSERIAL PRIMARY KEY,
	user_id BIGINT NOT NULL REFERENCES users( id ),
	lookup VARCHAR(60) DEFAULT NULL,
	hash VARCHAR(120) NOT NULL,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_logins_on_created_at ON logins ( created_at ASC );
CREATE INDEX idx_logins_on_updated_at ON logins ( updated_at DESC );
CREATE UNIQUE INDEX idx_logins_on_lookup ON logins ( lookup ASC );
CREATE UNIQUE INDEX idx_logins_on_user_id ON logins ( user_id );


-- Post tables
CREATE TABLE posts (
	id BIGSERIAL PRIMARY KEY,
	title VARCHAR(100) DEFAULT NULL,
	slug VARCHAR(100) DEFAULT NULL,
	parent_id BIGINT DEFAULT NULL REFERENCES posts( id ) ON DELETE CASCADE,
	board_id INTEGER NOT NULL REFERENCES boards( id ) ON DELETE RESTRICT,
	moved_id INTEGER DEFAULT 0,		-- New post id this post was moved to 
	user_id INTEGER DEFAULT NULL REFERENCES users( id ),
	author VARCHAR(20) DEFAULT NULL,
	trip VARCHAR(80) DEFAULT NULL,		-- Tripcode
	edit_code VARCHAR(100) DEFAULT NULL,	-- Random code for editing/deleting for anonymous users
	signature VARCHAR(100) DEFAULT NULL,	-- Browser signature
	avatar VARCHAR(255) DEFAULT NULL,
	ip VARCHAR(60) DEFAULT '',
	geo_lat DECIMAL(10,5) DEFAULT 0,
	geo_lon DECIMAL(10,5) DEFAULT 0,
	reply_count INTEGER DEFAULT 0,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	refs_to TEXT DEFAULT '',
	refs_from TEXT DEFAULT '',
	plain TEXT DEFAULT '',
	body TEXT DEFAULT '',
	status INTEGER DEFAULT 0
);

CREATE INDEX idx_posts_on_ip ON posts ( ip ASC );
CREATE INDEX idx_posts_on_slug ON posts ( slug ASC );
CREATE INDEX idx_posts_on_parent_id ON posts ( parent_id ASC );
CREATE INDEX idx_posts_on_board_id ON posts ( board_id ASC );
CREATE INDEX idx_posts_on_user_id ON posts ( user_id ASC );
CREATE INDEX idx_posts_on_created_at ON posts ( created_at ASC );
CREATE INDEX idx_posts_on_updated_at ON posts ( updated_at DESC );
CREATE INDEX idx_posts_on_status ON posts ( status );

-- Full text searching
CREATE INDEX idx_post_search ON posts USING GIN ( to_tsvector( title, plain ) );


CREATE TABLE post_rank (
	id BIGSERIAL PRIMARY KEY,
	post_id BIGINT DEFAULT NULL REFERENCES posts( id ) ON DELETE CASCADE,
	upvotes INTEGER DEFAULT 0,
	downvotes INTEGER DEFAULT 0,
	flags INTEGER DEFAULT 0,
	treatment TEXT DEFAULT '',	-- Certain behaviors (JSON)
	sort_order INTEGER DEFAULT 0,
	created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);



-- Board permissions
CREATE TABLE board_perms (
	id SERIAL PRIMARY KEYL,
	group_id INTEGER DEFAULT NULL REFERENCES groups( id ) ON DELETE CASCADE,
	user_id INTEGER DEFAULT NULL REFERENCES users( id ) ON DELETE CASCADE,
	board_id INTEGER NOT NULL REFERENCES boards( id ) ON DELETE CASCADE,
	flags TEXT DEFAULT ''		-- JSON encoded board->permissions
);

CREATE UNIQUE INDEX idx_board_perms ON board_perms 
	( group_id, user_id, board_id );



-- Metadata tables

CREATE TABLE meta_data (
	id SERIAL PRIMARY KEY,
	label VARCHAR(60) NOT NULL,
	input_view TEXT NOT NULL,	-- HTML form
	edit_view TEXT NOT NULL,
	render_view TEXT NOT NULL,
	default_filters TEXT NOT NULL,
	custom_filters TEXT DEFAULT NULL,
	model TEXT NOT NULL,
	sort_order INTEGER DEFAULT 0
);

CREATE INDEX idx_meta_applies ON meta_data ( model );

CREATE TABLE board_meta (
	id SERIAL PRIMARY KEY,
	board_id INTEGER NOT NULL REFERENCES boards( id ) ON DELETE CASCADE,
	meta_id INTEGER NOT NULL REFERENCES meta_data( id ) ON DELETE CASCADE,
	content TEXT NOT NULL
);

CREATE TABLE post_meta (
	id BIGSERIAL PRIMARY KEY,
	post_id BIGINT NOT NULL REFERENCES posts( id ),
	meta_id INTEGER NOT NULL REFERENCES meta_data( id ),
	content TEXT NOT NULL
);

CREATE TABLE user_meta (
	id BIGSERIAL PRIMARY KEY,
	user_id BIGINT NOT NULL REFERENCES users( id ),
	meta_id INTEGER NOT NULL REFERENCES meta_data( id ),
	content TEXT NOT NULL
);



-- https://www.postgresql.org/docs/current/static/plpgsql-trigger.html
-- https://stackoverflow.com/questions/2362871/postgresql-current-timestamp-on-update#2363047
-- Last updated modifier
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

-- http://www.simononsoftware.com/random-string-in-postgresql/
-- Random string generator
CREATE OR REPLACE FUNCTION random_string( INTEGER )
RETURNS TEXT 
LANGUAGE SQL AS $$ 
	SELECT UPPER(
		SUBSTRING(
		( SELECT string_agg( md5( random()::TEXT ), '' )
			FROM generate_series( 1, CEIL( $1 / 32. )::INTEGER ) 
		), $1 ) 
	);
$$;



-- Post triggers
CREATE OR REPLACE FUNCTION post_insert() RETURN TRIGGER 
LANGUAGE plpgsql AS $$
BEGIN
	INSERT INTO post_rank ( post_id, upvotes, downvotes )
		VALUES ( NEW.id, 1, 0 );
	
	UPDATE boards SET post_count = ( post_count + 1 ), 
		last_id = NEW.id 
		WHERE id = ( 
			SELECT board_id FROM posts 
				WHERE posts.id = NEW.parent_id 
		);
	
	UPDATE posts SET reply_count = ( reply_count + 1 ) 
		WHERE id = ( 
			SELECT id FROM posts WHERE id = NEW.parent_id 
		);
	
	UPDATE boards SET topic_count = ( topic_count + 1 ) 
		WHERE id = NEW.board_id;
	RETURN NULL;
END
$$;

CREATE OR REPLACE FUNCTION post_self_parent() RETURN TRIGGER
LANGUAGE plpgsql AS $$
BEGIN 
	UPDATE posts SET parent_id = NEW.id WHERE id = NEW.id;
	RETURN NULL
END
$$;

CREATE TRIGGER post_after_insert AFTER INSERT ON posts FOR EACH ROW
	EXECUTE PROCEDURE post_insert();
	
CREATE TRIGGER post_family AFTER INSERT ON posts FOR EACH ROW 
WHEN NEW.parent_id = NULL
	EXECUTE PROCEDURE post_self_parent();


-- Change last update times
CREATE TRIGGER post_before_update BEFORE UPDATE ON posts FOR EACH ROW
	EXECUTE PROCEDURE update_modified();

CREATE TRIGGER rank_update BEFORE UPDATE ON post_rank FOR EACH ROW 
	EXECUTE PROCEDURE update_modified();



-- Decrement board topic/post counts
CREATE OR REPLACE FUNCTION post_delete() RETURN TRIGGER 
LANGUAGE plpgsql AS $$
BEGIN
	UPDATE boards SET topic_count = ( topic_count - 1 ) 
		WHERE id = OLD.board_id;
	
	UPDATE boards SET post_count = ( post_count - 1 ), 
		last_id = ( SELECT posts.id FROM posts 
				WHERE posts.id = OLD.parent_id LIMIT 1 )
		WHERE id = ( 
			SELECT posts.board_id FROM posts 
				WHERE posts.id = OLD.parent_id 
		);
	RETURN NULL;
END
$$;

CREATE TRIGGER post_before_delete BEFORE DELETE ON posts FOR EACH ROW 
	EXECUTE PROCEDURE post_delete();



-- User triggers
CREATE OR REPLACE FUNCTION user_insert() RETURN TRIGGER 
LANGUAGE plpgsql AS $$
BEGIN
	INSERT INTO logins ( user_id, lookup ) 
		VALUES( NEW.id, lower( random_string( 16 ) ) );
	RETURN NULL;
END
$$;

CREATE TRIGGER user_after_insert AFTER INSERT ON users FOR EACH ROW
	EXECUTE PROCEDURE user_insert();


-- Change last update times
CREATE TRIGGER users_before_update BEFORE UPDATE ON users FOR EACH ROW
	EXECUTE PROCEDURE update_modified();
