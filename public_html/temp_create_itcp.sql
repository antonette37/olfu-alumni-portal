CREATE DATABASE IF NOT EXISTS itcp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE itcp_db;

CREATE TABLE IF NOT EXISTS `itcp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `photo` varchar(255) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) NOT NULL,
  `name_ext` varchar(20) NOT NULL,
  `birthday` date NOT NULL,
  `age` int(11) NOT NULL,
  `gender` varchar(10) NOT NULL,
  `civil_status` varchar(20) NOT NULL,
  `religion` varchar(50) NOT NULL,
  `nationality` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `personal_contact` varchar(20) NOT NULL,
  `emergency_contact` varchar(20) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `program` varchar(100) NOT NULL,
  `campus` varchar(100) NOT NULL,
  `month_graduated` varchar(20) NOT NULL,
  `year_graduated` varchar(10) NOT NULL,
  `post_grad` text NOT NULL,
  `licensure_exam` text NOT NULL,
  `club_involvement` text NOT NULL,
  `employment_status` varchar(50) NOT NULL,
  `company` varchar(100) NOT NULL,
  `industry` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `employment_history` text NOT NULL,
  `previous_role` varchar(100) NOT NULL,
  `length_of_service` varchar(50) NOT NULL,
  `consent` tinyint(1) NOT NULL,
  `password` varchar(255) NOT NULL,
  `date_joined` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) NOT NULL DEFAULT 'Active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


