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
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `tag_id` int NOT NULL AUTO_INCREMENT,
  `tag_name` varchar(50) NOT NULL,
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY `tag_name` (`tag_name`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
INSERT INTO `tags` VALUES (38,'Agile'),(11,'AI'),(57,'Algorithm'),(31,'Android'),(22,'Angular'),(9,'Backend'),(15,'Big Data'),(36,'Blockchain'),(5,'C++'),(43,'CI/CD'),(17,'Cloud'),(7,'Cơ sở dữ liệu'),(45,'CSS'),(14,'Data Science'),(58,'Data Structure'),(13,'Deep Learning'),(55,'Design Pattern'),(16,'DevOps'),(26,'Django'),(18,'Docker'),(50,'Firebase'),(27,'Flask'),(8,'Frontend'),(42,'Git'),(29,'GraphQL'),(44,'HTML'),(6,'Hướng dẫn'),(32,'iOS'),(37,'IoT'),(4,'Java'),(46,'JavaScript'),(49,'Jenkins'),(34,'Kotlin'),(19,'Kubernetes'),(1,'Lập trình'),(25,'Laravel'),(12,'Machine Learning'),(30,'Mobile'),(51,'MongoDB'),(52,'MySQL'),(23,'NodeJS'),(54,'NoSQL'),(56,'OOP'),(10,'PHP'),(53,'PostgreSQL'),(3,'Python'),(20,'React'),(28,'REST API'),(39,'Scrum'),(35,'Security'),(48,'Selenium'),(24,'Spring'),(33,'Swift'),(40,'Testing'),(47,'TypeScript'),(41,'Unit Test'),(21,'Vue'),(2,'Web');
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-25  8:08:02
