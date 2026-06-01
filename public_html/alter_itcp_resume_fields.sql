ALTER TABLE itcp 
  ADD COLUMN phone_number VARCHAR(255) NULL AFTER address,
  ADD COLUMN degree VARCHAR(255) NULL AFTER program,
  ADD COLUMN graduation_year INT(4) NULL AFTER year_graduated,
  ADD COLUMN current_company VARCHAR(255) NULL AFTER company,
  ADD COLUMN current_job_title VARCHAR(255) NULL AFTER position,
  ADD COLUMN professional_summary TEXT NULL AFTER photo,
  ADD COLUMN skills TEXT NULL AFTER professional_summary,
  ADD COLUMN linkedin_url VARCHAR(255) NULL AFTER skills,
  ADD COLUMN education_history LONGTEXT NULL AFTER linkedin_url,
  ADD COLUMN work_experience LONGTEXT NULL AFTER education_history;
