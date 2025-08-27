-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: web_programming
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
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `display_name` varchar(100) DEFAULT NULL,
  `last_display_change` datetime DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default.png',
  `phone` varchar(20) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `show_email` tinyint(1) DEFAULT '1',
  `show_phone` tinyint(1) DEFAULT '1',
  `show_birthday` tinyint(1) DEFAULT '1',
  `show_gender` tinyint(1) DEFAULT '1',
  `show_facebook` tinyint(1) DEFAULT '1',
  `otp_code` varchar(6) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `email_verification_code` varchar(6) DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT '0',
  `banned` tinyint(1) DEFAULT '0',
  `comment_locked` tinyint(1) DEFAULT '0',
  `upload_locked` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Lê Sơn Trường','LeSonTruong','LeSonTruong@icloud.com','$2y$12$6/Qod9SGmY03ikJ8B4zuYeANHC0/jSgdQg5jkvDoeHFqPOHCpBkqG','admin','2025-08-21 05:01:26','Lê Sơn Trường','2025-08-21 12:01:59','default.png','0967602973',NULL,NULL,NULL,1,1,1,1,1,NULL,NULL,'650623',0,0,0,0),(2,'Nguyễn Việt Hòa','NguyenVietHoa','nguyenviethoa2903@gmail.com','$2y$12$jp2/3z6kW9mPeQdcZuASy.JmKEAtzJ4W5nGGPxKaXcnPnR5La8Jnu','user','2025-08-21 05:02:33','NHƯ CON CẶC','2025-08-21 19:48:07','default.png',NULL,NULL,NULL,NULL,1,1,1,1,1,NULL,NULL,'944303',0,0,0,0),(3,'Nguyễn Huy Kiên','NguyenHuyKien','nguyenkien7901@gmail.com','$2y$12$x8wQv6C0NnEVBo0fDpuxFeZqZTIZU/14fS35fPgTCtQfjdhujtbZe','user','2025-08-25 05:23:04',NULL,NULL,'default.png',NULL,NULL,NULL,NULL,1,1,1,1,1,NULL,NULL,NULL,0,0,0,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-27 10:38:43
