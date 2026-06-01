-- System user creation using exact structure from existing data
-- This matches the exact column count and structure

INSERT IGNORE INTO itcp (
    id, photo, lastname, firstname, middlename, name_ext, 
    birthday, age, gender, civil_status, religion, nationality,
    email, address, personal_contact, emergency_contact, 
    student_number, program, campus, month_graduated, year_graduated, 
    post_grad, licensure_exam, club_involvement, employment_status, 
    company, industry, position, employment_history, previous_role, 
    length_of_service, consent, password
) VALUES (
    1, 'default.jpg', 'System', 'Admin', '', '', 
    '1990-01-01', 34, 'Male', 'Single', 'System', 'System', 
    'admin@system.com', 'System Address', '000-000-0000', '000-000-0000', 
    'SYS-001', 'System', 'System Campus', '01', '2020', 
    '', '', '', 'Employed', 
    'System Company', 'System', 'System Admin', '', '', 
    '', 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
);
