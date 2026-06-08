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
-- Data removed - use only table schema
--

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
