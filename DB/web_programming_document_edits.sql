-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: localhost    Database: web_programming
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `document_edits`
--

DROP TABLE IF EXISTS `document_edits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_edits` (
  `edit_id` int NOT NULL AUTO_INCREMENT,
  `doc_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `document_type` enum('pdf','doc','ppt','image','other','code') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  PRIMARY KEY (`edit_id`),
  KEY `idx_doc_id` (`doc_id`),
  KEY `idx_status` (`status`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `document_edits_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`doc_id`) ON DELETE CASCADE,
  CONSTRAINT `document_edits_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_edits`
--

LOCK TABLES `document_edits` WRITE;
/*!40000 ALTER TABLE `document_edits` DISABLE KEYS */;
INSERT INTO `document_edits` VALUES (2,13,'Tên tài liệu dài dài nào đấy hehehehe liên quân aaa','LHQ','DFG','uploads/68f7e928b2d3a.pdf',203683,'pdf',13,'2025-10-27 16:05:04','2025-10-26 18:11:26','rejected'),(4,14,'GG','GG','to ma 2','uploads/68ff841dcb1d1.pdf',1228078,'pdf',94,'2025-10-27 15:40:52','2025-10-27 14:54:35','approved'),(5,14,'GG','GG','to ma 23','uploads/68ff841dcb1d1.pdf',1228078,'pdf',94,'2025-10-27 16:56:21','2025-10-27 16:55:56','rejected');
/*!40000 ALTER TABLE `document_edits` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-28  2:15:16
