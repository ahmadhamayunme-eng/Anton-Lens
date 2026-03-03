CREATE TABLE pages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  page_url_normalized VARCHAR(2048) NOT NULL,
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  UNIQUE KEY ux_project_page (project_id, page_url_normalized(255)),
  KEY idx_pages_project (project_id),
  CONSTRAINT fk_pages_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE threads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  page_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','resolved') NOT NULL DEFAULT 'active',
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  label ENUM('design','content','bug','seo','other') NOT NULL DEFAULT 'other',
  assignee_user_id BIGINT UNSIGNED NULL,
  anchor_json JSON NOT NULL,
  device_preset ENUM('desktop','tablet','mobile') NOT NULL DEFAULT 'desktop',
  created_by_user_id BIGINT UNSIGNED NULL,
  created_by_guest_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  resolved_at DATETIME NULL,
  KEY idx_threads_project (project_id),
  KEY idx_threads_page (page_id),
  KEY idx_threads_status (status),
  CONSTRAINT fk_threads_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_threads_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE,
  CONSTRAINT fk_threads_assignee FOREIGN KEY (assignee_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_threads_created_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_threads_created_guest FOREIGN KEY (created_by_guest_id) REFERENCES guest_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT UNSIGNED NOT NULL,
  author_user_id BIGINT UNSIGNED NULL,
  author_guest_id BIGINT UNSIGNED NULL,
  visibility ENUM('public','internal') NOT NULL DEFAULT 'public',
  body_text TEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  edited_at DATETIME NULL,
  KEY idx_messages_thread (thread_id),
  CONSTRAINT fk_messages_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_messages_user FOREIGN KEY (author_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_messages_guest FOREIGN KEY (author_guest_id) REFERENCES guest_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE screenshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_id BIGINT UNSIGNED NOT NULL,
  message_id BIGINT UNSIGNED NULL,
  file_path VARCHAR(1024) NOT NULL,
  mime VARCHAR(80) NOT NULL,
  width INT NULL,
  height INT NULL,
  created_at DATETIME NOT NULL,
  KEY idx_screenshots_thread (thread_id),
  CONSTRAINT fk_screenshots_thread FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_screenshots_message FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
