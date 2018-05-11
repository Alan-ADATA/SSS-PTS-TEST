-- phpMyAdmin SQL Dump
-- version 4.5.4.1deb2ubuntu2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: May 09, 2018 at 10:00 AM
-- Server version: 10.2.14-MariaDB-10.2.14+maria~xenial
-- PHP Version: 7.1.16-1+ubuntu16.04.1+deb.sury.org+1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pts`
--

-- --------------------------------------------------------

--
-- Table structure for table `HIR_report`
--

CREATE TABLE `HIR_report` (
  `info_no` int(10) UNSIGNED NOT NULL,
  `hir_steady_state_iops` int(10) UNSIGNED DEFAULT NULL,
  `hir_wait_5s_iops` int(10) UNSIGNED DEFAULT NULL,
  `hir_wait_10s_iops` int(10) UNSIGNED DEFAULT NULL,
  `hir_wait_15s_iops` int(10) UNSIGNED DEFAULT NULL,
  `hir_wait_25s_iops` int(10) UNSIGNED DEFAULT NULL,
  `hir_wait_50s_iops` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `info`
--

CREATE TABLE `info` (
  `no` int(10) UNSIGNED NOT NULL,
  `stime` datetime NOT NULL,
  `etime` datetime DEFAULT NULL,
  `type` enum('client','enterprise') COLLATE utf8_unicode_ci NOT NULL,
  `item` set('IOPS','TP','LAT','WSAT','HIR','XSR','CBW','DIRTH') COLLATE utf8_unicode_ci NOT NULL,
  `uID` mediumint(8) UNSIGNED NOT NULL,
  `ssd` text COLLATE utf8_unicode_ci NOT NULL,
  `report` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `command` text COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `IOPS_report`
--

CREATE TABLE `IOPS_report` (
  `info_no` int(10) UNSIGNED NOT NULL,
  `iops_1m_100_0` float UNSIGNED DEFAULT NULL,
  `iops_128k_100_0` float UNSIGNED DEFAULT NULL,
  `iops_64k_100_0` float UNSIGNED DEFAULT NULL,
  `iops_32k_100_0` float UNSIGNED DEFAULT NULL,
  `iops_16k_100_0` float UNSIGNED DEFAULT NULL,
  `iops_8k_100_0` float UNSIGNED DEFAULT NULL,
  `iops_4k_100_0` float UNSIGNED DEFAULT NULL,
  `iops_512b_100_0` float UNSIGNED DEFAULT NULL,
  `iops_1m_95_5` float UNSIGNED DEFAULT NULL,
  `iops_128k_95_5` float UNSIGNED DEFAULT NULL,
  `iops_64k_95_5` float UNSIGNED DEFAULT NULL,
  `iops_32k_95_5` float UNSIGNED DEFAULT NULL,
  `iops_16k_95_5` float UNSIGNED DEFAULT NULL,
  `iops_8k_95_5` float UNSIGNED DEFAULT NULL,
  `iops_4k_95_5` float UNSIGNED DEFAULT NULL,
  `iops_512b_95_5` float UNSIGNED DEFAULT NULL,
  `iops_1m_65_35` float UNSIGNED DEFAULT NULL,
  `iops_128k_65_35` float UNSIGNED DEFAULT NULL,
  `iops_64k_65_35` float UNSIGNED DEFAULT NULL,
  `iops_32k_65_35` float UNSIGNED DEFAULT NULL,
  `iops_16k_65_35` float UNSIGNED DEFAULT NULL,
  `iops_8k_65_35` float UNSIGNED DEFAULT NULL,
  `iops_4k_65_35` float UNSIGNED DEFAULT NULL,
  `iops_512b_65_35` float UNSIGNED DEFAULT NULL,
  `iops_1m_50_50` float UNSIGNED DEFAULT NULL,
  `iops_128k_50_50` float UNSIGNED DEFAULT NULL,
  `iops_64k_50_50` float UNSIGNED DEFAULT NULL,
  `iops_32k_50_50` float UNSIGNED DEFAULT NULL,
  `iops_16k_50_50` float UNSIGNED DEFAULT NULL,
  `iops_8k_50_50` float UNSIGNED DEFAULT NULL,
  `iops_4k_50_50` float UNSIGNED DEFAULT NULL,
  `iops_512b_50_50` float UNSIGNED DEFAULT NULL,
  `iops_1m_35_65` float UNSIGNED DEFAULT NULL,
  `iops_128k_35_65` float UNSIGNED DEFAULT NULL,
  `iops_64k_35_65` float UNSIGNED DEFAULT NULL,
  `iops_32k_35_65` float UNSIGNED DEFAULT NULL,
  `iops_16k_35_65` float UNSIGNED DEFAULT NULL,
  `iops_8k_35_65` float UNSIGNED DEFAULT NULL,
  `iops_4k_35_65` float UNSIGNED DEFAULT NULL,
  `iops_512b_35_65` float UNSIGNED DEFAULT NULL,
  `iops_1m_5_95` float UNSIGNED DEFAULT NULL,
  `iops_128k_5_95` float UNSIGNED DEFAULT NULL,
  `iops_64k_5_95` float UNSIGNED DEFAULT NULL,
  `iops_32k_5_95` float UNSIGNED DEFAULT NULL,
  `iops_16k_5_95` float UNSIGNED DEFAULT NULL,
  `iops_8k_5_95` float UNSIGNED DEFAULT NULL,
  `iops_4k_5_95` float UNSIGNED DEFAULT NULL,
  `iops_512b_5_95` float UNSIGNED DEFAULT NULL,
  `iops_1m_0_100` float UNSIGNED DEFAULT NULL,
  `iops_128k_0_100` float UNSIGNED DEFAULT NULL,
  `iops_64k_0_100` float UNSIGNED DEFAULT NULL,
  `iops_32k_0_100` float UNSIGNED DEFAULT NULL,
  `iops_16k_0_100` float UNSIGNED DEFAULT NULL,
  `iops_8k_0_100` float UNSIGNED DEFAULT NULL,
  `iops_4k_0_100` float UNSIGNED DEFAULT NULL,
  `iops_512b_0_100` float UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `LAT_report`
--

CREATE TABLE `LAT_report` (
  `info_no` int(10) UNSIGNED NOT NULL,
  `latency_8k_100_0_iops` float UNSIGNED DEFAULT NULL,
  `latency_8k_100_0_mean` float UNSIGNED DEFAULT NULL,
  `latency_8k_100_0_59s` float UNSIGNED DEFAULT NULL,
  `latency_8k_100_0_max` float UNSIGNED DEFAULT NULL,
  `latency_8k_65_35_iops` float UNSIGNED DEFAULT NULL,
  `latency_8k_65_35_mean` float UNSIGNED DEFAULT NULL,
  `latency_8k_65_35_59s` float UNSIGNED DEFAULT NULL,
  `latency_8k_65_35_max` float UNSIGNED DEFAULT NULL,
  `latency_8k_0_100_iops` float UNSIGNED DEFAULT NULL,
  `latency_8k_0_100_mean` float UNSIGNED DEFAULT NULL,
  `latency_8k_0_100_59s` float UNSIGNED DEFAULT NULL,
  `latency_8k_0_100_max` float UNSIGNED DEFAULT NULL,
  `latency_4k_100_0_iops` float UNSIGNED DEFAULT NULL,
  `latency_4k_100_0_mean` float UNSIGNED DEFAULT NULL,
  `latency_4k_100_0_59s` float UNSIGNED DEFAULT NULL,
  `latency_4k_100_0_max` float UNSIGNED DEFAULT NULL,
  `latency_4k_65_35_iops` float UNSIGNED DEFAULT NULL,
  `latency_4k_65_35_mean` float UNSIGNED DEFAULT NULL,
  `latency_4k_65_35_59s` float UNSIGNED DEFAULT NULL,
  `latency_4k_65_35_max` float UNSIGNED DEFAULT NULL,
  `latency_4k_0_100_iops` float UNSIGNED DEFAULT NULL,
  `latency_4k_0_100_mean` float UNSIGNED DEFAULT NULL,
  `latency_4k_0_100_59s` float UNSIGNED DEFAULT NULL,
  `latency_4k_0_100_max` float UNSIGNED DEFAULT NULL,
  `latency_512b_100_0_iops` float UNSIGNED DEFAULT NULL,
  `latency_512b_100_0_mean` float UNSIGNED DEFAULT NULL,
  `latency_512b_100_0_59s` float UNSIGNED DEFAULT NULL,
  `latency_512b_100_0_max` float UNSIGNED DEFAULT NULL,
  `latency_512b_65_35_iops` float UNSIGNED DEFAULT NULL,
  `latency_512b_65_35_mean` float UNSIGNED DEFAULT NULL,
  `latency_512b_65_35_59s` float UNSIGNED DEFAULT NULL,
  `latency_512b_65_35_max` float UNSIGNED DEFAULT NULL,
  `latency_512b_0_100_iops` float UNSIGNED DEFAULT NULL,
  `latency_512b_0_100_mean` float UNSIGNED DEFAULT NULL,
  `latency_512b_0_100_59s` float UNSIGNED DEFAULT NULL,
  `latency_512b_0_100_max` float UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `status`
--

CREATE TABLE `status` (
  `time` datetime NOT NULL,
  `data` text COLLATE utf8_unicode_ci DEFAULT NULL,
  `parameter` text COLLATE utf8_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `TP_report`
--

CREATE TABLE `TP_report` (
  `info_no` int(10) UNSIGNED NOT NULL,
  `throughput_1024k_100_0` float UNSIGNED DEFAULT NULL,
  `throughput_1024k_0_100` float UNSIGNED DEFAULT NULL,
  `throughput_128k_100_0` float UNSIGNED DEFAULT NULL,
  `throughput_128k_0_100` float UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `uID` mediumint(8) UNSIGNED NOT NULL,
  `uName` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `uEnable` tinyint(1) NOT NULL DEFAULT 1,
  `uPermissions` set('R','W','D','A') COLLATE utf8_unicode_ci NOT NULL,
  `uLogin` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `WSAT_report`
--

CREATE TABLE `WSAT_report` (
  `info_no` int(10) UNSIGNED NOT NULL,
  `wsat_iops` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `HIR_report`
--
ALTER TABLE `HIR_report`
  ADD PRIMARY KEY (`info_no`),
  ADD UNIQUE KEY `info_no` (`info_no`);

--
-- Indexes for table `info`
--
ALTER TABLE `info`
  ADD PRIMARY KEY (`no`),
  ADD UNIQUE KEY `no` (`no`);

--
-- Indexes for table `IOPS_report`
--
ALTER TABLE `IOPS_report`
  ADD PRIMARY KEY (`info_no`),
  ADD UNIQUE KEY `info_no` (`info_no`);

--
-- Indexes for table `LAT_report`
--
ALTER TABLE `LAT_report`
  ADD PRIMARY KEY (`info_no`),
  ADD UNIQUE KEY `info_no` (`info_no`);

--
-- Indexes for table `status`
--
ALTER TABLE `status`
  ADD UNIQUE KEY `time` (`time`);

--
-- Indexes for table `TP_report`
--
ALTER TABLE `TP_report`
  ADD PRIMARY KEY (`info_no`),
  ADD UNIQUE KEY `info_no` (`info_no`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`uID`),
  ADD UNIQUE KEY `uID` (`uID`);

--
-- Indexes for table `WSAT_report`
--
ALTER TABLE `WSAT_report`
  ADD PRIMARY KEY (`info_no`),
  ADD UNIQUE KEY `info_no` (`info_no`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
