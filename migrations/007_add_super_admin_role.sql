-- Add the highest-authority role and promote the existing administrator.

ALTER TABLE users
    MODIFY role ENUM('super_admin', 'admin', 'secretary', 'treasurer', 'committee') NOT NULL;

UPDATE users
SET role = 'super_admin'
WHERE username = 'admin';