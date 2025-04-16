CREATE DATABASE IF NOT EXISTS brizo;
USE brizo;

CREATE TABLE menu_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category ENUM('burger', 'chicken', 'drink', 'snacks', 'meal') NOT NULL,
    item_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    promotion VARCHAR(100),
    photo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);