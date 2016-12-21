PRAGMA foreign_keys = ON;

-- Board tables
CREATE TABLE categories (
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
	title TEXT DEFAULT NULL,
	slug TEXT NOT NULL,
	sort_order INTEGER DEFAULT 0,
	status INTEGER DEFAULT 0
);

CREATE UNIQUE INDEX idx_categories_on_slug ON categories ( slug ASC );
CREATE INDEX idx_categories_on_sort ON categories ( sort_order ASC );


CREATE TABLE boards (
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	title TEXT DEFAULT NULL,
	slug TEXT NOT NULL,
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
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	username TEXT NOT NULL,
	password TEXT NOT NULL,
	bio TEXT DEFAULT NULL,
	email TEXT DEFAULT NULL,
	avatar TEXT DEFAULT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	status INTEGER DEFAULT 0
);

CREATE UNIQUE INDEX idx_users_on_username ON users ( username ASC );
CREATE INDEX idx_users_on_created_at ON users ( created_at ASC );
CREATE INDEX idx_users_on_status ON users ( status );

CREATE TABLE groups (
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	name TEXT NOT NULL,
	description TEXT DEFAULT NULL,
	status INTEGER DEFAULT 0
);

CREATE TABLE user_groups (
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	group_id INTEGER NOT NULL REFERENCES groups( id ) ON DELETE CASCADE,
	user_Id INTEGER NOT NULL REFERENCES users( id ) ON DELETE CASCADE,
	status INTEGER DEFAULT 0
);

CREATE UNIQUE INDEX idx_user_groups ON user_groups ( group_id, user_id );

CREATE TABLE logins (
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	user_id INTEGER NOT NULL REFERENCES users( id ) ON DELETE CASCADE,
	lookup TEXT DEFAULT NULL,
	hash TEXT NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_logins_on_created_at ON logins ( created_at ASC );
CREATE INDEX idx_logins_on_updated_at ON logins ( updated_at DESC );
CREATE UNIQUE INDEX idx_logins_on_lookup ON logins ( lookup ASC );
CREATE UNIQUE INDEX idx_logins_on_user_id ON logins ( user_id );


-- Post tables
CREATE TABLE posts (
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	title TEXT DEFAULT NULL,
	slug TEXT DEFAULT NULL,
	parent_id INTEGER DEFAULT NULL REFERENCES posts( id ) ON DELETE CASCADE,
	board_id INTEGER NOT NULL REFERENCES boards( id ) ON DELETE RESTRICT,
	moved_id INTEGER DEFAULT 0,		-- New post id this post was moved to 
	user_id INTEGER DEFAULT NULL REFERENCES users( id ),
	author TEXT DEFAULT NULL,
	trip TEXT DEFAULT NULL,			-- Tripcode
	edit_code TEXT DEFAULT NULL,		-- Random code for editing/deleting for anonymous users
	signature TEXT DEFAULT NULL,		-- Browser signature
	avatar TEXT DEFAULT NULL,
	ip TEXT DEFAULT '',
	geo_lat DECIMAL(10,5) DEFAULT 0,
	geo_lon DECIMAL(10,5) DEFAULT 0,
	reply_count INTEGER DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
CREATE VIRTUAL TABLE post_search USING fts4 ( search_data );


CREATE TABLE post_rank (
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	post_id BIGINT DEFAULT NULL REFERENCES posts( id ) ON DELETE CASCADE,
	upvotes INTEGER DEFAULT 0,
	downvotes INTEGER DEFAULT 0,
	flags INTEGER DEFAULT 0,
	treatment TEXT DEFAULT '',	-- Certain behaviors (JSON)
	sort_order INTEGER DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);



-- Board permissions
CREATE TABLE board_perms (
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	group_id INTEGER DEFAULT NULL REFERENCES groups( id ) ON DELETE CASCADE,
	user_id INTEGER DEFAULT NULL REFERENCES users( id ) ON DELETE CASCADE,
	board_id INTEGER NOT NULL REFERENCES boards( id ) ON DELETE CASCADE,
	flags TEXT DEFAULT ''		-- JSON encoded board->permissions
);

CREATE UNIQUE INDEX idx_board_perms ON board_perms 
	( group_id, user_id, board_id );



-- Metadata tables

CREATE TABLE meta_data (
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	label TEXT NOT NULL,
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
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	board_id INTEGER NOT NULL REFERENCES boards( id ) ON DELETE CASCADE,
	meta_id INTEGER NOT NULL REFERENCES meta_data( id ) ON DELETE CASCADE,
	content TEXT NOT NULL
);

CREATE TABLE post_meta (
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	post_id INTEGER NOT NULL REFERENCES posts( id ) ON DELETE CASCADE,
	meta_id INTEGER NOT NULL REFERENCES meta_data( id ) ON DELETE CASCADE,
	content TEXT NOT NULL
);

CREATE TABLE user_meta (
	id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
	user_id INTEGER NOT NULL REFERENCES users( id ) ON DELETE CASCADE,
	meta_id INTEGER NOT NULL REFERENCES meta_data( id ) ON DELETE CASCADE,
	content TEXT NOT NULL
);



-- Post triggers
CREATE TRIGGER post_after_insert AFTER INSERT ON posts FOR EACH ROW 
BEGIN
	INSERT INTO post_search ( docid, search_data ) 
		VALUES ( NEW.rowid, NEW.plain );
	
	INSERT INTO post_rank ( post_id, upvotes, downvotes )
		VALUES ( NEW.rowid, 1, 0 );
	
	UPDATE posts SET edit_code = lower( hex( randomblob( 4 ) ) ) 
		WHERE rowid = NEW.rowid;
	
	UPDATE posts SET reply_count = ( reply_count + 1 ) 
		WHERE id = ( 
			SELECT id FROM posts WHERE id = NEW.parent_id 
		);
	
	UPDATE boards SET post_count = ( post_count + 1 ), 
		last_id = NEW.rowid 
		WHERE id = ( 
			SELECT board_id FROM posts 
				WHERE posts.id = NEW.parent_id 
		);
	
	UPDATE boards SET topic_count = ( topic_count + 1 ) 
		WHERE id = NEW.board_id;
END;

CREATE TRIGGER post_family AFTER INSERT ON posts FOR EACH ROW 
WHEN NEW.parent_id IS NULL
BEGIN
	UPDATE posts SET parent_id = NEW.rowid WHERE rowid = NEW.rowid;
END;


-- Change update times, author info, and search content
CREATE TRIGGER post_after_update AFTER UPDATE ON posts FOR EACH ROW 
WHEN NEW.updated_at < OLD.updated_at
BEGIN
	UPDATE posts SET updated_at = CURRENT_TIMESTAMP 
		WHERE rowid = NEW.rowid;
	
	INSERT INTO post_search ( docid, search_data ) 
		VALUES ( NEW.rowid, NEW.title || ' ' || NEW.plain );
END;


-- Remove search content and decrement board topic/post counts
CREATE TRIGGER post_before_delete BEFORE DELETE ON posts FOR EACH ROW 
BEGIN
	DELETE FROM post_search WHERE docid = OLD.rowid;
	
	UPDATE boards SET topic_count = ( topic_count - 1 ) 
		WHERE id = OLD.board_id;
	
	UPDATE boards SET post_count = ( post_count - 1 ), 
		last_id = ( SELECT posts.id FROM posts 
				WHERE posts.id = OLD.parent_id LIMIT 1 )
		WHERE id = ( 
			SELECT posts.board_id FROM posts 
				WHERE posts.id = OLD.parent_id 
		);
END;


-- User triggers
CREATE TRIGGER user_after_insert AFTER INSERT ON users FOR EACH ROW 
BEGIN
	INSERT INTO logins ( user_id, lookup ) 
		VALUES( NEW.rowid, lower( hex( randomblob( 16 ) ) ) );
END;

-- Change user authorization lookup tokens
CREATE TRIGGER user_after_update AFTER UPDATE ON users FOR EACH ROW 
WHEN NEW.updated_at < OLD.updated_at
BEGIN
	UPDATE users SET updated_at = CURRENT_TIMESTAMP  
		WHERE rowid = NEW.rowid;
	
	--UPDATE logins SET lookup = lower( hex( randomblob( 16 ) ) )
	--	WHERE rowid = NEW.rowid;
END;

-- Change lookup token after the user has a new hash
CREATE TRIGGER login_after_update AFTER UPDATE ON logins FOR EACH ROW 
WHEN NEW.updated_at < OLD.updated_at AND NEW.hash NOT NULL
BEGIN
	UPDATE logins SET updated_at = CURRENT_TIMESTAMP, 
		lookup = lower( hex( randomblob( 16 ) ) )
		WHERE rowid = NEW.rowid;
END;

PRAGMA journal_mode = WAL;

INSERT INTO categories ( id, title, slug ) VALUES ( 1, 'Entertainment', 'entertainment' );
INSERT INTO boards ( id, title, slug, category_id, description ) VALUES ( 1, 'Anime', 'anime', 1, 'Japanese animation' );

INSERT INTO posts (
	id, title, slug, board_id, signature, avatar, ip, plain, body 
) VALUES ( 1, 'A post about anime', 'a-post-about-anime', 1, 
	'fda08521718568cb2dda343edde42e5e170aed1ecc4dc0c8', 
	'fda08521718568cb2dda343edde42e5e170aed1ecc4dc0c8', 
	'192.168.1.1', 'I\'m a big fan of Initial D', 
	'<p>I\'m a big fan of Initial D</p>'
);
