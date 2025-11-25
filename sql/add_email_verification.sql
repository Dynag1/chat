-- Migration script to add email verification columns to existing database
-- Run this if you already have the users table created

ALTER TABLE users 
ADD COLUMN email_verified TINYINT(1) DEFAULT 0 AFTER status,
ADD COLUMN verification_token VARCHAR(64) DEFAULT NULL AFTER email_verified,
ADD COLUMN verification_expires TIMESTAMP NULL DEFAULT NULL AFTER verification_token;
