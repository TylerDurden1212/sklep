-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sklep
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `chats`
--

DROP TABLE IF EXISTS `chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produkt_id` int(11) DEFAULT NULL,
  `user_from` int(11) NOT NULL,
  `user_to` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_status` tinyint(1) DEFAULT 0,
  `is_system` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_from` (`user_from`),
  KEY `idx_user_to` (`user_to`,`read_status`),
  KEY `fk_chats_produkty` (`produkt_id`),
  CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`user_from`) REFERENCES `logi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chats_ibfk_2` FOREIGN KEY (`user_to`) REFERENCES `logi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chats_produkty` FOREIGN KEY (`produkt_id`) REFERENCES `produkty` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chats`
--

LOCK TABLES `chats` WRITE;
/*!40000 ALTER TABLE `chats` DISABLE KEYS */;
INSERT INTO `chats` VALUES (49,10,2,1,'elo elo','2025-10-29 19:49:23',1,0),(50,10,1,2,'no co tam','2025-10-29 19:49:34',1,0),(51,10,2,1,'a chce sie targowac','2025-10-29 19:49:38',1,0),(52,10,1,2,'dawaj','2025-10-29 19:49:41',1,0),(53,10,2,1,'{\"type\":\"price_proposal\",\"price\":345,\"nego_id\":5,\"from_role\":\"buyer\",\"from_username\":\"anarchiabot1\"}','2025-10-29 19:49:59',1,1),(54,10,1,2,'{\"type\":\"price_accepted\",\"price\":\"345.00\",\"nego_id\":5,\"accepter_username\":\"fraxnol.0\"}','2025-10-29 19:50:07',1,1),(55,10,2,1,'{\"type\":\"price_proposal\",\"price\":767,\"nego_id\":5,\"from_role\":\"buyer\",\"from_username\":\"anarchiabot1\"}','2025-10-29 19:50:14',1,1),(56,10,1,2,'{\"type\":\"price_rejected\",\"price\":\"767.00\",\"nego_id\":5,\"rejecter_username\":\"fraxnol.0\"}','2025-10-29 19:50:17',1,1),(57,10,2,1,'{\"type\":\"price_proposal\",\"price\":345,\"nego_id\":5,\"from_role\":\"buyer\",\"from_username\":\"anarchiabot1\"}','2025-10-29 19:50:26',1,1),(58,10,1,2,'{\"type\":\"price_proposal\",\"price\":4523,\"nego_id\":5,\"from_role\":\"seller\",\"from_username\":\"fraxnol.0\"}','2025-10-29 19:50:31',1,1),(59,10,2,1,'{\"type\":\"price_rejected\",\"price\":\"4523.00\",\"nego_id\":5,\"rejecter_username\":\"anarchiabot1\"}','2025-10-29 19:50:34',1,1),(60,11,1,2,'siema siema','2025-10-29 19:55:00',1,0);
/*!40000 ALTER TABLE `chats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logi`
--

DROP TABLE IF EXISTS `logi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bio` text DEFAULT NULL,
  `ig_link` varchar(150) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logi`
--

LOCK TABLES `logi` WRITE;
/*!40000 ALTER TABLE `logi` DISABLE KEYS */;
INSERT INTO `logi` VALUES (1,'fraxnol.0','$2y$10$twLQuurdB5FEFBvxBfLuNeFHH6QJ1mVp.JeR29ehVLSji92lBmBmq','labuz.franciszek291@gmail.com','2025-10-26 13:46:06','jestem gejem no i lubie w dupe','https://kamilkminek.pl','uploads/profiles/profile_1_690264cdce6e2.png','2025-10-29 19:54:57'),(2,'anarchiabot1','$2y$10$GbV3uF59a0WS.DNIn0l83.5lp9d0lH.w.CKdX1tM01lq.hP8GmHwS','lukankee@gmail.com','2025-10-26 13:51:20','Jestem KamilKminek','https://kamilkminek.pl','uploads/profiles/profile_2_68ffe816808f4.png','2025-10-29 20:19:32'),(3,'Podpiwek','$2y$10$WXBnSs54CI018k03D6Gfa.6qn.TMPkbwAlc.jMYuUj05Hhi3WeGWK','franekszkola291@gmail.com','2025-10-26 19:52:39',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `logi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `price_negotiations`
--

DROP TABLE IF EXISTS `price_negotiations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `price_negotiations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `produkt_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `original_price` decimal(10,2) NOT NULL,
  `current_price` decimal(10,2) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `last_proposer` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `produkt_id` (`produkt_id`),
  KEY `buyer_id` (`buyer_id`),
  KEY `seller_id` (`seller_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `price_negotiations`
--

LOCK TABLES `price_negotiations` WRITE;
/*!40000 ALTER TABLE `price_negotiations` DISABLE KEYS */;
INSERT INTO `price_negotiations` VALUES (5,10,2,1,234.00,4523.00,'rejected',1,'2025-10-29 19:49:59','2025-10-29 19:50:34');
/*!40000 ALTER TABLE `price_negotiations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produkty`
--

DROP TABLE IF EXISTS `produkty`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `produkty` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sprzedawcy` int(11) DEFAULT NULL,
  `nazwa` varchar(100) NOT NULL,
  `opis` varchar(300) NOT NULL,
  `cena` decimal(10,2) NOT NULL,
  `zdjecie` varchar(255) DEFAULT NULL,
  `data_dodania` timestamp NOT NULL DEFAULT current_timestamp(),
  `kategoria` varchar(50) NOT NULL DEFAULT 'inne',
  PRIMARY KEY (`id`),
  KEY `fk_produkty_sprzedawca` (`id_sprzedawcy`),
  KEY `idx_kategoria` (`kategoria`),
  CONSTRAINT `fk_produkty_sprzedawca` FOREIGN KEY (`id_sprzedawcy`) REFERENCES `logi` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produkty`
--

LOCK TABLES `produkty` WRITE;
/*!40000 ALTER TABLE `produkty` DISABLE KEYS */;
INSERT INTO `produkty` VALUES (10,1,'we','qweqwe',234.00,'[\"uploads\\/product_69025a20a3027_1761761824.png\",\"uploads\\/product_69025a20a3252_1761761824.png\",\"uploads\\/product_69025a20a32eb_1761761824.png\",\"uploads\\/product_69025a20a3368_1761761824.png\",\"uploads\\/product_69025a20a33e1_1761761824.png\"]','2025-10-29 18:17:04','inne'),(11,2,'45435t','wefs',345.00,'[\"uploads\\/product_690271020f3e4_1761767682.png\"]','2025-10-29 19:54:42','inne');
/*!40000 ALTER TABLE `produkty` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-29 21:58:13
