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
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `doc_id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `author_name` varchar(255) DEFAULT NULL,
  `description` text,
  `file_path` varchar(255) NOT NULL,
  `file_size` int DEFAULT NULL,
  `document_type` enum('pdf','doc','ppt','image','other','code') NOT NULL DEFAULT 'other',
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `subject_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `status_id` int DEFAULT '1',
  `views` int DEFAULT '0',
  `summary` text,
  PRIMARY KEY (`doc_id`),
  KEY `subject_id` (`subject_id`),
  KEY `user_id` (`user_id`),
  KEY `fk_documents_status` (`status_id`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE SET NULL,
  CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
INSERT INTO `documents` VALUES (9,'Mô hình thử nghiệm các Agents khác nhau','Lê Sơn Trường','','uploads/68a94b8c8f217.ipynb',122296,'code','2025-08-23 05:03:08','2025-10-27 14:22:17',84,1,2,74,''),(10,'PHP - Chủ đề bài tập lớn','Hòa','','uploads/68a9d6317b2c0.pdf',176066,'pdf','2025-08-23 14:54:41','2025-10-21 17:23:01',33,2,3,47,''),(11,'12312','3123123','sdasda','uploads/68ee8efa05dd3.pdf',1338268,'pdf','2025-10-14 17:57:14','2025-10-27 14:41:25',41,5,2,11,'DL đã tạo bước nhảy vọt trong xử lý máy tính, xử lý ngôn ngữ và nhận dạng tiếng nói. Mạng nhiều lớp (Multilayer Perceptron, MLP) và Hàm kích hoạt phi tuyến (sigmoid, tanh, ReLU, Leaky ReLU) giúp biểu diễn phi tuyến mạnh mẽ, nhưng dễ quá khớp nếu dữ liệu/regularization chưa đủ. Ứng dụng: tích chập (convolution), tạo tín hiệu mới (feature map), và chia sẻ tham số theo thời gian.\nTransformer sử dụng cơ chế tự chú ý (self-attention) để ghi nhớ dài hạn hơn, giảm tham số và huấn luyện nhanh hơn. Các mô hình tiêu biểu gồm:BERT, chia ảnh thành patch, và V. Multi-head tách nhiều “đầu” để học quan hệ đa chiều. Generative Adversarial Networks (GANs) 2014 gồm hai mạng đối đầu: Generator (tạo dữ liệu giả) và Distor (phân biệt thật/giả).\nNền tảng: Python, đại số tuyến tính, xác suất-thống kê, giải tích cơ bản, và công cụ. 2, 3, và Deep Learning bao gồm các kiến trúc: Perceptron, MLP, CNN, RNN/LSTM/GRU, và Autoencoder. Các công cụ: PyTorch/TensorFlow, Conda/Docker, và các mô hình sinh như GAN, Autoencoders.'),(12,'13123','123123','1231','uploads/68ee8f36e7728.pdf',396518,'pdf','2025-10-14 17:58:14','2025-10-27 18:23:54',80,4,1,11,NULL),(13,'Tên tài liệu dài dài nào đấy hehehehe liên quân mô bi','á','','uploads/68f7e928b2d3a.pdf',203683,'pdf','2025-10-21 20:12:24','2025-10-27 14:58:33',17,5,2,11,'Sản phẩm bàn giao gồm mã nguồn chương trình, báo cáo kỹ thuật, mô hình phân loại, và so sánh kết quả. Tiêu chí đánh giá: Độ chính xác và phân tích kết quả (30%), tính sáng tạo trong giao diện (20%), và giao diện hiển thị trực quan (20%). Yêu cầu: Chuẩn bị tập dữ liệu gồm người dùng, sản phẩm, lịch sử tương tác. Sản phẩm bao gồm: Mã nguồn, bộ dữ liệu mẫu, và mô hình.\nSản phẩm bàn giao: Mã nguồn chương trình, bộ dữ liệu người dùng, sản phẩm, báo cáo kỹ thuật, mô hình, kết quả, và hệ thống gợi ý. Hệ thống hỏi đáp dựa trên tài liệu upload bằng LLM-RAG. Yêu cầu: Chuẩn bị tri thức, mô tả triệu chứng, bệnh, lỗi, nguyên nhân, và giao diện nhập dữ liệu tình huống. Báo cáo đầy đủ, logic, có phân tích kết quả (20%).'),(14,'GG','GG','to ma 2','uploads/68ff841dcb1d1.pdf',1228078,'pdf','2025-10-27 14:39:25','2025-10-27 18:44:29',24,6,2,6,'Cơ chế xác thực 1.1 http authentication là cơ chế bảo vệ tài nguyên trực tuyến khỏi truy cập trái phép bằng cách yêu cầu người dùng cung cấp thông tin đăng nhập hoặc mã thông báo xác thực. Sử dụng mã hóa đơn giản, mã hóa hàm 1 chiều, và mã hóa chuỗi ngẫu nhiên, nhưng có một số vấn đề như lưu username và password vào CSDL không an toàn. Giải pháp là sử dụnghash function với tham số PASSWORDDEFAULT.\nLưu ý: độ dài chuỗi mật khẩu sinh ra tối thiểu 255, do vậy cần thiết lập CSDL lưu trữ đủ lớn (tối thiểu 255). Sử dụng mãhash để tạo giá trị băm từ mật khẩu $password, tạo biến phiên lưu trữ thông tin người dùng, và sử dụng session để xác minh quyền truy cập. Ajax cho phép gửi yêu cầu HTTP và nhận dữ liệu từ máy chủ, sau đó cập nhật nội dung trang web mà không cần làm mới.\nHàm xử lý sự kiện, $.ajax, gửi yêu cầu Ajax đến máy chủ, bao gồm: url, id, và.asp. Học jquery: https://www.w3schools.com/jquery/default.ap 29 Ví dụ, khi nhấn add to elements, hệ thống thêm class cho text 30 Index.php 31 Project: Đăng nhập, phân quyền người dùng, quản trị nhân viên, và mã hóa mật khẩu.'),(16,'tệ','tệ','a','uploads/68ffbed450a80.pdf',1035,'pdf','2025-10-27 18:49:56','2025-10-27 19:02:20',5,4,3,0,NULL),(17,'g','g','g','uploads/68ffbf081b6f8.pdf',203683,'pdf','2025-10-27 18:50:48','2025-10-27 18:51:20',17,4,1,0,'Sản phẩm bàn giao gồm mã nguồn chương trình, báo cáo kỹ thuật, mô hình phân loại, và so sánh kết quả. Tiêu chí đánh giá: Độ chính xác và phân tích kết quả (30%), tính sáng tạo trong giao diện (20%), và giao diện hiển thị trực quan (20%). Yêu cầu: Chuẩn bị tập dữ liệu gồm người dùng, sản phẩm, lịch sử tương tác. Sản phẩm bao gồm: Mã nguồn, bộ dữ liệu mẫu, và mô hình.\nSản phẩm bàn giao: Mã nguồn chương trình, bộ dữ liệu người dùng, sản phẩm, báo cáo kỹ thuật, mô hình, kết quả, và hệ thống gợi ý. Hệ thống hỏi đáp dựa trên tài liệu upload bằng LLM-RAG. Yêu cầu: Chuẩn bị tri thức, mô tả triệu chứng, bệnh, lỗi, nguyên nhân, và giao diện nhập dữ liệu tình huống. Báo cáo đầy đủ, logic, có phân tích kết quả (20%).'),(18,'g','g','gg','uploads/68ffbf17970d7.pdf',239835,'pdf','2025-10-27 18:51:03','2025-10-27 18:51:36',10,5,2,0,'Chủ đề 1: Lập trình ứng dụng với java Buổi 1 - 6: Làm bài thực hành từ 1 đến 9 trong file BaiThucHanhJSP.pdf trong Files/Class Materials. Yêu cầu các chức năng chạy được Nộp mã nguồn .zip Trình bày: Buổi 12: Bài tập lớn (final) Các nhóm nộp toàn bộ sản phảm theo yêu cầu của BTL. Hạn nộp: Trình bày bài tập lớn: Báo cáo BTL');
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
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
