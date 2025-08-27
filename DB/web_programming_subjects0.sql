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
-- Table structure for table `subjects`
--

DROP TABLE IF EXISTS `subjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subjects` (
  `subject_id` int NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`subject_id`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subjects`
--

LOCK TABLES `subjects` WRITE;
/*!40000 ALTER TABLE `subjects` DISABLE KEYS */;
INSERT INTO `subjects` VALUES (1,'An toàn mạng','Khoa Công nghệ Thông tin'),(2,'Các vấn đề hiện đại của công nghệ thông tin và truyền thông máy tính','Khoa Công nghệ Thông tin'),(3,'Cấu trúc dữ liệu và giải thuật 1','Khoa Công nghệ Thông tin'),(4,'Cấu trúc dữ liệu và giải thuật 2','Khoa Công nghệ Thông tin'),(5,'Chủ nghĩa xã hội khoa học','Khoa Khoa học Xã hội'),(6,'Chứng chỉ ngoại ngữ đại trà','Khoa Ngoại ngữ'),(7,'Chứng chỉ Tin học','Khoa Công nghệ Thông tin'),(8,'Chuyên đề 1: Lập trình ứng dụng với Java','Khoa Công nghệ Thông tin'),(9,'Chuyên đề 2: Lập trình Web nâng cao','Khoa Công nghệ Thông tin'),(10,'Chuyên đề 3: Lập trình nhúng','Khoa Công nghệ Thông tin'),(11,'Đồ họa ứng dụng','Khoa Công nghệ Thông tin'),(12,'Giải tích số','Khoa Toán học'),(13,'Giáo dục quốc phòng và An ninh 1','Khoa Giáo dục Quốc phòng'),(14,'Giáo dục quốc phòng và An ninh 2','Khoa Giáo dục Quốc phòng'),(15,'Giáo dục quốc phòng và An ninh 3','Khoa Giáo dục Quốc phòng'),(16,'Giáo dục quốc phòng và An ninh 4','Khoa Giáo dục Quốc phòng'),(17,'Giáo dục thể chất 1','Khoa Giáo dục Thể chất'),(18,'Giáo dục thể chất 2','Khoa Giáo dục Thể chất'),(19,'Giáo dục thể chất 3','Khoa Giáo dục Thể chất'),(20,'Hà Nội học','Khoa Khoa học Xã hội'),(21,'Hệ điều hành Linux','Khoa Công nghệ Thông tin'),(22,'Hệ quản trị cơ sở dữ liệu','Khoa Công nghệ Thông tin'),(23,'Khóa luận','Khoa Công nghệ Thông tin'),(24,'Kiến trúc máy tính','Khoa Công nghệ Thông tin'),(25,'Kinh tế chính trị Mác-Lênin','Khoa Khoa học Xã hội'),(26,'Kỹ năng hội nhập thế giới nghề nghiệp','Khoa Công nghệ Thông tin'),(27,'Kỹ nghệ phần mềm','Khoa Công nghệ Thông tin'),(28,'Kỹ thuật số','Khoa Công nghệ Thông tin'),(29,'Lập trình cơ bản','Khoa Công nghệ Thông tin'),(30,'Lập trình di động','Khoa Công nghệ Thông tin'),(31,'Lập trình hướng đối tượng','Khoa Công nghệ Thông tin'),(32,'Lập trình trên Windows','Khoa Công nghệ Thông tin'),(33,'Lập trình WEB','Khoa Công nghệ Thông tin'),(34,'Lịch sử Đảng Cộng sản Việt Nam','Khoa Khoa học Xã hội'),(35,'Ngôn ngữ lập trình Java','Khoa Công nghệ Thông tin'),(36,'Ngôn ngữ truy vấn có cấu trúc SQL','Khoa Công nghệ Thông tin'),(37,'Nguyên lý hệ điều hành','Khoa Công nghệ Thông tin'),(38,'Nhập môn hệ cơ sở dữ liệu','Khoa Công nghệ Thông tin'),(39,'Nhập môn mạng máy tính','Khoa Công nghệ Thông tin'),(40,'Phân tích và thiết kế các hệ thống thông tin','Khoa Công nghệ Thông tin'),(41,'Pháp luật đại cương','Khoa Luật'),(42,'Quản trị mạng','Khoa Công nghệ Thông tin'),(43,'Sinh viên đại học','Khoa Khoa học Xã hội'),(44,'Thiết kế WEB','Khoa Công nghệ Thông tin'),(45,'Thực hành hệ điều hành mạng','Khoa Công nghệ Thông tin'),(46,'Thực tập 1','Khoa Công nghệ Thông tin'),(47,'Thực tập 2','Khoa Công nghệ Thông tin'),(48,'Thực tập tốt nghiệp','Khoa Công nghệ Thông tin'),(49,'Tiếng Anh chuyên ngành','Khoa Ngoại ngữ'),(50,'Toán rời rạc','Khoa Toán học'),(51,'Toán xác suất thống kê','Khoa Toán học'),(52,'Triết học Mác-Lênin','Khoa Khoa học Xã hội'),(53,'Tư tưởng Hồ Chí Minh','Khoa Khoa học Xã hội'),(54,'Tiếng Anh 1','Khoa Ngoại ngữ'),(55,'Tiếng Nhật 1','Khoa Ngoại ngữ'),(56,'Tiếng Trung Quốc 1','Khoa Ngoại ngữ'),(57,'Tiếng Anh 2','Khoa Ngoại ngữ'),(58,'Tiếng Nhật 2','Khoa Ngoại ngữ'),(59,'Tiếng Trung Quốc 2','Khoa Ngoại ngữ'),(60,'Khoa học thông tin','Khoa Công nghệ Thông tin'),(61,'Kinh tế học ứng dụng','Khoa Kinh tế'),(62,'Môi trường và con người','Khoa Khoa học Xã hội'),(63,'Thực phẩm, nước và sức khỏe','Khoa Khoa học Xã hội'),(64,'Địa chính trị Việt Nam','Khoa Khoa học Xã hội'),(65,'Quản trị học','Khoa Kinh tế'),(66,'Tâm lý học','Khoa Khoa học Xã hội'),(67,'Tiếng Việt thực hành','Khoa Khoa học Xã hội'),(68,'Âm nhạc và cảm thụ âm nhạc','Khoa Nghệ thuật'),(69,'Các loại hình nghệ thuật truyền thống','Khoa Nghệ thuật'),(70,'Giao tiếp trong môi trường đa văn hóa','Khoa Khoa học Xã hội'),(71,'Mĩ thuật và cảm thụ mĩ thuật','Khoa Nghệ thuật'),(72,'Công nghệ bền vững','Khoa Công nghệ Thông tin'),(73,'Khoa học dữ liệu','Khoa Công nghệ Thông tin'),(74,'Khoa học và đời sống','Khoa Khoa học Xã hội'),(75,'Thuật toán và ứng dụng','Khoa Công nghệ Thông tin'),(76,'Khởi nghiệp và đổi mới sáng tạo','Khoa Kinh tế'),(77,'Lịch sử văn minh thế giới','Khoa Khoa học Xã hội'),(78,'Quyền con người','Khoa Khoa học Xã hội'),(79,'Tôn giáo và xã hội','Khoa Khoa học Xã hội'),(80,'Âm nhạc và vũ đạo','Khoa Nghệ thuật'),(81,'Cơ sở văn hóa Việt Nam','Khoa Khoa học Xã hội'),(82,'Công nghiệp giải trí','Khoa Nghệ thuật'),(83,'Mĩ thuật dân gian và đương đại','Khoa Nghệ thuật'),(84,'Nhập môn trí tuệ nhân tạo','Khoa Công nghệ Thông tin'),(85,'Phân tích và thiết kế mạng máy tính','Khoa Công nghệ Thông tin'),(86,'Khai phá dữ liệu','Khoa Công nghệ Thông tin'),(87,'Lập trình mạng','Khoa Công nghệ Thông tin'),(88,'Chuyên đề 4: Mạng máy tính nâng cao','Khoa Công nghệ Thông tin'),(89,'Đảm bảo chất lượng phần mềm','Khoa Công nghệ Thông tin'),(90,'Học sâu','Khoa Công nghệ Thông tin'),(91,'Học máy','Khoa Công nghệ Thông tin'),(92,'Internet vạn vật (IoT)','Khoa Công nghệ Thông tin'),(93,'Chủ đề cơ sở dữ liệu','Khoa Công nghệ Thông tin'),(94,'Chủ đề lập trình máy tính','Khoa Công nghệ Thông tin'),(95,'Điều kiện đầu vào Tiếng Anh','Khoa Ngoại ngữ'),(96,'Điều kiện đầu vào Tiếng Nhật','Khoa Ngoại ngữ'),(97,'Điều kiện đầu vào Tiếng Trung','Khoa Ngoại ngữ');
/*!40000 ALTER TABLE `subjects` ENABLE KEYS */;
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
