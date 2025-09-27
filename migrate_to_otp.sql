-- Migration script to update password_reset_tokens table for OTP support
-- Run this ONLY if you already have the old password_reset_tokens table

-- Add new columns for OTP functionality
ALTER TABLE password_reset_tokens 
ADD COLUMN email VARCHAR(100) NOT NULL AFTER user_id,
ADD COLUMN otp_code CHAR(6) NOT NULL AFTER email,
ADD COLUMN attempts INT DEFAULT 0 AFTER used;

-- Create indexes for new columns
CREATE INDEX idx_password_reset_tokens_otp ON password_reset_tokens(otp_code);
CREATE INDEX idx_password_reset_tokens_email ON password_reset_tokens(email);

-- Note: If you get an error about the email column being added without a default value,
-- you can run this alternative approach:

-- Alternative method (if the above fails):
-- ALTER TABLE password_reset_tokens 
-- ADD COLUMN email VARCHAR(100) DEFAULT '' AFTER user_id;
-- 
-- ALTER TABLE password_reset_tokens 
-- ADD COLUMN otp_code CHAR(6) DEFAULT '000000' AFTER email;
-- 
-- ALTER TABLE password_reset_tokens 
-- ADD COLUMN attempts INT DEFAULT 0 AFTER used;
--
-- -- Then update existing records
-- UPDATE password_reset_tokens SET email = (
--     SELECT u.email FROM users u WHERE u.id = password_reset_tokens.user_id
-- );
--
-- -- Make email NOT NULL after updating
-- ALTER TABLE password_reset_tokens MODIFY COLUMN email VARCHAR(100) NOT NULL;
-- ALTER TABLE password_reset_tokens MODIFY COLUMN otp_code CHAR(6) NOT NULL;