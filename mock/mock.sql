-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 17, 2026 at 04:08 PM
-- Server version: 10.11.13-MariaDB-0ubuntu0.24.04.1
-- PHP Version: 8.2.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mock`
--

-- --------------------------------------------------------

--
-- Table structure for table `answers`
--

CREATE TABLE `answers` (
  `question_answer_id` int(11) NOT NULL,
  `question_number` int(11) DEFAULT NULL,
  `question` text DEFAULT NULL,
  `option1` varchar(255) DEFAULT NULL,
  `option2` varchar(255) DEFAULT NULL,
  `option3` varchar(255) DEFAULT NULL,
  `option4` varchar(255) DEFAULT NULL,
  `answer` int(11) DEFAULT NULL,
  `user_answer` int(11) DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `exam` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dept`
--

CREATE TABLE `dept` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dept`
--

INSERT INTO `dept` (`id`, `name`, `code`) VALUES
(1, 'Community Health', 'CHEW'),
(2, 'Health Information Management', 'HIM'),
(3, 'Medical Laboratory Technician', 'MLT'),
(4, 'Pharmacy Technician', 'PT');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `cms_uuid` varchar(100) DEFAULT NULL,
  `code` varchar(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `dept` int(11) NOT NULL,
  `level` varchar(55) NOT NULL,
  `description` varchar(120) NOT NULL,
  `time_allowed` int(11) NOT NULL,
  `number_of_questions` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `open` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `ca_max_score` int(11) DEFAULT 30,
  `session` varchar(25) NOT NULL,
  `semester` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_session`
--

CREATE TABLE `exam_session` (
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `exam` int(11) NOT NULL,
  `attempt_number` int(11) NOT NULL DEFAULT 1,
  `attempt_type` enum('normal','resit') NOT NULL DEFAULT 'normal',
  `started_at` datetime DEFAULT NULL,
  `stop_at` datetime DEFAULT NULL,
  `submit_status` tinyint(4) DEFAULT 0,
  `total_score` int(11) DEFAULT 0,
  `percent_score` int(11) DEFAULT 0,
  `total_questions` int(11) DEFAULT 0,
  `attempted` int(11) DEFAULT 0,
  `ca_score` decimal(5,2) DEFAULT NULL,
  `is_synced` tinyint(1) DEFAULT 0,
  `synced_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_setting`
--

CREATE TABLE `exam_setting` (
  `id` int(11) NOT NULL,
  `system_name` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `exam_time_allowed` int(11) DEFAULT NULL,
  `addtime` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `exam_setting`
--

INSERT INTO `exam_setting` (`id`, `system_name`, `logo`, `exam_time_allowed`, `addtime`) VALUES
(1, 'NATIONAL MOCK EXAM', 'asset/logo.jgp', 80, 'UPDATE `exam_session` SET `stop_at`= DATE_ADD(stop_at, INTERVAL 25 MINUTE) WHERE submit_status = 0');

-- --------------------------------------------------------

--
-- Table structure for table `question`
--

CREATE TABLE `question` (
  `question_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `option1` varchar(255) DEFAULT NULL,
  `option2` varchar(255) DEFAULT NULL,
  `option3` varchar(255) DEFAULT NULL,
  `option4` varchar(255) DEFAULT NULL,
  `answer` int(11) DEFAULT NULL,
  `exam` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sync_logs`
--

CREATE TABLE `sync_logs` (
  `id` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL,
  `message` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(55) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `dept` int(11) NOT NULL,
  `level` varchar(55) NOT NULL,
  `role` varchar(11) NOT NULL DEFAULT 'student',
  `semester` varchar(20) NOT NULL DEFAULT 'First'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `fullname`, `dept`, `level`, `role`) VALUES
(1, 'admin', '1234567', 'ICT Office', 1, '100', 'admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `answers`
--
ALTER TABLE `answers`
  ADD PRIMARY KEY (`question_answer_id`);

--
-- Indexes for table `dept`
--
ALTER TABLE `dept`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cms_uuid` (`cms_uuid`);

--
-- Indexes for table `exam_session`
--
ALTER TABLE `exam_session`
  ADD PRIMARY KEY (`session_id`);

--
-- Indexes for table `exam_setting`
--
ALTER TABLE `exam_setting`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `question`
--
ALTER TABLE `question`
  ADD PRIMARY KEY (`question_id`);

--
-- Indexes for table `sync_logs`
--
ALTER TABLE `sync_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `answers`
--
ALTER TABLE `answers`
  MODIFY `question_answer_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dept`
--
ALTER TABLE `dept`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_session`
--
ALTER TABLE `exam_session`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_setting`
--
ALTER TABLE `exam_setting`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `question`
--
ALTER TABLE `question`
  MODIFY `question_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sync_logs`
--
ALTER TABLE `sync_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
