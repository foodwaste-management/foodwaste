-- ------------------------------------------------------
-- Insert default users (admin, manager, regular user)
-- ------------------------------------------------------
INSERT INTO users (email, password, role, verified, created_at) VALUES
('admin@example.com', '$2y$10$hXRkgpF58Biw2BLi/bkA.uJU8y1HxFi34ul8b8nV.ZG/30HcqCwc6', 'admin', 1, NOW()),
('manager@example.com', '$2y$10$hXRkgpF58Biw2BLi/bkA.uJU8y1HxFi34ul8b8nV.ZG/30HcqCwc6', 'manager', 1, NOW()),
('user@example.com', '$2y$10$hXRkgpF58Biw2BLi/bkA.uJU8y1HxFi34ul8b8nV.ZG/30HcqCwc6', 'user', 1, NOW());