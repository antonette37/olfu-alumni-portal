-- Add back-of-ID image column to alumni_registration (for admin verification).
-- Run this once if you get: Unknown column 'id_image_back' in 'INSERT INTO'

ALTER TABLE alumni_registration
ADD COLUMN id_image_back VARCHAR(255) NULL AFTER id_image;
