-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 13, 2025 at 06:42 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `itcp_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `alumni`
--

CREATE TABLE `alumni` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `graduation_date` date DEFAULT NULL,
  `degree_program` varchar(100) DEFAULT NULL,
  `employment_status` varchar(50) DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `date_posted` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `type` enum('workshop','seminar','conference','networking','other') NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `venue` varchar(255) NOT NULL,
  `audience` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('draft','published','cancelled') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `max_participants` int(11) DEFAULT NULL,
  `registration_deadline` datetime DEFAULT NULL,
  `is_online` tinyint(1) DEFAULT 0,
  `meeting_link` varchar(255) DEFAULT NULL,
  `banner_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `itcp` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `itcp`
--

CREATE TABLE `itcp` (
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

--
-- Dumping data for table `itcp`
--

INSERT INTO `itcp` (`id`, `photo`, `lastname`, `firstname`, `middlename`, `name_ext`, `birthday`, `age`, `gender`, `civil_status`, `religion`, `nationality`, `email`, `address`, `personal_contact`, `emergency_contact`, `student_number`, `program`, `campus`, `month_graduated`, `year_graduated`, `post_grad`, `licensure_exam`, `club_involvement`, `employment_status`, `company`, `industry`, `position`, `employment_history`, `previous_role`, `length_of_service`, `consent`, `password`) VALUES
(11, 'mener.jpg', 'Buensuceso', 'Mener', 'Peralta', '', '1981-01-05', 44, 'Female', 'Single', 'Catholic', 'Filipino', 'menerbasiga@gmail.com', 'Marikina City', '456456', '789789', '1-2345679-8', 'BSIT', 'Antipolo Campus', '0', '2025', '', 'LPT', 'Garments Fashion and Design', 'Employed', 'DSWD', 'Day Care', 'Teacher', '', '', '', 1, '$2y$10$4E1Dn0Q7wnbPJpWhZezOeOIuLFNWlV1.z7PS1lA5oL1NChvx80fPi'),
(14, 'basiga.jpg', 'Basiga', 'Shaira Mae', 'Buensuceso', '', '2001-05-04', 23, '', 'Single', 'Catholic', 'Filipino', 'secretnishairamalupet@gmail.com', '25 Daisy Street Purok 7 Phase 3 Malanday Marikina City', '09916296883', '09513133916', '03210002203', 'BSIT', 'Antipolo Campus', '0', '2026', '', '', 'FCMS', 'Employed', 'International AIA Express', 'Logistics', 'Documentation Custodian', 'DOLE - NCR', 'Intern', '6 months', 1, '$2y$10$Ry0zAUQGPulFC3eeYQD//.RIdQYpX.xjCpO/p7AWtaVYp11WeICvi');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `company` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `salary_range` varchar(100) DEFAULT NULL,
  `job_type` varchar(50) DEFAULT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `posted_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `itcp` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contactmessages`
--

CREATE TABLE `contactmessages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_name` varchar(255) DEFAULT NULL,
  `sender_email` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('new','read','archived') DEFAULT 'new',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requester_name` varchar(255) DEFAULT NULL,
  `requester_email` varchar(255) DEFAULT NULL,
  `request_type` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','in_progress','resolved','closed') DEFAULT 'pending',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alumni`
--
ALTER TABLE `alumni`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alumni`
--
ALTER TABLE `alumni`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `itcp`
--
ALTER TABLE `itcp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contactmessages`
--
ALTER TABLE `contactmessages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
