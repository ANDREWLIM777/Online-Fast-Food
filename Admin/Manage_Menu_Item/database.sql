CREATE DATABASE IF NOT EXISTS brizo;
USE brizo;

CREATE TABLE menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    promotion VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    COLUMN photo VARCHAR(255) DEFAULT NULL;
);
ALTER TABLE menu_items ADD category ENUM('burger','chicken','drink','snacks','meal') NOT NULL AFTER id;
ADD FULLTEXT INDEX ft_search (item_name, description);

