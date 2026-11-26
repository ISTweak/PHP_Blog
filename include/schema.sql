-- ブログ用スキーマ（初回アクセス時に自動で作成されます）

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    username      TEXT    NOT NULL COLLATE NOCASE,
    display_name  TEXT    NOT NULL DEFAULT '',
    email         TEXT    NOT NULL DEFAULT '',
    role          TEXT    NOT NULL CHECK (role IN ('admin', 'editor', 'author')),
    auth_type     TEXT    NOT NULL CHECK (auth_type IN ('local', 'ldap')),
    password_hash TEXT,
    is_active     INTEGER NOT NULL DEFAULT 1,
    last_login_at TEXT,
    created_at    TEXT    NOT NULL,
    UNIQUE (auth_type, username)
);

CREATE TABLE IF NOT EXISTS categories (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    slug        TEXT    NOT NULL UNIQUE,
    description TEXT    NOT NULL DEFAULT '',
    sort_order  INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS media (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    path          TEXT    NOT NULL,
    thumb_path    TEXT,
    medium_path   TEXT,
    original_name TEXT    NOT NULL,
    mime          TEXT    NOT NULL,
    kind          TEXT    NOT NULL CHECK (kind IN ('image', 'video', 'file')),
    size          INTEGER NOT NULL DEFAULT 0,
    width         INTEGER,
    height        INTEGER,
    uploaded_by   INTEGER REFERENCES users (id) ON DELETE SET NULL,
    created_at    TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_media_kind ON media (kind, id);

CREATE TABLE IF NOT EXISTS posts (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    title             TEXT    NOT NULL,
    content           TEXT    NOT NULL DEFAULT '',
    category_id       INTEGER REFERENCES categories (id) ON DELETE SET NULL,
    author_id         INTEGER REFERENCES users (id) ON DELETE SET NULL,
    eyecatch_media_id INTEGER REFERENCES media (id) ON DELETE SET NULL,
    is_sticky         INTEGER NOT NULL DEFAULT 0,
    allow_comments    INTEGER NOT NULL DEFAULT 1,
    published_at      TEXT    NOT NULL,
    created_at        TEXT    NOT NULL,
    updated_at        TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_posts_published ON posts (published_at, id);
CREATE INDEX IF NOT EXISTS idx_posts_sticky    ON posts (is_sticky, published_at, id);
CREATE INDEX IF NOT EXISTS idx_posts_updated   ON posts (updated_at, id);
CREATE INDEX IF NOT EXISTS idx_posts_cat_pub    ON posts (category_id, published_at, id);
CREATE INDEX IF NOT EXISTS idx_posts_author_pub ON posts (author_id, published_at, id);
-- 旧バージョンのインデックス（上の複合インデックスで置き換え）
DROP INDEX IF EXISTS idx_posts_category;
DROP INDEX IF EXISTS idx_posts_author;

CREATE TABLE IF NOT EXISTS tags (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    created_at TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS post_tags (
    post_id INTEGER NOT NULL REFERENCES posts (id) ON DELETE CASCADE,
    tag_id  INTEGER NOT NULL REFERENCES tags (id) ON DELETE CASCADE,
    PRIMARY KEY (post_id, tag_id)
);
CREATE INDEX IF NOT EXISTS idx_post_tags_tag ON post_tags (tag_id);

CREATE TABLE IF NOT EXISTS comments (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id     INTEGER NOT NULL REFERENCES posts (id) ON DELETE CASCADE,
    user_id     INTEGER REFERENCES users (id) ON DELETE SET NULL,
    author_name TEXT    NOT NULL,
    body        TEXT    NOT NULL,
    status      TEXT    NOT NULL CHECK (status IN ('approved', 'pending')),
    ip          TEXT    NOT NULL DEFAULT '',
    created_at  TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_comments_post ON comments (post_id, status);

CREATE TABLE IF NOT EXISTS settings (
    name  TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    username     TEXT    NOT NULL,
    ip           TEXT    NOT NULL,
    attempted_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_login_attempts ON login_attempts (attempted_at);
