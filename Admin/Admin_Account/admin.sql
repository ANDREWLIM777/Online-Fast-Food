CREATE TABLE `admin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `photo` VARCHAR(255) DEFAULT 'default.jpg',
  `name` VARCHAR(100) NOT NULL,
  `gender` ENUM('Male','Female') NOT NULL,
  `age` INT NOT NULL,
  `position` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `role` ENUM('admin','superadmin') NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO admin (name, gender, age, position, phone, role, email, password, photo)
VALUES (
  'Ng Jin Yang',
  'Male',
  30,
  'Super Administrator',
  '0123456789',
  'superadmin',
  'jinyang@brizo.com',
  '$2b$12$F8q00EQR6Bjz6El9TFFlWuiw6wyjvQWLS1Di/SiPY.mD28hh0Kw8i',
  'default.jpg'
);