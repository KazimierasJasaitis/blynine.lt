CREATE TABLE IF NOT EXISTS dictionary_entries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    word VARCHAR(120) NOT NULL,
    meaning TEXT NOT NULL,
    example TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    seed_key VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    reviewed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY dictionary_seed_key (seed_key),
    KEY dictionary_status_word (status, word),
    KEY dictionary_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dictionary_tags (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(80) NOT NULL,
    label VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY dictionary_tag_slug (slug),
    UNIQUE KEY dictionary_tag_label (label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dictionary_entry_tags (
    entry_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (entry_id, tag_id),
    KEY dictionary_tag_entry (tag_id, entry_id),
    CONSTRAINT dictionary_entry_tags_entry_fk FOREIGN KEY (entry_id) REFERENCES dictionary_entries (id) ON DELETE CASCADE,
    CONSTRAINT dictionary_entry_tags_tag_fk FOREIGN KEY (tag_id) REFERENCES dictionary_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dictionary_rate_limits (
    scope VARCHAR(40) NOT NULL,
    client_hash CHAR(64) NOT NULL,
    window_start BIGINT UNSIGNED NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (scope, client_hash, window_start),
    KEY dictionary_rate_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;
