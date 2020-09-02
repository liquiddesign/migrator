-- phpMyAdmin SQL Dump
-- version 4.8.5
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 25, 2020 at 09:18 AM
-- Server version: 5.7.26
-- PHP Version: 7.4.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `_test_migrator`
--

-- --------------------------------------------------------

--
-- Table structure for table `stocks_alert`
--

DROP TABLE IF EXISTS `stocks_alert`;
CREATE TABLE IF NOT EXISTS `stocks_alert` (
  `uuid` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stocks_industry`
--

DROP TABLE IF EXISTS `stocks_industry`;
CREATE TABLE IF NOT EXISTS `stocks_industry` (
  `uuid` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `fk_type` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`uuid`),
  KEY `stocks_industry_type` (`fk_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stocks_sector`
--

DROP TABLE IF EXISTS `stocks_sector`;
CREATE TABLE IF NOT EXISTS `stocks_sector` (
  `uuid` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `performance` double NOT NULL,
  `general` tinyint(1) NOT NULL,
  `no_stocks` int(11) NOT NULL,
  `name_cz` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `name_en` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stocks_stock`
--

DROP TABLE IF EXISTS `stocks_stock`;
CREATE TABLE IF NOT EXISTS `stocks_stock` (
  `uuid` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `currency` varchar(255) COLLATE utf8_czech_ci NOT NULL COMMENT 'Stock currency CZK, TEST',
  `is_enabled` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `fk_sector` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `fk_industry` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`uuid`),
  KEY `stocks_stock_sector` (`fk_sector`),
  KEY `stocks_stock_industry` (`fk_industry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci COMMENT='Table of stocks';

-- --------------------------------------------------------

--
-- Table structure for table `stocks_stock_nxn_stocks_tag`
--

DROP TABLE IF EXISTS `stocks_stock_nxn_stocks_tag`;
CREATE TABLE IF NOT EXISTS `stocks_stock_nxn_stocks_tag` (
  `fk_stock` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `fk_tag` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`fk_stock`,`fk_tag`),
  KEY `stocks_stock_nxn_stocks_tag_fk_stock` (`fk_stock`),
  KEY `stocks_stock_nxn_stocks_tag_fk_tag` (`fk_tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stocks_tag`
--

DROP TABLE IF EXISTS `stocks_tag`;
CREATE TABLE IF NOT EXISTS `stocks_tag` (
  `uuid` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stocks_type`
--

DROP TABLE IF EXISTS `stocks_type`;
CREATE TABLE IF NOT EXISTS `stocks_type` (
  `id` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_czech_ci NOT NULL,
  `fk_sector` varchar(32) COLLATE utf8_czech_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

--
-- Indexes for dumped tables
--

ALTER TABLE `stocks_sector` ADD INDEX `no_stocks` (`no_stocks`);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `stocks_industry`
--
ALTER TABLE `stocks_industry`
  ADD CONSTRAINT `stocks_industry_type` FOREIGN KEY (`fk_type`) REFERENCES `stocks_type` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

--
-- Constraints for table `stocks_stock`
--
ALTER TABLE `stocks_stock`
  ADD CONSTRAINT `stocks_stock_industry` FOREIGN KEY (`fk_industry`) REFERENCES `stocks_industry` (`uuid`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `stocks_stock_sector` FOREIGN KEY (`fk_sector`) REFERENCES `stocks_sector` (`uuid`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `stocks_stock_nxn_stocks_tag`
--
ALTER TABLE `stocks_stock_nxn_stocks_tag`
  ADD CONSTRAINT `stocks_stock_nxn_stocks_tag_source` FOREIGN KEY (`fk_stock`) REFERENCES `stocks_stock` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `stocks_stock_nxn_stocks_tag_target` FOREIGN KEY (`fk_tag`) REFERENCES `stocks_tag` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;