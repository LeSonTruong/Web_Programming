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
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,2,'✅ Tài liệu \'PHP - Chủ đề bài tập lớn\' của bạn đã được duyệt!',1,'2025-08-21 12:17:45'),(2,1,'❌ Tài liệu \'PHP - Chủ đề bài tập lớn\' của bạn đã bị từ chối!',0,'2025-08-21 12:21:17'),(3,2,'❌ Tài liệu \'PHP - Chủ đề bài tập lớn\' của bạn đã bị từ chối bởi admin!',1,'2025-08-21 12:41:57'),(4,2,'✅ Tài liệu \'PHP - Chủ đề bài tập lớn\' của bạn đã được duyệt bởi admin!',1,'2025-08-21 12:42:05'),(5,1,'✅ Tài liệu \'PHP - Chủ đề bài tập lớn\' của bạn đã được duyệt bởi admin!',0,'2025-08-21 12:42:06'),(6,1,'✅ Tài liệu \'Mô hình thử nghiệm các Agents khác nhau\' của bạn đã được duyệt!',0,'2025-08-23 05:07:11'),(7,2,'✅ Tài liệu \'PHP - Chủ đề bài tập lớn\' của bạn đã được duyệt!',1,'2025-08-23 14:55:26'),(8,4,'✅ Tài liệu \'13123\' của bạn đã được duyệt!',1,'2025-10-19 05:02:14'),(9,5,'✅ Tài liệu \'12312\' của bạn đã được duyệt!',1,'2025-10-19 05:03:25'),(10,2,'❌ Tài liệu \'PHP - Chủ đề bài tập lớn\' của bạn đã bị từ chối bởi admin!',0,'2025-10-21 17:22:59'),(11,5,'✅ Tài liệu \'á\' của bạn đã được duyệt!',1,'2025-10-21 20:13:31'),(12,4,'✅ Tài liệu \'13123\' của bạn đã được duyệt!',1,'2025-10-27 14:44:18'),(13,6,'✅ Tài liệu \'GG\' của bạn đã được duyệt!',1,'2025-10-27 14:45:17'),(14,5,'✅ Tài liệu \'Tên tài liệu dài dài nào đấy hehehehe liên quân mô bi\' của bạn đã được duyệt bởi admin!',1,'2025-10-27 14:58:33'),(15,6,'✅ Sửa đổi cho tài liệu đã được chấp nhận.',1,'2025-10-27 15:40:52'),(16,6,'❌ Tài liệu \'GG\' của bạn đã bị từ chối!',1,'2025-10-27 16:05:07'),(17,6,'✅ Tài liệu \'GG\' của bạn đã được duyệt!',1,'2025-10-27 16:55:43'),(18,4,'❌ Tài liệu \'tệ\' của bạn đã bị từ chối tự động bởi AI!',1,'2025-10-27 19:02:20');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
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
