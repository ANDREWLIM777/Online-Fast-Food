CREATE TABLE orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(255),
    total_price DECIMAL(10,2),
    order_status ENUM('Pending', 'Completed', 'Cancelled'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
