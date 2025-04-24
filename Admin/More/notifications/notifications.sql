CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_pinned BOOLEAN DEFAULT 0,
    created_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES admin(id)
);

ALTER TABLE notifications 
ADD COLUMN reposted_by INT NULL,
ADD COLUMN reposted_at DATETIME NULL,
ADD FOREIGN KEY (reposted_by) REFERENCES admin(id);