-- Add registration fields to itcp so all alumni registration details are stored
-- and visible on alumni profile (al_profile.php) and admin view (ad_viewprofile.php).
-- Run once. If a column already exists, MySQL will error on that line; remove that line and run again.
USE itcp_db;

ALTER TABLE itcp
  ADD COLUMN college VARCHAR(150) NULL AFTER program,
  ADD COLUMN months_to_get_job VARCHAR(100) NULL AFTER length_of_service,
  ADD COLUMN job_aligned VARCHAR(100) NULL AFTER months_to_get_job,
  ADD COLUMN college_prepared VARCHAR(255) NULL AFTER job_aligned,
  ADD COLUMN important_soft_skill VARCHAR(255) NULL AFTER college_prepared,
  ADD COLUMN proud_alumni VARCHAR(255) NULL AFTER important_soft_skill;
