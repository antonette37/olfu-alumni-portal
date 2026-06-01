-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 07, 2025 at 11:40 AM
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
-- Table structure for table `itcp`
--

CREATE TABLE `itcp` (
  `id` int(11) NOT NULL,
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
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `date_joined` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `itcp`
--

INSERT INTO `itcp` (`id`, `photo`, `lastname`, `firstname`, `middlename`, `name_ext`, `birthday`, `age`, `gender`, `civil_status`, `religion`, `nationality`, `email`, `address`, `personal_contact`, `emergency_contact`, `student_number`, `program`, `campus`, `month_graduated`, `year_graduated`, `post_grad`, `licensure_exam`, `club_involvement`, `employment_status`, `company`, `industry`, `position`, `employment_history`, `previous_role`, `length_of_service`, `consent`, `password`) VALUES
(11, 'mener.jpg', 'Buensuceso', 'Mener', 'Peralta', '', '1981-01-05', 44, 'Female', 'Single', 'Catholic', 'Filipino', 'menerbasiga@gmail.com', 'Marikina City', '456456', '789789', '1-2345679-8', 'BSIT', 'Antipolo Campus', '0', '2025', '', 'LPT', 'Garments Fashion and Design', 'Employed', 'DSWD', 'Day Care', 'Teacher', '', '', '', 1, '$2y$10$4E1Dn0Q7wnbPJpWhZezOeOIuLFNWlV1.z7PS1lA5oL1NChvx80fPi'),
(14, 'basiga.jpg', 'Basiga', 'Shaira Mae', 'Buensuceso', '', '2001-05-04', 23, '', 'Single', 'Catholic', 'Filipino', 'secretnishairamalupet@gmail.com', '25 Daisy Street Purok 7 Phase 3 Malanday Marikina City', '09916296883', '09513133916', '03210002203', 'BSIT', 'Antipolo Campus', '0', '2026', '', '', 'FCMS', 'Employed', 'International AIA Express', 'Logistics', 'Documentation Custodian', 'DOLE - NCR', 'Intern', '6 months', 1, '$2y$10$Ry0zAUQGPulFC3eeYQD//.RIdQYpX.xjCpO/p7AWtaVYp11WeICvi');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `itcp`
--
ALTER TABLE `itcp`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `itcp`
--
ALTER TABLE `itcp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
