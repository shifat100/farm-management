CREATE DATABASE IF NOT EXISTS goat_farm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE goat_farm;

-- User Roles Table (RBAC Support)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','worker') DEFAULT 'worker',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default system credentials: admin / password
INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Shed Locations
CREATE TABLE sheds (
  id INT AUTO_INCREMENT PRIMARY KEY, 
  name VARCHAR(100) NOT NULL, 
  location VARCHAR(100)
);

INSERT INTO sheds (name, location) VALUES 
('Shed A', 'North Wing'),
('Shed B', 'South Wing'),
('Shed C', 'East Wing');

-- Core Animal Registry Table
CREATE TABLE animals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  auto_id VARCHAR(20) UNIQUE NOT NULL,
  name VARCHAR(100),
  type ENUM('Goat','Buck','Castrated') NOT NULL,
  breed VARCHAR(100),
  dob DATE,
  color VARCHAR(50),
  weight DECIMAL(5,2) DEFAULT 0.00,
  health_status ENUM('Healthy','Sick','Critical') DEFAULT 'Healthy',
  vaccination_status ENUM('Complete','Pending','Overdue') DEFAULT 'Pending',
  pregnancy_status ENUM('Not Pregnant','Pregnant','Unknown') DEFAULT 'Not Pregnant',
  sale_readiness ENUM('Not Ready','Ready') DEFAULT 'Not Ready',
  status ENUM('Active','Sold','Dead') DEFAULT 'Active',
  shed_id INT, 
  mother_id INT, 
  father_id INT,
  purchase_type ENUM('Born','Purchased') DEFAULT 'Born',
  purchase_price DECIMAL(10,2) DEFAULT 0.00, 
  selling_price DECIMAL(10,2) DEFAULT NULL,
  image VARCHAR(255) DEFAULT 'default.png', 
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (shed_id) REFERENCES sheds(id) ON DELETE SET NULL,
  FOREIGN KEY (mother_id) REFERENCES animals(id) ON DELETE SET NULL,
  FOREIGN KEY (father_id) REFERENCES animals(id) ON DELETE SET NULL
);

-- Relational Financial Costs Table
CREATE TABLE animal_costs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  category ENUM('Feed','Medicine','Vaccine','Labor','Other') NOT NULL,
  amount DECIMAL(10,2) NOT NULL, 
  cost_date DATE NOT NULL, 
  note VARCHAR(255),
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
);

-- Activity Operations and Medical Event Logs
CREATE TABLE activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT, 
  activity_type ENUM('Vaccination','Deworming','Feeding','Treatment','Breeding','Pregnancy Check','Weight Entry','Expense','Income','Sale','Death','Maintenance') NOT NULL,
  activity_date DATE NOT NULL, 
  description TEXT, 
  amount DECIMAL(10,2) DEFAULT 0.00, 
  user_id INT,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Daily Milk Yield Log Table
CREATE TABLE milk_production (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL,
  quantity_liters DECIMAL(5,2) NOT NULL,
  record_date DATE NOT NULL,
  notes VARCHAR(255),
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
);

-- Feed Consumption Log Table
CREATE TABLE feed_consumption (
  id INT AUTO_INCREMENT PRIMARY KEY,
  shed_id INT,
  feed_type VARCHAR(100) NOT NULL,
  quantity_kg DECIMAL(6,2) NOT NULL,
  record_date DATE NOT NULL,
  notes VARCHAR(255),
  FOREIGN KEY (shed_id) REFERENCES sheds(id) ON DELETE SET NULL
);

-- Employee Tasks Table
CREATE TABLE employee_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assigned_to_id INT NOT NULL,
  task_title VARCHAR(150) NOT NULL,
  task_description TEXT,
  due_date DATE NOT NULL,
  status ENUM('Pending','Completed') DEFAULT 'Pending',
  completed_at TIMESTAMP NULL,
  FOREIGN KEY (assigned_to_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Bookkeeping Financial Transaction Log
CREATE TABLE transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT, 
  type ENUM('Income','Expense') NOT NULL,
  category VARCHAR(100) NOT NULL, 
  amount DECIMAL(10,2) NOT NULL, 
  trans_date DATE NOT NULL, 
  description TEXT,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE SET NULL
);

-- Vaccines Directory
CREATE TABLE vaccines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL, 
  recommended_age_months INT NOT NULL,
  repeat_months INT DEFAULT 0
);

INSERT INTO vaccines (name, recommended_age_months, repeat_months) VALUES 
('CDT Vaccine', 3, 12),
('PPR Vaccine', 6, 12),
('Foot Rot Vaccine', 12, 6);

-- Vaccine Stock Inventory
CREATE TABLE vaccine_stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vaccine_id INT NOT NULL,
  batch_number VARCHAR(50),
  stock_quantity INT NOT NULL DEFAULT 0,
  expiry_date DATE NOT NULL,
  FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE CASCADE
);

-- Scheduled Immunization Records
CREATE TABLE vaccination_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL, 
  vaccine_id INT NOT NULL, 
  due_date DATE NOT NULL, 
  given_date DATE,
  status ENUM('Pending','Completed','Overdue') DEFAULT 'Pending',
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE,
  FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE CASCADE
);

-- Notification Alerts Table
CREATE TABLE alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  animal_id INT NOT NULL, 
  message TEXT NOT NULL, 
  type VARCHAR(50) NOT NULL,
  is_read TINYINT(1) DEFAULT 0, 
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
);

-- Database indexes for query optimization
CREATE INDEX idx_animals_status ON animals(status);
CREATE INDEX idx_animals_type ON animals(type);
CREATE INDEX idx_costs_animal ON animal_costs(animal_id);

-- ওজন রেকর্ডের জন্য নতুন টেবিল
CREATE TABLE IF NOT EXISTS weight_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    weight DECIMAL(5,2) NOT NULL,
    record_date DATE NOT NULL,
    notes VARCHAR(255),
    FOREIGN KEY (animal_id) REFERENCES animals(id) ON DELETE CASCADE
);

-- animals টেবিলে নতুন কলাম
ALTER TABLE animals ADD COLUMN kidding_date DATE DEFAULT NULL;
ALTER TABLE animals ADD COLUMN last_heat_date DATE DEFAULT NULL;
ALTER TABLE animals ADD COLUMN next_heat_date DATE DEFAULT NULL;

-- activities টেবিলে Withdrawal End Date যোগ করা
ALTER TABLE activities ADD COLUMN withdrawal_end_date DATE DEFAULT NULL;

-- inventory পেইজে ইতিমধ্যে vaccine_stock আছে, ওখানেই এক্সপায়ারি চেক করা হবে

-- ALTER TABLE animals ADD COLUMN color VARCHAR(100) DEFAULT NULL AFTER breed;
ALTER TABLE animals ADD COLUMN temp_celsius DECIMAL(4,2) DEFAULT NULL AFTER weight;
ALTER TABLE animals ADD COLUMN pulse_rate INT DEFAULT NULL AFTER temp_celsius;
ALTER TABLE animals ADD COLUMN resp_rate INT DEFAULT NULL AFTER pulse_rate;