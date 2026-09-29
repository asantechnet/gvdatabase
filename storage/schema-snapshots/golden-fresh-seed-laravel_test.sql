-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: 172.25.32.1    Database: laravel_test
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `account_deactivation_requests`
--

DROP TABLE IF EXISTS `account_deactivation_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_deactivation_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `pending_user_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `requester_rank` smallint(5) unsigned NOT NULL,
  `requester_roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`requester_roles`)),
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reviewer_id` bigint(20) unsigned DEFAULT NULL,
  `reviewer_rank` smallint(5) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `decision_notes` text DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `audit_metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`audit_metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_deactivation_requests_pending_user_id_unique` (`pending_user_id`),
  KEY `account_deactivation_requests_employee_id_foreign` (`employee_id`),
  KEY `account_deactivation_requests_reviewer_id_foreign` (`reviewer_id`),
  KEY `account_deactivation_requests_status_requested_at_index` (`status`,`requested_at`),
  KEY `account_deactivation_requests_user_id_requested_at_index` (`user_id`,`requested_at`),
  CONSTRAINT `account_deactivation_requests_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `account_deactivation_requests_pending_user_id_foreign` FOREIGN KEY (`pending_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `account_deactivation_requests_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `account_deactivation_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_deactivation_requests`
--

LOCK TABLES `account_deactivation_requests` WRITE;
/*!40000 ALTER TABLE `account_deactivation_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_deactivation_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applicant_attachments`
--

DROP TABLE IF EXISTS `applicant_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applicant_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` bigint(20) unsigned NOT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `label` varchar(255) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `disk` varchar(255) NOT NULL DEFAULT 'local',
  `path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `applicant_attachments_uploaded_by_foreign` (`uploaded_by`),
  KEY `applicant_attachments_applicant_id_label_index` (`applicant_id`,`label`),
  CONSTRAINT `applicant_attachments_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applicant_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicant_attachments`
--

LOCK TABLES `applicant_attachments` WRITE;
/*!40000 ALTER TABLE `applicant_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `applicant_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applicant_stage_histories`
--

DROP TABLE IF EXISTS `applicant_stage_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applicant_stage_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` bigint(20) unsigned NOT NULL,
  `old_stage_id` bigint(20) unsigned DEFAULT NULL,
  `new_stage_id` bigint(20) unsigned NOT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `applicant_stage_histories_old_stage_id_foreign` (`old_stage_id`),
  KEY `applicant_stage_histories_new_stage_id_foreign` (`new_stage_id`),
  KEY `applicant_stage_histories_changed_by_foreign` (`changed_by`),
  KEY `applicant_stage_histories_applicant_id_changed_at_index` (`applicant_id`,`changed_at`),
  CONSTRAINT `applicant_stage_histories_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applicant_stage_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicant_stage_histories_new_stage_id_foreign` FOREIGN KEY (`new_stage_id`) REFERENCES `applicant_stages` (`id`),
  CONSTRAINT `applicant_stage_histories_old_stage_id_foreign` FOREIGN KEY (`old_stage_id`) REFERENCES `applicant_stages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicant_stage_histories`
--

LOCK TABLES `applicant_stage_histories` WRITE;
/*!40000 ALTER TABLE `applicant_stage_histories` DISABLE KEYS */;
INSERT INTO `applicant_stage_histories` VALUES (1,1,NULL,2,NULL,'2026-09-18 00:09:14','Case created from consultation.','2026-09-28 00:09:14','2026-09-28 00:09:14');
/*!40000 ALTER TABLE `applicant_stage_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applicant_stages`
--

DROP TABLE IF EXISTS `applicant_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applicant_stages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `university_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_terminal` tinyint(1) NOT NULL DEFAULT 0,
  `allowed_next_keys` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allowed_next_keys`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `applicant_stages_key_unique` (`key`),
  KEY `applicant_stages_university_id_foreign` (`university_id`),
  KEY `applicant_stage_context_index` (`country_id`,`university_id`,`is_active`),
  CONSTRAINT `applicant_stages_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicant_stages_university_id_foreign` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicant_stages`
--

LOCK TABLES `applicant_stages` WRITE;
/*!40000 ALTER TABLE `applicant_stages` DISABLE KEYS */;
INSERT INTO `applicant_stages` VALUES (1,'new',NULL,NULL,'New',10,1,0,'[\"consultation\",\"cancelled\",\"withdrawn\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(2,'consultation',NULL,NULL,'Consultation',20,1,0,'[\"documents_required\",\"cancelled\",\"withdrawn\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(3,'documents_required',NULL,NULL,'Documents Required',30,1,0,'[\"documents_complete\",\"cancelled\",\"withdrawn\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(4,'documents_complete',NULL,NULL,'Documents Complete',40,1,0,'[\"university_selection\",\"cancelled\",\"withdrawn\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(5,'university_selection',NULL,NULL,'University Selection',50,1,0,'[\"university_application\",\"cancelled\",\"withdrawn\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(6,'university_application',NULL,NULL,'University Application',60,1,0,'[\"admission_pending\",\"cancelled\",\"withdrawn\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(7,'admission_pending',NULL,NULL,'Admission Pending',70,1,0,'[\"admission_received\",\"cancelled\",\"withdrawn\",\"rejected\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(8,'admission_received',NULL,NULL,'Admission Received',80,1,0,'[\"contract_service_active\",\"cancelled\",\"withdrawn\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(9,'contract_service_active',NULL,NULL,'Contract/Service Active',90,1,0,'[\"visa_preparation\",\"cancelled\",\"withdrawn\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(10,'visa_preparation',NULL,NULL,'Visa Preparation',100,1,0,'[\"visa_submitted\",\"cancelled\",\"withdrawn\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(11,'visa_submitted',NULL,NULL,'Visa Submitted',110,1,0,'[\"visa_processing\",\"cancelled\",\"withdrawn\",\"rejected\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(12,'visa_processing',NULL,NULL,'Visa Processing',120,1,0,'[\"visa_approved\",\"cancelled\",\"withdrawn\",\"rejected\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(13,'visa_approved',NULL,NULL,'Visa Approved',130,1,0,'[\"completed\",\"cancelled\",\"withdrawn\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(14,'completed',NULL,NULL,'Completed',140,1,1,'[]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(15,'cancelled',NULL,NULL,'Cancelled',900,1,1,'[]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(16,'rejected',NULL,NULL,'Rejected',910,1,1,'[\"university_selection\",\"visa_preparation\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL),(17,'withdrawn',NULL,NULL,'Withdrawn',920,1,1,'[\"consultation\"]','2026-09-28 00:01:56','2026-09-28 00:02:00',NULL);
/*!40000 ALTER TABLE `applicant_stages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applicant_universities`
--

DROP TABLE IF EXISTS `applicant_universities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applicant_universities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` bigint(20) unsigned NOT NULL,
  `university_id` bigint(20) unsigned NOT NULL,
  `semester_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `intake` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `applicant_universities_applicant_id_university_id_intake_unique` (`applicant_id`,`university_id`,`intake`),
  KEY `applicant_universities_university_id_status_index` (`university_id`,`status`),
  KEY `applicant_universities_semester_id_foreign` (`semester_id`),
  CONSTRAINT `applicant_universities_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applicant_universities_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicant_universities_university_id_foreign` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicant_universities`
--

LOCK TABLES `applicant_universities` WRITE;
/*!40000 ALTER TABLE `applicant_universities` DISABLE KEYS */;
INSERT INTO `applicant_universities` VALUES (1,1,1,1,'pending','Fall 2027','Primary university choice.','2026-09-28 00:09:14','2026-09-28 00:09:14',NULL),(2,1,2,1,'applied','Fall 2027','Alternative university application.','2026-09-28 00:09:14','2026-09-28 00:09:14',NULL);
/*!40000 ALTER TABLE `applicant_universities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `applicants`
--

DROP TABLE IF EXISTS `applicants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `applicants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tax_number` varchar(255) DEFAULT NULL,
  `marketer` varchar(255) DEFAULT NULL,
  `marketer_id` bigint(20) unsigned DEFAULT NULL,
  `date` date DEFAULT NULL,
  `parent_applicant_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `responsible_employee_id` bigint(20) unsigned DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `university_id` bigint(20) unsigned DEFAULT NULL,
  `education_level_id` bigint(20) unsigned DEFAULT NULL,
  `language_id` bigint(20) unsigned DEFAULT NULL,
  `semester_id` bigint(20) unsigned DEFAULT NULL,
  `current_stage_id` bigint(20) unsigned DEFAULT NULL,
  `application_date` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `profile_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`profile_data`)),
  `profile_completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `applicants_user_id_unique` (`user_id`),
  KEY `applicants_country_id_foreign` (`country_id`),
  KEY `applicants_university_id_foreign` (`university_id`),
  KEY `applicants_education_level_id_foreign` (`education_level_id`),
  KEY `applicants_language_id_foreign` (`language_id`),
  KEY `applicants_current_stage_id_foreign` (`current_stage_id`),
  KEY `applicants_customer_id_status_index` (`status`),
  KEY `applicants_responsible_employee_id_current_stage_id_index` (`responsible_employee_id`,`current_stage_id`),
  KEY `applicants_status_index` (`status`),
  KEY `applicants_semester_id_index` (`semester_id`),
  KEY `applicants_name_idx` (`name`),
  KEY `applicants_marketer_id_idx` (`marketer_id`),
  KEY `applicants_parent_applicant_id_idx` (`parent_applicant_id`),
  KEY `applicants_name_status_idx` (`name`,`status`),
  CONSTRAINT `applicants_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicants_current_stage_id_foreign` FOREIGN KEY (`current_stage_id`) REFERENCES `applicant_stages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicants_education_level_id_foreign` FOREIGN KEY (`education_level_id`) REFERENCES `education_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicants_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicants_marketer_id_foreign` FOREIGN KEY (`marketer_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicants_parent_applicant_id_foreign` FOREIGN KEY (`parent_applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicants_responsible_employee_id_foreign` FOREIGN KEY (`responsible_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicants_semester_id_foreign` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicants_university_id_foreign` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicants`
--

LOCK TABLES `applicants` WRITE;
/*!40000 ALTER TABLE `applicants` DISABLE KEYS */;
INSERT INTO `applicants` VALUES (1,'John Doe','555-1234',NULL,NULL,NULL,'Sarah Johnson',NULL,'2023-10-15',NULL,NULL,1,1,1,2,1,1,2,'2026-09-18','active','Demo educational visa case.',NULL,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:14',NULL),(2,'Jane Smith','555-5678',NULL,NULL,NULL,'Michael Brown',NULL,'2023-10-16',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(3,'Bob Wilson','555-9012',NULL,NULL,NULL,'Sarah Johnson',NULL,'2023-10-17',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(4,'Alice Cooper','555-3456',NULL,NULL,NULL,'Michael Brown',NULL,'2023-10-18',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active',NULL,NULL,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL);
/*!40000 ALTER TABLE `applicants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asset_allocations`
--

DROP TABLE IF EXISTS `asset_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_allocations_asset_id_employee_id_unique` (`asset_id`,`employee_id`),
  KEY `asset_allocations_employee_id_foreign` (`employee_id`),
  CONSTRAINT `asset_allocations_asset_id_foreign` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_allocations_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asset_allocations`
--

LOCK TABLES `asset_allocations` WRITE;
/*!40000 ALTER TABLE `asset_allocations` DISABLE KEYS */;
/*!40000 ALTER TABLE `asset_allocations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `serial_number` varchar(255) NOT NULL,
  `item` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `model` varchar(255) NOT NULL,
  `condition` varchar(255) NOT NULL DEFAULT 'good',
  `office_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `assets_office_id_foreign` (`office_id`),
  CONSTRAINT `assets_office_id_foreign` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assets`
--

LOCK TABLES `assets` WRITE;
/*!40000 ALTER TABLE `assets` DISABLE KEYS */;
INSERT INTO `assets` VALUES (1,'SN-1001','Dell Laptop',1,0.00,'XPS 15','good',1,'2026-09-28 00:09:09','2026-09-28 00:09:09',NULL),(2,'SN-1002','HP Monitor',2,0.00,'27f','new',1,'2026-09-28 00:09:09','2026-09-28 00:09:09',NULL),(3,'SN-1003','Logitech Mouse',5,0.00,'MX Master','fair',1,'2026-09-28 00:09:09','2026-09-28 00:09:09',NULL);
/*!40000 ALTER TABLE `assets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attendances`
--

DROP TABLE IF EXISTS `attendances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `office_id` bigint(20) unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `status` enum('present','absent','paid-leave','unpaid-leave') NOT NULL,
  `leave_duration_days` decimal(3,1) NOT NULL DEFAULT 0.0,
  `paid_leave_days` decimal(3,1) NOT NULL DEFAULT 0.0,
  `unpaid_leave_days` decimal(3,1) NOT NULL DEFAULT 0.0,
  `logged_at` time NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `checked_in_at` timestamp NULL DEFAULT NULL,
  `checked_out_at` timestamp NULL DEFAULT NULL,
  `check_in_latitude` decimal(10,7) DEFAULT NULL,
  `check_in_longitude` decimal(10,7) DEFAULT NULL,
  `check_in_accuracy_meters` int(10) unsigned DEFAULT NULL,
  `check_in_distance_meters` int(10) unsigned DEFAULT NULL,
  `check_in_source` varchar(30) DEFAULT NULL,
  `check_out_latitude` decimal(10,7) DEFAULT NULL,
  `check_out_longitude` decimal(10,7) DEFAULT NULL,
  `check_out_accuracy_meters` int(10) unsigned DEFAULT NULL,
  `check_out_distance_meters` int(10) unsigned DEFAULT NULL,
  `check_out_source` varchar(30) DEFAULT NULL,
  `geofence_exit_detected_at` timestamp NULL DEFAULT NULL,
  `break_minutes` smallint(5) unsigned DEFAULT NULL,
  `gross_minutes` int(10) unsigned DEFAULT NULL,
  `net_worked_minutes` int(10) unsigned DEFAULT NULL,
  `required_minutes` int(10) unsigned DEFAULT NULL,
  `late_arrival_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `early_departure_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `overtime_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `missing_punch_state` varchar(20) NOT NULL DEFAULT 'none',
  `is_overnight_shift` tinyint(1) NOT NULL DEFAULT 0,
  `calculation_hash` varchar(64) DEFAULT NULL,
  `calculated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendances_employee_id_date_unique` (`employee_id`,`date`),
  KEY `attendances_office_id_foreign` (`office_id`),
  KEY `attendances_employee_id_checked_out_at_index` (`employee_id`,`checked_out_at`),
  KEY `attendances_date_office_id_status_index` (`date`,`office_id`,`status`),
  KEY `attendances_calculated_at_calculation_hash_index` (`calculated_at`,`calculation_hash`),
  CONSTRAINT `attendances_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendances_office_id_foreign` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendances`
--

LOCK TABLES `attendances` WRITE;
/*!40000 ALTER TABLE `attendances` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event` varchar(64) NOT NULL,
  `table_name` varchar(255) NOT NULL,
  `auditable_type` varchar(255) NOT NULL,
  `auditable_id` bigint(20) unsigned NOT NULL,
  `subject_type` varchar(80) DEFAULT NULL,
  `subject_label` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `audited_by` bigint(20) unsigned DEFAULT NULL,
  `review_status` varchar(20) NOT NULL DEFAULT 'pending',
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `audited_at` timestamp NULL DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `changed_columns` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changed_columns`)),
  `url` varchar(2048) DEFAULT NULL,
  `method` varchar(10) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_reviewed_by_foreign` (`reviewed_by`),
  KEY `audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audit_logs_table_name_event_created_at_index` (`table_name`,`event`,`created_at`),
  KEY `audit_logs_review_status_created_at_index` (`review_status`,`created_at`),
  KEY `audit_logs_employee_id_created_at_index` (`employee_id`,`created_at`),
  KEY `audit_logs_audited_at_created_at_index` (`audited_at`,`created_at`),
  KEY `audit_logs_audited_by_audited_at_index` (`audited_by`,`audited_at`),
  KEY `audit_logs_subject_type_created_at_index` (`subject_type`,`created_at`),
  CONSTRAINT `audit_logs_audited_by_foreign` FOREIGN KEY (`audited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=493 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,'created','permissions','App\\Models\\Permission',16,'permissions','view_dashboard',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_dashboard\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(2,'created','permissions','App\\Models\\Permission',17,'permissions','view_reports',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_reports\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(3,'created','permissions','App\\Models\\Permission',18,'permissions','export_reports',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"export_reports\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(4,'created','permissions','App\\Models\\Permission',19,'permissions','view_automation_runs',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_automation_runs\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(5,'created','permissions','App\\Models\\Permission',20,'permissions','view_analytics',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_analytics\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(6,'created','permissions','App\\Models\\Permission',21,'permissions','view_settings',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_settings\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(7,'created','permissions','App\\Models\\Permission',22,'permissions','edit_settings',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_settings\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(8,'created','permissions','App\\Models\\Permission',23,'permissions','view_audit_logs',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_audit_logs\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(9,'created','permissions','App\\Models\\Permission',24,'permissions','view_deleted_records',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_deleted_records\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(10,'created','permissions','App\\Models\\Permission',25,'permissions','review_audit_logs',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"review_audit_logs\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(11,'created','permissions','App\\Models\\Permission',26,'permissions','revert_audit_changes',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"revert_audit_changes\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(12,'updated','permissions','App\\Models\\Permission',1,'permissions','manage_audit_scope',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"guard_name\":\"web\"}','{\"guard_name\":\"api\"}','[\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(13,'created','permissions','App\\Models\\Permission',27,'permissions','view_subordinate_records',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_subordinate_records\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(14,'created','permissions','App\\Models\\Permission',28,'permissions','manage_subordinate_records',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_subordinate_records\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(15,'created','permissions','App\\Models\\Permission',29,'permissions','view_team_audit_logs',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_team_audit_logs\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(16,'created','permissions','App\\Models\\Permission',30,'permissions','view_all_audits',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_all_audits\",\"category\":\"core\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(17,'created','permissions','App\\Models\\Permission',31,'permissions','view_users',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_users\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(18,'created','permissions','App\\Models\\Permission',32,'permissions','create_users',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_users\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(19,'created','permissions','App\\Models\\Permission',33,'permissions','edit_users',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_users\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(20,'created','permissions','App\\Models\\Permission',34,'permissions','delete_users',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_users\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(21,'created','permissions','App\\Models\\Permission',35,'permissions','manage_user_status',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_user_status\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(22,'created','permissions','App\\Models\\Permission',36,'permissions','request_account_deactivation',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"request_account_deactivation\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(23,'created','permissions','App\\Models\\Permission',37,'permissions','cancel_own_deactivation_request',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"cancel_own_deactivation_request\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(24,'created','permissions','App\\Models\\Permission',38,'permissions','view_account_deactivation_requests',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_account_deactivation_requests\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(25,'created','permissions','App\\Models\\Permission',39,'permissions','review_account_deactivation_requests',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"review_account_deactivation_requests\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(26,'created','permissions','App\\Models\\Permission',40,'permissions','reactivate_user_accounts',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"reactivate_user_accounts\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(27,'created','permissions','App\\Models\\Permission',41,'permissions','assign_user_roles',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"assign_user_roles\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(28,'created','permissions','App\\Models\\Permission',42,'permissions','view_roles',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_roles\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(29,'created','permissions','App\\Models\\Permission',43,'permissions','create_roles',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_roles\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(30,'created','permissions','App\\Models\\Permission',44,'permissions','edit_roles',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_roles\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(31,'created','permissions','App\\Models\\Permission',45,'permissions','delete_roles',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_roles\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(32,'created','permissions','App\\Models\\Permission',46,'permissions','manage_role_permissions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_role_permissions\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(33,'created','permissions','App\\Models\\Permission',47,'permissions','manage_role_hierarchy',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_role_hierarchy\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(34,'created','permissions','App\\Models\\Permission',48,'permissions','view_permissions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_permissions\",\"category\":\"identity_access\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(35,'created','permissions','App\\Models\\Permission',49,'permissions','view_employees',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_employees\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(36,'created','permissions','App\\Models\\Permission',50,'permissions','view_all_employees',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_all_employees\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(37,'created','permissions','App\\Models\\Permission',51,'permissions','create_employees',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_employees\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(38,'created','permissions','App\\Models\\Permission',52,'permissions','edit_employees',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_employees\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(39,'created','permissions','App\\Models\\Permission',53,'permissions','manage_all_employees',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_all_employees\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(40,'created','permissions','App\\Models\\Permission',54,'permissions','delete_employees',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_employees\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(41,'created','permissions','App\\Models\\Permission',55,'permissions','view_departments',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_departments\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(42,'created','permissions','App\\Models\\Permission',56,'permissions','manage_departments',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_departments\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(43,'created','permissions','App\\Models\\Permission',57,'permissions','assign_employee_supervisor',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"assign_employee_supervisor\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(44,'created','permissions','App\\Models\\Permission',58,'permissions','view_paid_time_off',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_paid_time_off\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(45,'created','permissions','App\\Models\\Permission',59,'permissions','create_paid_time_off',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_paid_time_off\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(46,'created','permissions','App\\Models\\Permission',60,'permissions','edit_paid_time_off',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_paid_time_off\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(47,'created','permissions','App\\Models\\Permission',61,'permissions','delete_paid_time_off',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_paid_time_off\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:02','2026-09-28 00:09:02'),(48,'created','permissions','App\\Models\\Permission',62,'permissions','request_own_paid_time_off',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"request_own_paid_time_off\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(49,'created','permissions','App\\Models\\Permission',63,'permissions','grant_paid_time_off',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"grant_paid_time_off\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(50,'created','permissions','App\\Models\\Permission',64,'permissions','print_paid_time_off',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"print_paid_time_off\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(51,'created','permissions','App\\Models\\Permission',65,'permissions','view_attendance',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_attendance\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(52,'created','permissions','App\\Models\\Permission',66,'permissions','record_attendance',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"record_attendance\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(53,'created','permissions','App\\Models\\Permission',67,'permissions','delete_attendance',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_attendance\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(54,'created','permissions','App\\Models\\Permission',68,'permissions','view_unmarked_attendance_staff',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_unmarked_attendance_staff\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(55,'created','permissions','App\\Models\\Permission',69,'permissions','manage_own_workday',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_own_workday\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(56,'created','permissions','App\\Models\\Permission',70,'permissions','submit_own_daily_report',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"submit_own_daily_report\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(57,'created','permissions','App\\Models\\Permission',71,'permissions','view_salaries',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_salaries\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(58,'created','permissions','App\\Models\\Permission',72,'permissions','create_salaries',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_salaries\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(59,'created','permissions','App\\Models\\Permission',73,'permissions','edit_salaries',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_salaries\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(60,'created','permissions','App\\Models\\Permission',74,'permissions','delete_salaries',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_salaries\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(61,'created','permissions','App\\Models\\Permission',75,'permissions','view_salary_deductions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_salary_deductions\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(62,'created','permissions','App\\Models\\Permission',76,'permissions','create_salary_deductions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_salary_deductions\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(63,'created','permissions','App\\Models\\Permission',77,'permissions','edit_salary_deductions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_salary_deductions\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(64,'created','permissions','App\\Models\\Permission',78,'permissions','delete_salary_deductions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_salary_deductions\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(65,'created','permissions','App\\Models\\Permission',79,'permissions','view_bonuses',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_bonuses\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(66,'created','permissions','App\\Models\\Permission',80,'permissions','nominate_bonus_candidates',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"nominate_bonus_candidates\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(67,'created','permissions','App\\Models\\Permission',81,'permissions','edit_bonus_nominations',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_bonus_nominations\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(68,'created','permissions','App\\Models\\Permission',82,'permissions','grant_bonuses',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"grant_bonuses\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(69,'created','permissions','App\\Models\\Permission',83,'permissions','manage_bonus_rules',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_bonus_rules\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(70,'created','permissions','App\\Models\\Permission',84,'permissions','delete_bonus_nominations',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_bonus_nominations\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(71,'created','permissions','App\\Models\\Permission',85,'permissions','view_positions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_positions\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(72,'created','permissions','App\\Models\\Permission',86,'permissions','manage_positions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_positions\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(73,'created','permissions','App\\Models\\Permission',87,'permissions','view_marketers',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_marketers\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(74,'created','permissions','App\\Models\\Permission',88,'permissions','manage_marketers',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_marketers\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(75,'created','permissions','App\\Models\\Permission',89,'permissions','view_offices',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_offices\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(76,'created','permissions','App\\Models\\Permission',90,'permissions','manage_offices',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_offices\",\"category\":\"human_resources\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(77,'created','permissions','App\\Models\\Permission',91,'permissions','view_contracts',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_contracts\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(78,'created','permissions','App\\Models\\Permission',92,'permissions','view_all_contracts',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_all_contracts\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(79,'created','permissions','App\\Models\\Permission',93,'permissions','create_contracts',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_contracts\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(80,'created','permissions','App\\Models\\Permission',94,'permissions','edit_contracts',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_contracts\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(81,'created','permissions','App\\Models\\Permission',95,'permissions','cancel_contracts',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"cancel_contracts\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(82,'created','permissions','App\\Models\\Permission',96,'permissions','delete_contracts',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_contracts\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(83,'created','permissions','App\\Models\\Permission',97,'permissions','download_contracts',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"download_contracts\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(84,'created','permissions','App\\Models\\Permission',98,'permissions','view_contract_templates',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_contract_templates\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(85,'created','permissions','App\\Models\\Permission',99,'permissions','manage_contract_templates',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_contract_templates\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(86,'created','permissions','App\\Models\\Permission',100,'permissions','create_contract_template_versions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_contract_template_versions\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(87,'created','permissions','App\\Models\\Permission',101,'permissions','edit_draft_contract_template_versions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_draft_contract_template_versions\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(88,'created','permissions','App\\Models\\Permission',102,'permissions','activate_contract_template_versions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"activate_contract_template_versions\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(89,'created','permissions','App\\Models\\Permission',103,'permissions','archive_contract_template_versions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"archive_contract_template_versions\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(90,'created','permissions','App\\Models\\Permission',104,'permissions','view_contract_policies',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_contract_policies\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(91,'created','permissions','App\\Models\\Permission',105,'permissions','manage_contract_policies',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_contract_policies\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(92,'created','permissions','App\\Models\\Permission',106,'permissions','view_contract_types',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_contract_types\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(93,'created','permissions','App\\Models\\Permission',107,'permissions','manage_contract_types',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_contract_types\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(94,'created','permissions','App\\Models\\Permission',108,'permissions','view_companies',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_companies\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(95,'created','permissions','App\\Models\\Permission',109,'permissions','create_companies',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_companies\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(96,'created','permissions','App\\Models\\Permission',110,'permissions','edit_companies',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_companies\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(97,'created','permissions','App\\Models\\Permission',111,'permissions','delete_companies',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_companies\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(98,'created','permissions','App\\Models\\Permission',112,'permissions','view_services',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_services\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(99,'created','permissions','App\\Models\\Permission',113,'permissions','manage_services',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_services\",\"category\":\"customer_contracts\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(100,'created','permissions','App\\Models\\Permission',114,'permissions','view_invoices',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_invoices\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(101,'created','permissions','App\\Models\\Permission',115,'permissions','create_invoices',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_invoices\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(102,'created','permissions','App\\Models\\Permission',116,'permissions','edit_invoices',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_invoices\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(103,'created','permissions','App\\Models\\Permission',117,'permissions','void_invoices',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"void_invoices\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(104,'created','permissions','App\\Models\\Permission',118,'permissions','delete_invoices',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_invoices\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(105,'created','permissions','App\\Models\\Permission',119,'permissions','view_payment_details',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_payment_details\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(106,'created','permissions','App\\Models\\Permission',120,'permissions','create_payment_details',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_payment_details\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(107,'created','permissions','App\\Models\\Permission',121,'permissions','edit_payment_details',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_payment_details\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(108,'created','permissions','App\\Models\\Permission',122,'permissions','delete_payment_details',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_payment_details\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(109,'created','permissions','App\\Models\\Permission',123,'permissions','view_incomes',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_incomes\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(110,'created','permissions','App\\Models\\Permission',124,'permissions','create_incomes',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_incomes\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(111,'created','permissions','App\\Models\\Permission',125,'permissions','edit_incomes',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_incomes\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(112,'created','permissions','App\\Models\\Permission',126,'permissions','delete_incomes',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_incomes\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(113,'created','permissions','App\\Models\\Permission',127,'permissions','view_expenses',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_expenses\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(114,'created','permissions','App\\Models\\Permission',128,'permissions','create_expenses',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_expenses\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(115,'created','permissions','App\\Models\\Permission',129,'permissions','edit_expenses',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_expenses\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(116,'created','permissions','App\\Models\\Permission',130,'permissions','delete_expenses',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_expenses\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(117,'created','permissions','App\\Models\\Permission',131,'permissions','view_recurring_events',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_recurring_events\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(118,'created','permissions','App\\Models\\Permission',132,'permissions','create_recurring_events',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_recurring_events\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(119,'created','permissions','App\\Models\\Permission',133,'permissions','edit_recurring_events',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_recurring_events\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(120,'created','permissions','App\\Models\\Permission',134,'permissions','delete_recurring_events',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_recurring_events\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(121,'created','permissions','App\\Models\\Permission',135,'permissions','manage_recurring_events',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_recurring_events\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(122,'created','permissions','App\\Models\\Permission',136,'permissions','execute_recurring_events',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"execute_recurring_events\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(123,'created','permissions','App\\Models\\Permission',137,'permissions','create_recurring_incomes',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_recurring_incomes\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(124,'created','permissions','App\\Models\\Permission',138,'permissions','create_recurring_expenses',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_recurring_expenses\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(125,'created','permissions','App\\Models\\Permission',139,'permissions','create_recurring_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_recurring_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(126,'created','permissions','App\\Models\\Permission',140,'permissions','view_assets',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_assets\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(127,'created','permissions','App\\Models\\Permission',141,'permissions','create_assets',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_assets\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(128,'created','permissions','App\\Models\\Permission',142,'permissions','edit_assets',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_assets\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(129,'created','permissions','App\\Models\\Permission',143,'permissions','delete_assets',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_assets\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(130,'created','permissions','App\\Models\\Permission',144,'permissions','view_currencies',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_currencies\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(131,'created','permissions','App\\Models\\Permission',145,'permissions','manage_currencies',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_currencies\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(132,'created','permissions','App\\Models\\Permission',146,'permissions','view_financial_reports',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_financial_reports\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(133,'created','permissions','App\\Models\\Permission',147,'permissions','export_financial_reports',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"export_financial_reports\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(134,'created','permissions','App\\Models\\Permission',148,'permissions','view_projects',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_projects\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(135,'created','permissions','App\\Models\\Permission',149,'permissions','create_projects',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_projects\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(136,'created','permissions','App\\Models\\Permission',150,'permissions','edit_projects',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_projects\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(137,'created','permissions','App\\Models\\Permission',151,'permissions','delete_projects',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_projects\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(138,'created','permissions','App\\Models\\Permission',152,'permissions','view_assigned_boards',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_assigned_boards\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(139,'created','permissions','App\\Models\\Permission',153,'permissions','view_all_boards',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_all_boards\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(140,'created','permissions','App\\Models\\Permission',154,'permissions','create_boards',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_boards\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(141,'created','permissions','App\\Models\\Permission',155,'permissions','edit_assigned_boards',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_assigned_boards\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(142,'created','permissions','App\\Models\\Permission',156,'permissions','edit_all_boards',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_all_boards\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(143,'created','permissions','App\\Models\\Permission',157,'permissions','delete_assigned_boards',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_assigned_boards\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(144,'created','permissions','App\\Models\\Permission',158,'permissions','delete_all_boards',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_all_boards\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(145,'created','permissions','App\\Models\\Permission',159,'permissions','reorder_boards',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"reorder_boards\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(146,'created','permissions','App\\Models\\Permission',160,'permissions','assign_board_members',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"assign_board_members\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(147,'created','permissions','App\\Models\\Permission',161,'permissions','view_assigned_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_assigned_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(148,'created','permissions','App\\Models\\Permission',162,'permissions','view_all_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_all_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(149,'created','permissions','App\\Models\\Permission',163,'permissions','create_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(150,'created','permissions','App\\Models\\Permission',164,'permissions','edit_assigned_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_assigned_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(151,'created','permissions','App\\Models\\Permission',165,'permissions','edit_all_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_all_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(152,'created','permissions','App\\Models\\Permission',166,'permissions','delete_assigned_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_assigned_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(153,'created','permissions','App\\Models\\Permission',167,'permissions','delete_all_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_all_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(154,'created','permissions','App\\Models\\Permission',168,'permissions','move_assigned_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"move_assigned_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(155,'created','permissions','App\\Models\\Permission',169,'permissions','move_all_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"move_all_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(156,'created','permissions','App\\Models\\Permission',170,'permissions','assign_task_members',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"assign_task_members\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(157,'created','permissions','App\\Models\\Permission',171,'permissions','manage_task_dependencies',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_task_dependencies\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(158,'created','permissions','App\\Models\\Permission',172,'permissions','manage_task_tags',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_task_tags\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(159,'created','permissions','App\\Models\\Permission',173,'permissions','view_task_tags',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_task_tags\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(160,'created','permissions','App\\Models\\Permission',174,'permissions','manage_task_tags_catalog',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_task_tags_catalog\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(161,'created','permissions','App\\Models\\Permission',175,'permissions','create_subtasks_on_assigned_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_subtasks_on_assigned_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(162,'created','permissions','App\\Models\\Permission',176,'permissions','edit_own_subtasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_own_subtasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(163,'created','permissions','App\\Models\\Permission',177,'permissions','edit_all_subtasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_all_subtasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(164,'created','permissions','App\\Models\\Permission',178,'permissions','delete_own_subtasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_own_subtasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(165,'created','permissions','App\\Models\\Permission',179,'permissions','delete_all_subtasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_all_subtasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(166,'created','permissions','App\\Models\\Permission',180,'permissions','create_comments_on_assigned_tasks',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_comments_on_assigned_tasks\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(167,'created','permissions','App\\Models\\Permission',181,'permissions','edit_own_comments',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_own_comments\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(168,'created','permissions','App\\Models\\Permission',182,'permissions','edit_all_comments',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_all_comments\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(169,'created','permissions','App\\Models\\Permission',183,'permissions','delete_own_comments',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_own_comments\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(170,'created','permissions','App\\Models\\Permission',184,'permissions','delete_all_comments',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_all_comments\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(171,'created','permissions','App\\Models\\Permission',185,'permissions','override_edit_window',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"override_edit_window\",\"category\":\"task_management\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(172,'created','permissions','App\\Models\\Permission',186,'permissions','view_country',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_country\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(173,'created','permissions','App\\Models\\Permission',187,'permissions','create_country',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_country\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(174,'created','permissions','App\\Models\\Permission',188,'permissions','edit_country',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_country\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(175,'created','permissions','App\\Models\\Permission',189,'permissions','delete_country',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_country\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(176,'created','permissions','App\\Models\\Permission',190,'permissions','view_education_level',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_education_level\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(177,'created','permissions','App\\Models\\Permission',191,'permissions','create_education_level',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_education_level\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(178,'created','permissions','App\\Models\\Permission',192,'permissions','edit_education_level',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_education_level\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(179,'created','permissions','App\\Models\\Permission',193,'permissions','delete_education_level',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_education_level\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(180,'created','permissions','App\\Models\\Permission',194,'permissions','view_language',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_language\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(181,'created','permissions','App\\Models\\Permission',195,'permissions','create_language',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_language\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(182,'created','permissions','App\\Models\\Permission',196,'permissions','edit_language',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_language\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(183,'created','permissions','App\\Models\\Permission',197,'permissions','delete_language',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_language\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(184,'created','permissions','App\\Models\\Permission',198,'permissions','view_package_type',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_package_type\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(185,'created','permissions','App\\Models\\Permission',199,'permissions','create_package_type',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_package_type\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(186,'created','permissions','App\\Models\\Permission',200,'permissions','edit_package_type',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_package_type\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(187,'created','permissions','App\\Models\\Permission',201,'permissions','delete_package_type',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_package_type\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(188,'updated','permissions','App\\Models\\Permission',13,'permissions','view_packages',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"guard_name\":\"web\"}','{\"guard_name\":\"api\"}','[\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(189,'updated','permissions','App\\Models\\Permission',14,'permissions','manage_packages',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"guard_name\":\"web\"}','{\"guard_name\":\"api\"}','[\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(190,'updated','permissions','App\\Models\\Permission',15,'permissions','issue_packages',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"guard_name\":\"web\"}','{\"guard_name\":\"api\"}','[\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(191,'created','permissions','App\\Models\\Permission',202,'permissions','view_university',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_university\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(192,'created','permissions','App\\Models\\Permission',203,'permissions','create_university',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_university\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(193,'created','permissions','App\\Models\\Permission',204,'permissions','edit_university',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_university\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(194,'created','permissions','App\\Models\\Permission',205,'permissions','delete_university',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_university\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(195,'created','permissions','App\\Models\\Permission',206,'permissions','view_semester',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_semester\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(196,'created','permissions','App\\Models\\Permission',207,'permissions','create_semester',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_semester\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(197,'created','permissions','App\\Models\\Permission',208,'permissions','edit_semester',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_semester\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(198,'created','permissions','App\\Models\\Permission',209,'permissions','delete_semester',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_semester\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(199,'created','permissions','App\\Models\\Permission',210,'permissions','view_applicants',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_applicants\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(200,'created','permissions','App\\Models\\Permission',211,'permissions','view_all_applicants',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_all_applicants\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(201,'created','permissions','App\\Models\\Permission',212,'permissions','create_applicants',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_applicants\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(202,'created','permissions','App\\Models\\Permission',213,'permissions','edit_applicants',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_applicants\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(203,'created','permissions','App\\Models\\Permission',214,'permissions','delete_applicants',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_applicants\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(204,'created','permissions','App\\Models\\Permission',215,'permissions','change_applicant_stage',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"change_applicant_stage\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(205,'created','permissions','App\\Models\\Permission',216,'permissions','view_applicant_stages',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_applicant_stages\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(206,'created','permissions','App\\Models\\Permission',217,'permissions','manage_applicant_stages',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_applicant_stages\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(207,'created','permissions','App\\Models\\Permission',218,'permissions','assign_applicant_employee',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"assign_applicant_employee\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(208,'created','permissions','App\\Models\\Permission',219,'permissions','view_applicant_financials',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_applicant_financials\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(209,'created','permissions','App\\Models\\Permission',220,'permissions','view_applicant_contracts',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_applicant_contracts\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(210,'created','permissions','App\\Models\\Permission',221,'permissions','view_applicant_services',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_applicant_services\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(211,'created','permissions','App\\Models\\Permission',222,'permissions','view_educational_services',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_educational_services\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(212,'created','permissions','App\\Models\\Permission',223,'permissions','manage_educational_services',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_educational_services\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(213,'created','permissions','App\\Models\\Permission',224,'permissions','manage_educational_bonuses',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"manage_educational_bonuses\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(214,'created','permissions','App\\Models\\Permission',225,'permissions','view_visa_reports',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_visa_reports\",\"category\":\"educational_visa\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(215,'created','permissions','App\\Models\\Permission',226,'permissions','view_pending_transactions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_pending_transactions\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(216,'created','permissions','App\\Models\\Permission',227,'permissions','create_pending_transactions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_pending_transactions\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(217,'created','permissions','App\\Models\\Permission',228,'permissions','approve_pending_transactions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"approve_pending_transactions\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(218,'created','permissions','App\\Models\\Permission',229,'permissions','post_pending_transactions',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"post_pending_transactions\",\"category\":\"finance\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(219,'created','permissions','App\\Models\\Permission',230,'permissions','view_visitors',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_visitors\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(220,'created','permissions','App\\Models\\Permission',231,'permissions','create_visitors',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"create_visitors\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(221,'created','permissions','App\\Models\\Permission',232,'permissions','edit_visitors',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"edit_visitors\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(222,'created','permissions','App\\Models\\Permission',233,'permissions','delete_visitors',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_visitors\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(223,'created','permissions','App\\Models\\Permission',234,'permissions','view_own_notifications',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_own_notifications\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(224,'created','permissions','App\\Models\\Permission',235,'permissions','update_own_notifications',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"update_own_notifications\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(225,'created','permissions','App\\Models\\Permission',236,'permissions','delete_own_notifications',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_own_notifications\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(226,'created','permissions','App\\Models\\Permission',237,'permissions','send_notifications',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"send_notifications\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(227,'created','permissions','App\\Models\\Permission',238,'permissions','send_all_notifications',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"send_all_notifications\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(228,'created','permissions','App\\Models\\Permission',239,'permissions','upload_files',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"upload_files\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(229,'created','permissions','App\\Models\\Permission',240,'permissions','view_files',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"view_files\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(230,'created','permissions','App\\Models\\Permission',241,'permissions','download_files',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"download_files\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(231,'created','permissions','App\\Models\\Permission',242,'permissions','delete_files',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"delete_files\",\"category\":\"operations\",\"guard_name\":\"api\"}','[\"name\",\"category\",\"guard_name\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:03','2026-09-28 00:09:03'),(232,'created','currencies','App\\Models\\Currency',1,'currencies','Afghan Afghani',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Afghan Afghani\",\"code\":\"AFN\",\"symbol\":\"\\u060b\",\"rate\":1,\"is_base\":true,\"is_active\":true,\"decimal_places\":2}','[\"name\",\"code\",\"symbol\",\"rate\",\"is_base\",\"is_active\",\"decimal_places\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(233,'created','currencies','App\\Models\\Currency',2,'currencies','United States Dollar',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"United States Dollar\",\"code\":\"USD\",\"symbol\":\"$\",\"rate\":0.014,\"is_base\":false,\"is_active\":false,\"decimal_places\":2}','[\"name\",\"code\",\"symbol\",\"rate\",\"is_base\",\"is_active\",\"decimal_places\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(234,'created','currencies','App\\Models\\Currency',3,'currencies','Euro',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Euro\",\"code\":\"EUR\",\"symbol\":\"\\u20ac\",\"rate\":0.012,\"is_base\":false,\"is_active\":false,\"decimal_places\":2}','[\"name\",\"code\",\"symbol\",\"rate\",\"is_base\",\"is_active\",\"decimal_places\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(235,'created','currencies','App\\Models\\Currency',4,'currencies','British Pound',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"British Pound\",\"code\":\"GBP\",\"symbol\":\"\\u00a3\",\"rate\":0.01,\"is_base\":false,\"is_active\":false,\"decimal_places\":2}','[\"name\",\"code\",\"symbol\",\"rate\",\"is_base\",\"is_active\",\"decimal_places\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(236,'created','currencies','App\\Models\\Currency',5,'currencies','Japanese Yen',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Japanese Yen\",\"code\":\"JPY\",\"symbol\":\"\\u00a5\",\"rate\":2.16,\"is_base\":false,\"is_active\":false,\"decimal_places\":0}','[\"name\",\"code\",\"symbol\",\"rate\",\"is_base\",\"is_active\",\"decimal_places\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(237,'created','currencies','App\\Models\\Currency',6,'currencies','Canadian Dollar',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Canadian Dollar\",\"code\":\"CAD\",\"symbol\":\"CA$\",\"rate\":0.019,\"is_base\":false,\"is_active\":false,\"decimal_places\":2}','[\"name\",\"code\",\"symbol\",\"rate\",\"is_base\",\"is_active\",\"decimal_places\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(238,'created','currencies','App\\Models\\Currency',7,'currencies','Australian Dollar',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Australian Dollar\",\"code\":\"AUD\",\"symbol\":\"AU$\",\"rate\":0.021,\"is_base\":false,\"is_active\":false,\"decimal_places\":2}','[\"name\",\"code\",\"symbol\",\"rate\",\"is_base\",\"is_active\",\"decimal_places\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(239,'created','currencies','App\\Models\\Currency',8,'currencies','Swiss Franc',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Swiss Franc\",\"code\":\"CHF\",\"symbol\":\"CHF\",\"rate\":0.011,\"is_base\":false,\"is_active\":false,\"decimal_places\":2}','[\"name\",\"code\",\"symbol\",\"rate\",\"is_base\",\"is_active\",\"decimal_places\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(240,'created','currencies','App\\Models\\Currency',9,'currencies','Chinese Yuan',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Chinese Yuan\",\"code\":\"CNY\",\"symbol\":\"\\u00a5\",\"rate\":0.099,\"is_base\":false,\"is_active\":false,\"decimal_places\":2}','[\"name\",\"code\",\"symbol\",\"rate\",\"is_base\",\"is_active\",\"decimal_places\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(241,'created','currencies','App\\Models\\Currency',10,'currencies','Indian Rupee',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Indian Rupee\",\"code\":\"INR\",\"symbol\":\"\\u20b9\",\"rate\":1.17,\"is_base\":false,\"is_active\":false,\"decimal_places\":2}','[\"name\",\"code\",\"symbol\",\"rate\",\"is_base\",\"is_active\",\"decimal_places\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(242,'created','currencies','App\\Models\\Currency',11,'currencies','Russian Ruble',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Russian Ruble\",\"code\":\"RUB\",\"symbol\":\"\\u20bd\",\"rate\":1.15,\"is_base\":false,\"is_active\":false,\"decimal_places\":2}','[\"name\",\"code\",\"symbol\",\"rate\",\"is_base\",\"is_active\",\"decimal_places\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(243,'created','offices','App\\Models\\Office',1,'offices','Head Office',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"code\":\"HQ\",\"name\":\"Head Office\",\"status\":\"active\"}','[\"code\",\"name\",\"status\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(244,'created','positions','App\\Models\\Position',2,'positions','Junior Engineer',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Junior Engineer\",\"salary\":50000,\"status\":\"active\",\"description\":\"Entry-level engineering position\"}','[\"title\",\"salary\",\"status\",\"description\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(245,'created','positions','App\\Models\\Position',3,'positions','Senior Engineer',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Senior Engineer\",\"salary\":80000,\"status\":\"active\",\"description\":\"Experienced engineering position\"}','[\"title\",\"salary\",\"status\",\"description\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(246,'created','positions','App\\Models\\Position',4,'positions','Lead Consultant',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Lead Consultant\",\"salary\":100000,\"status\":\"active\",\"description\":\"Technical leadership position\"}','[\"title\",\"salary\",\"status\",\"description\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(247,'created','positions','App\\Models\\Position',5,'positions','Manager',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Manager\",\"salary\":120000,\"status\":\"active\",\"description\":\"Management position\"}','[\"title\",\"salary\",\"status\",\"description\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(248,'created','positions','App\\Models\\Position',6,'positions','Director',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Director\",\"salary\":150000,\"status\":\"active\",\"description\":\"Executive leadership position\"}','[\"title\",\"salary\",\"status\",\"description\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:06','2026-09-28 00:09:06'),(249,'created','users','App\\Models\\User',1,'users','Demo Super Admin',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"email\":\"admin@example.test\",\"name\":\"Demo Super Admin\",\"phone\":\"0700000000\",\"address\":\"Demo Office, Kabul\",\"acc_status\":\"active\",\"email_verified_at\":\"2026-09-28 04:39:07\"}','[\"email\",\"name\",\"phone\",\"address\",\"acc_status\",\"email_verified_at\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:07','2026-09-28 00:09:07'),(250,'created','employees','App\\Models\\Employee',1,'employees','Demo Super Admin',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":1,\"name\":\"Demo Super Admin\",\"father_name\":\"N\\/A\",\"contact\":\"0700000000\",\"addr\":\"Demo Office, Kabul\",\"education_lvl\":\"Bachelor\",\"position_id\":6,\"salary\":150000,\"department\":\"Administration\",\"join_date\":\"2026-09-28 00:00:00\",\"contract_duration\":\"Permanent\"}','[\"user_id\",\"name\",\"father_name\",\"contact\",\"addr\",\"education_lvl\",\"position_id\",\"salary\",\"department\",\"join_date\",\"contract_duration\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:07','2026-09-28 00:09:07'),(251,'created','users','App\\Models\\User',2,'users','John Doe',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"email\":\"john.doe@example.com\",\"name\":\"John Doe\",\"phone\":\"555-1234\",\"address\":\"123 Main St, Cityville\",\"acc_status\":\"active\"}','[\"email\",\"name\",\"phone\",\"address\",\"acc_status\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:07','2026-09-28 00:09:07'),(252,'created','employees','App\\Models\\Employee',2,'employees','John Doe',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":2,\"name\":\"John Doe\",\"father_name\":\"Michael Doe\",\"contact\":\"555-1234\",\"education_lvl\":\"Bachelor\",\"addr\":\"123 Main St, Cityville\",\"position_id\":1,\"salary\":5000,\"join_date\":\"2022-01-15 00:00:00\",\"contract_duration\":\"2 years\"}','[\"user_id\",\"name\",\"father_name\",\"contact\",\"education_lvl\",\"addr\",\"position_id\",\"salary\",\"join_date\",\"contract_duration\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:07','2026-09-28 00:09:07'),(253,'created','users','App\\Models\\User',3,'users','Jane Smith',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"email\":\"jane.smith@example.com\",\"name\":\"Jane Smith\",\"phone\":\"555-5678\",\"address\":\"456 Oak Ave, Townsville\",\"acc_status\":\"active\"}','[\"email\",\"name\",\"phone\",\"address\",\"acc_status\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(254,'created','employees','App\\Models\\Employee',3,'employees','Jane Smith',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":3,\"name\":\"Jane Smith\",\"father_name\":\"Robert Smith\",\"contact\":\"555-5678\",\"education_lvl\":\"Master\",\"addr\":\"456 Oak Ave, Townsville\",\"position_id\":1,\"salary\":6500,\"join_date\":\"2021-11-01 00:00:00\",\"contract_duration\":\"3 years\"}','[\"user_id\",\"name\",\"father_name\",\"contact\",\"education_lvl\",\"addr\",\"position_id\",\"salary\",\"join_date\",\"contract_duration\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(255,'created','users','App\\Models\\User',4,'users','David Johnson',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"email\":\"david.johnson@example.com\",\"name\":\"David Johnson\",\"phone\":\"555-9012\",\"address\":\"789 Pine Rd, Villagetown\",\"acc_status\":\"active\"}','[\"email\",\"name\",\"phone\",\"address\",\"acc_status\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(256,'created','employees','App\\Models\\Employee',4,'employees','David Johnson',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":4,\"name\":\"David Johnson\",\"father_name\":\"William Johnson\",\"contact\":\"555-9012\",\"education_lvl\":\"High School\",\"addr\":\"789 Pine Rd, Villagetown\",\"position_id\":1,\"salary\":3500,\"join_date\":\"2023-02-20 00:00:00\",\"contract_duration\":\"1 year\"}','[\"user_id\",\"name\",\"father_name\",\"contact\",\"education_lvl\",\"addr\",\"position_id\",\"salary\",\"join_date\",\"contract_duration\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(257,'created','services','App\\Models\\Service',1,'services','Initial Education Consultation',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"type\":\"Initial Education Consultation\",\"category\":\"general\",\"fee\":1500,\"status\":\"active\",\"currency_id\":1}','[\"type\",\"category\",\"fee\",\"status\",\"currency_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(258,'created','services','App\\Models\\Service',2,'services','Document Review',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"type\":\"Document Review\",\"category\":\"general\",\"fee\":5000,\"status\":\"active\",\"currency_id\":1}','[\"type\",\"category\",\"fee\",\"status\",\"currency_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(259,'created','services','App\\Models\\Service',3,'services','University Application Support',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"type\":\"University Application Support\",\"category\":\"general\",\"fee\":15000,\"status\":\"active\",\"currency_id\":1}','[\"type\",\"category\",\"fee\",\"status\",\"currency_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(260,'created','services','App\\Models\\Service',4,'services','Scholarship Application Support',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"type\":\"Scholarship Application Support\",\"category\":\"scholarship\",\"fee\":12000,\"status\":\"active\",\"currency_id\":1}','[\"type\",\"category\",\"fee\",\"status\",\"currency_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(261,'created','services','App\\Models\\Service',5,'services','English Language Test Offer',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"type\":\"English Language Test Offer\",\"category\":\"test_offer\",\"fee\":5000,\"status\":\"active\",\"currency_id\":1}','[\"type\",\"category\",\"fee\",\"status\",\"currency_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(262,'created','services','App\\Models\\Service',6,'services','Student Visa Application Support',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"type\":\"Student Visa Application Support\",\"category\":\"general\",\"fee\":20000,\"status\":\"active\",\"currency_id\":1}','[\"type\",\"category\",\"fee\",\"status\",\"currency_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(263,'created','services','App\\Models\\Service',7,'services','Statement of Purpose and CV Review',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"type\":\"Statement of Purpose and CV Review\",\"category\":\"general\",\"fee\":8000,\"status\":\"active\",\"currency_id\":1}','[\"type\",\"category\",\"fee\",\"status\",\"currency_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(264,'created','services','App\\Models\\Service',8,'services','Visa Interview Preparation',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"type\":\"Visa Interview Preparation\",\"category\":\"general\",\"fee\":5000,\"status\":\"active\",\"currency_id\":1}','[\"type\",\"category\",\"fee\",\"status\",\"currency_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(265,'created','services','App\\Models\\Service',9,'services','Translation Coordination',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"type\":\"Translation Coordination\",\"category\":\"general\",\"fee\":3000,\"status\":\"active\",\"currency_id\":1}','[\"type\",\"category\",\"fee\",\"status\",\"currency_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(266,'created','applicants','App\\Models\\Applicant',1,'applicants','John Doe',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"John Doe\",\"contact\":\"555-1234\",\"marketer\":\"Sarah Johnson\",\"date\":\"2023-10-15 00:00:00\"}','[\"name\",\"contact\",\"marketer\",\"date\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(267,'created','applicants','App\\Models\\Applicant',2,'applicants','Jane Smith',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Jane Smith\",\"contact\":\"555-5678\",\"marketer\":\"Michael Brown\",\"date\":\"2023-10-16 00:00:00\"}','[\"name\",\"contact\",\"marketer\",\"date\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(268,'created','applicants','App\\Models\\Applicant',3,'applicants','Bob Wilson',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Bob Wilson\",\"contact\":\"555-9012\",\"marketer\":\"Sarah Johnson\",\"date\":\"2023-10-17 00:00:00\"}','[\"name\",\"contact\",\"marketer\",\"date\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(269,'created','contracts','App\\Models\\Contract',1,'contracts','Seeded service agreement',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"contract_type_id\":2,\"applicant_id\":1,\"name\":\"Seeded service agreement\",\"type\":\"Customer\",\"status\":\"active\",\"start_date\":\"2026-09-28 00:00:00\",\"counterparty\":\"John Doe\",\"counterparty_company\":null,\"counterparty_contact\":\"555-1234\",\"content\":\"\",\"is_custom\":true}','[\"contract_type_id\",\"applicant_id\",\"name\",\"type\",\"status\",\"start_date\",\"counterparty\",\"counterparty_company\",\"counterparty_contact\",\"content\",\"is_custom\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(270,'created','contract_service','App\\Models\\ContractService',1,'contract_services','CTR-2026-000001 · Initial Education Consultation',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"service_id\":1,\"contract_id\":1}','[\"service_id\",\"contract_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(271,'created','contracts','App\\Models\\Contract',2,'contracts','Seeded service agreement',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"contract_type_id\":2,\"applicant_id\":2,\"name\":\"Seeded service agreement\",\"type\":\"Customer\",\"status\":\"active\",\"start_date\":\"2026-09-28 00:00:00\",\"counterparty\":\"Jane Smith\",\"counterparty_company\":null,\"counterparty_contact\":\"555-5678\",\"content\":\"\",\"is_custom\":true}','[\"contract_type_id\",\"applicant_id\",\"name\",\"type\",\"status\",\"start_date\",\"counterparty\",\"counterparty_company\",\"counterparty_contact\",\"content\",\"is_custom\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(272,'created','contract_service','App\\Models\\ContractService',2,'contract_services','CTR-2026-000002 · Document Review',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"service_id\":2,\"contract_id\":2}','[\"service_id\",\"contract_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(273,'created','contracts','App\\Models\\Contract',3,'contracts','Seeded service agreement',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"contract_type_id\":2,\"applicant_id\":3,\"name\":\"Seeded service agreement\",\"type\":\"Customer\",\"status\":\"active\",\"start_date\":\"2026-09-28 00:00:00\",\"counterparty\":\"Bob Wilson\",\"counterparty_company\":null,\"counterparty_contact\":\"555-9012\",\"content\":\"\",\"is_custom\":true}','[\"contract_type_id\",\"applicant_id\",\"name\",\"type\",\"status\",\"start_date\",\"counterparty\",\"counterparty_company\",\"counterparty_contact\",\"content\",\"is_custom\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(274,'created','contract_service','App\\Models\\ContractService',3,'contract_services','CTR-2026-000003 · University Application Support',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"service_id\":3,\"contract_id\":3}','[\"service_id\",\"contract_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(275,'created','applicants','App\\Models\\Applicant',4,'applicants','Alice Cooper',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Alice Cooper\",\"contact\":\"555-3456\",\"marketer\":\"Michael Brown\",\"date\":\"2023-10-18 00:00:00\"}','[\"name\",\"contact\",\"marketer\",\"date\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(276,'created','contracts','App\\Models\\Contract',4,'contracts','Seeded service agreement',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"contract_type_id\":2,\"applicant_id\":4,\"name\":\"Seeded service agreement\",\"type\":\"Customer\",\"status\":\"active\",\"start_date\":\"2026-09-28 00:00:00\",\"counterparty\":\"Alice Cooper\",\"counterparty_company\":null,\"counterparty_contact\":\"555-3456\",\"content\":\"\",\"is_custom\":true}','[\"contract_type_id\",\"applicant_id\",\"name\",\"type\",\"status\",\"start_date\",\"counterparty\",\"counterparty_company\",\"counterparty_contact\",\"content\",\"is_custom\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(277,'created','contract_service','App\\Models\\ContractService',4,'contract_services','CTR-2026-000004 · Initial Education Consultation',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"service_id\":1,\"contract_id\":4}','[\"service_id\",\"contract_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(278,'created','contract_service','App\\Models\\ContractService',5,'contract_services','CTR-2026-000004 · University Application Support',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"service_id\":3,\"contract_id\":4}','[\"service_id\",\"contract_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:08','2026-09-28 00:09:08'),(279,'created','assets','App\\Models\\Asset',1,'assets','SN-1001',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"serial_number\":\"SN-1001\",\"item\":\"Dell Laptop\",\"quantity\":1,\"model\":\"XPS 15\",\"condition\":\"good\",\"office_id\":1}','[\"serial_number\",\"item\",\"quantity\",\"model\",\"condition\",\"office_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:09','2026-09-28 00:09:09'),(280,'created','assets','App\\Models\\Asset',2,'assets','SN-1002',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"serial_number\":\"SN-1002\",\"item\":\"HP Monitor\",\"quantity\":2,\"model\":\"27f\",\"condition\":\"new\",\"office_id\":1}','[\"serial_number\",\"item\",\"quantity\",\"model\",\"condition\",\"office_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:09','2026-09-28 00:09:09'),(281,'created','assets','App\\Models\\Asset',3,'assets','SN-1003',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"serial_number\":\"SN-1003\",\"item\":\"Logitech Mouse\",\"quantity\":5,\"model\":\"MX Master\",\"condition\":\"fair\",\"office_id\":1}','[\"serial_number\",\"item\",\"quantity\",\"model\",\"condition\",\"office_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:09','2026-09-28 00:09:09'),(282,'created','boards','App\\Models\\Board',1,'boards','Backlog',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Backlog\",\"order\":0}','[\"title\",\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(283,'created','boards','App\\Models\\Board',2,'boards','To Do',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"To Do\",\"order\":1}','[\"title\",\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(284,'created','boards','App\\Models\\Board',3,'boards','In Progress',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"In Progress\",\"order\":2}','[\"title\",\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(285,'created','boards','App\\Models\\Board',4,'boards','Review',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Review\",\"order\":3}','[\"title\",\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(286,'created','boards','App\\Models\\Board',5,'boards','Done',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Done\",\"order\":4}','[\"title\",\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(287,'created','tags','App\\Models\\Tag',1,'tags','Bug',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Bug\",\"color\":\"error\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(288,'created','tags','App\\Models\\Tag',2,'tags','Feature',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Feature\",\"color\":\"primary\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(289,'created','tags','App\\Models\\Tag',3,'tags','Enhancement',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Enhancement\",\"color\":\"info\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(290,'created','tags','App\\Models\\Tag',4,'tags','Documentation',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Documentation\",\"color\":\"warning\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(291,'created','tags','App\\Models\\Tag',5,'tags','Research',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Research\",\"color\":\"secondary\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(292,'created','tags','App\\Models\\Tag',6,'tags','Testing',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Testing\",\"color\":\"success\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(293,'created','tags','App\\Models\\Tag',7,'tags','UI/UX',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"UI\\/UX\",\"color\":\"primary\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(294,'created','tags','App\\Models\\Tag',8,'tags','Backend',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Backend\",\"color\":\"error\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(295,'created','tasks','App\\Models\\Task',1,'tasks','Eius tempore eum numquam ut blanditiis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Eius tempore eum numquam ut blanditiis.\",\"description\":\"Maiores sapiente beatae rerum minus consectetur nulla. Illum voluptatibus assumenda doloribus soluta sit recusandae quam. Et fuga distinctio nulla fugiat.\",\"priority\":\"urgent\",\"progress\":34,\"start_date\":\"2026-09-24 00:00:00\",\"due_date\":\"2026-09-28 00:00:00\",\"order\":0,\"board_id\":1}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(296,'created','tasks','App\\Models\\Task',2,'tasks','Et nostrum soluta praesentium fuga eum.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Et nostrum soluta praesentium fuga eum.\",\"description\":\"Eveniet voluptatem repellendus facere nostrum non tempora. Sit mollitia enim nihil delectus.\",\"priority\":\"urgent\",\"progress\":8,\"start_date\":\"2026-09-26 00:00:00\",\"due_date\":\"2026-09-30 00:00:00\",\"order\":0,\"board_id\":1}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(297,'created','tasks','App\\Models\\Task',3,'tasks','Nam nam repellat natus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Nam nam repellat natus.\",\"description\":\"Atque eos et qui fugiat sunt. Fugiat praesentium et ea dolor laboriosam nostrum voluptatibus. Cupiditate quia autem qui. Sit possimus id facere veniam atque sit.\",\"priority\":\"medium\",\"progress\":100,\"start_date\":\"2026-09-23 00:00:00\",\"due_date\":\"2026-10-07 00:00:00\",\"order\":0,\"board_id\":1,\"completed_at\":\"2026-09-28 04:39:10\"}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\",\"completed_at\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(298,'created','tasks','App\\Models\\Task',4,'tasks','Quam et voluptas inventore ullam.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Quam et voluptas inventore ullam.\",\"description\":\"Labore et consequatur ut dolores blanditiis. Illum voluptates rerum placeat modi rerum quia. Eaque itaque ut odio placeat. Ut molestiae dolor aperiam sed quo illo fugiat.\",\"priority\":\"medium\",\"progress\":8,\"start_date\":\"2026-09-28 00:00:00\",\"due_date\":\"2026-10-01 00:00:00\",\"order\":0,\"board_id\":1}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(299,'created','tasks','App\\Models\\Task',5,'tasks','Iusto iusto dolorum velit.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Iusto iusto dolorum velit.\",\"description\":\"Facilis ratione quaerat quos vel rerum dolore rerum. Perferendis nulla provident suscipit excepturi dolorem. Modi dolorem omnis rem id quis. Sint voluptatem ipsum est natus vero et nihil in. Esse ex qui et excepturi.\",\"priority\":\"low\",\"progress\":36,\"start_date\":\"2026-09-24 00:00:00\",\"due_date\":\"2026-10-10 00:00:00\",\"order\":0,\"board_id\":1}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(300,'created','tasks','App\\Models\\Task',6,'tasks','Rerum veritatis enim dignissimos autem.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Rerum veritatis enim dignissimos autem.\",\"description\":\"Quasi dicta fugiat velit iste error dolores accusantium. Sint incidunt eaque similique accusamus. Porro animi ut repudiandae veniam quia omnis quaerat. Fugit qui molestiae nobis consequuntur ex.\",\"priority\":\"urgent\",\"progress\":53,\"start_date\":\"2026-09-25 00:00:00\",\"due_date\":\"2026-10-07 00:00:00\",\"order\":0,\"board_id\":1}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(301,'created','tasks','App\\Models\\Task',7,'tasks','Iure nemo aliquam soluta aut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Iure nemo aliquam soluta aut.\",\"description\":\"Eius est numquam eius est veritatis facilis enim. Error et vel quis sit enim. Excepturi illum eius fuga ducimus aperiam.\",\"priority\":\"low\",\"progress\":43,\"start_date\":\"2026-09-22 00:00:00\",\"due_date\":\"2026-09-28 00:00:00\",\"order\":0,\"board_id\":1}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(302,'created','tasks','App\\Models\\Task',8,'tasks','Dicta dolorum deserunt hic.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Dicta dolorum deserunt hic.\",\"description\":\"Eveniet numquam inventore quia repudiandae omnis. Harum excepturi dignissimos incidunt non est eum. Tempore expedita est recusandae eos tempore.\",\"priority\":\"urgent\",\"progress\":0,\"start_date\":\"2026-09-24 00:00:00\",\"due_date\":\"2026-10-03 00:00:00\",\"order\":0,\"board_id\":1}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(303,'created','tasks','App\\Models\\Task',9,'tasks','Quae dolores ipsa placeat fuga.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Quae dolores ipsa placeat fuga.\",\"description\":\"Voluptatem esse ex voluptas eum corrupti et quam. Explicabo numquam vel distinctio. Reiciendis qui et officia voluptatem eveniet. Tempore ipsum voluptatem perferendis quae dolor ea earum nihil.\",\"priority\":\"low\",\"progress\":81,\"start_date\":\"2026-09-25 00:00:00\",\"due_date\":\"2026-10-08 00:00:00\",\"order\":0,\"board_id\":2}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(304,'created','tasks','App\\Models\\Task',10,'tasks','Quod architecto et odit.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Quod architecto et odit.\",\"description\":\"Porro similique id dolor expedita harum accusamus et eum. Tenetur consequatur nobis quia iure ipsam a. Aliquid laborum quos non aliquid corrupti. Est qui soluta minima aut et aliquam est.\",\"priority\":\"high\",\"progress\":97,\"start_date\":\"2026-09-24 00:00:00\",\"due_date\":\"2026-10-10 00:00:00\",\"order\":0,\"board_id\":2}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(305,'created','tasks','App\\Models\\Task',11,'tasks','Deserunt iusto rem maxime.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Deserunt iusto rem maxime.\",\"description\":\"Consequuntur labore qui a rerum soluta beatae. Ut vitae deserunt id quaerat ad ratione. Non aliquam delectus velit eos.\",\"priority\":\"urgent\",\"progress\":15,\"start_date\":\"2026-09-25 00:00:00\",\"due_date\":\"2026-10-06 00:00:00\",\"order\":0,\"board_id\":2}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(306,'created','tasks','App\\Models\\Task',12,'tasks','Et odit quaerat consectetur optio.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Et odit quaerat consectetur optio.\",\"description\":\"Quisquam ut reprehenderit deserunt aliquam sapiente. Qui aspernatur iste nihil facere. Tempora error nostrum saepe voluptatem consequatur nisi.\",\"priority\":\"urgent\",\"progress\":58,\"start_date\":\"2026-09-25 00:00:00\",\"due_date\":\"2026-09-29 00:00:00\",\"order\":0,\"board_id\":3}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(307,'created','tasks','App\\Models\\Task',13,'tasks','Deserunt necessitatibus molestiae ut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Deserunt necessitatibus molestiae ut.\",\"description\":\"Ex sunt cum ut doloribus consequatur qui. Sit natus pariatur magnam voluptas ducimus id. Explicabo sunt odit eligendi.\",\"priority\":\"urgent\",\"progress\":20,\"start_date\":\"2026-09-28 00:00:00\",\"due_date\":\"2026-10-03 00:00:00\",\"order\":0,\"board_id\":3}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(308,'created','tasks','App\\Models\\Task',14,'tasks','Cupiditate recusandae aut placeat.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Cupiditate recusandae aut placeat.\",\"description\":\"Pariatur ab fuga maxime voluptatem ratione sed ad. Aut est aliquid voluptas inventore assumenda quia id repellendus. Laudantium laborum quia ea non cupiditate pariatur. Ducimus nisi aut dignissimos quas eveniet deserunt dolorem. Ut sit illum sapiente nulla ipsum qui vel ullam.\",\"priority\":\"urgent\",\"progress\":22,\"start_date\":\"2026-09-23 00:00:00\",\"due_date\":\"2026-09-29 00:00:00\",\"order\":0,\"board_id\":3}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(309,'created','tasks','App\\Models\\Task',15,'tasks','Dignissimos omnis molestiae esse assumenda.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Dignissimos omnis molestiae esse assumenda.\",\"description\":\"Incidunt vel et amet et sed. Nam quia rerum accusantium enim. Et omnis unde quisquam asperiores magni. Itaque earum quae quae labore rerum iusto eaque.\",\"priority\":\"urgent\",\"progress\":86,\"start_date\":\"2026-09-25 00:00:00\",\"due_date\":\"2026-09-29 00:00:00\",\"order\":0,\"board_id\":4}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(310,'created','tasks','App\\Models\\Task',16,'tasks','Autem sequi accusantium ut voluptates.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Autem sequi accusantium ut voluptates.\",\"description\":\"Magnam ea sequi possimus ut qui. Vel non expedita eum sapiente odit. Sit harum optio excepturi ea rem sit quia.\",\"priority\":\"low\",\"progress\":17,\"start_date\":\"2026-09-28 00:00:00\",\"due_date\":\"2026-10-03 00:00:00\",\"order\":0,\"board_id\":4}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(311,'created','tasks','App\\Models\\Task',17,'tasks','Incidunt provident molestiae et dolores.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Incidunt provident molestiae et dolores.\",\"description\":\"Aut dolorum itaque sequi eligendi. Placeat vero facere nostrum culpa possimus ipsa quasi non. Odio qui accusamus at.\",\"priority\":\"low\",\"progress\":70,\"start_date\":\"2026-09-24 00:00:00\",\"due_date\":\"2026-10-04 00:00:00\",\"order\":0,\"board_id\":4}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(312,'created','tasks','App\\Models\\Task',18,'tasks','Non enim ut blanditiis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Non enim ut blanditiis.\",\"description\":\"Et et quia cumque velit labore fuga. Accusantium dolorem sit est quos officiis voluptatem esse.\",\"priority\":\"medium\",\"progress\":68,\"start_date\":\"2026-09-23 00:00:00\",\"due_date\":\"2026-10-06 00:00:00\",\"order\":0,\"board_id\":4}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(313,'created','tasks','App\\Models\\Task',19,'tasks','Amet sunt quas.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Amet sunt quas.\",\"description\":\"Consequuntur dolorum repellat consequatur. Et natus optio quo. Dicta excepturi id atque vel eum sit.\",\"priority\":\"high\",\"progress\":89,\"start_date\":\"2026-09-21 00:00:00\",\"due_date\":\"2026-10-03 00:00:00\",\"order\":0,\"board_id\":4}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(314,'created','tasks','App\\Models\\Task',20,'tasks','Eius dignissimos accusantium ut laboriosam et.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Eius dignissimos accusantium ut laboriosam et.\",\"description\":\"Et enim facilis aliquid dicta. Adipisci ipsum aut dicta neque magni illum. Est error vero amet.\",\"priority\":\"low\",\"progress\":96,\"start_date\":\"2026-09-27 00:00:00\",\"due_date\":\"2026-09-30 00:00:00\",\"order\":0,\"board_id\":5}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(315,'created','tasks','App\\Models\\Task',21,'tasks','Quis temporibus et odio.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Quis temporibus et odio.\",\"description\":\"Quia debitis est illo qui ipsam adipisci molestiae et. Voluptas dignissimos laborum quisquam tenetur ut dolorem. Eos eligendi mollitia voluptates quae dolorem. Dolores enim molestiae voluptatem earum provident perferendis.\",\"priority\":\"medium\",\"progress\":60,\"start_date\":\"2026-09-21 00:00:00\",\"due_date\":\"2026-10-03 00:00:00\",\"order\":0,\"board_id\":5}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(316,'created','tasks','App\\Models\\Task',22,'tasks','Fugiat voluptatem consequuntur.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Fugiat voluptatem consequuntur.\",\"description\":\"Id voluptatem molestias facere. Ut quod nemo odit provident. Consequatur quam at aliquam cumque assumenda ipsa officiis. Vitae consequatur mollitia molestiae ut.\",\"priority\":\"high\",\"progress\":44,\"start_date\":\"2026-09-22 00:00:00\",\"due_date\":\"2026-10-10 00:00:00\",\"order\":0,\"board_id\":5}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(317,'created','tasks','App\\Models\\Task',23,'tasks','Omnis fuga et natus ex.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Omnis fuga et natus ex.\",\"description\":\"Qui reiciendis quia aliquam a asperiores. Hic nostrum quibusdam deleniti eum ad doloribus harum reprehenderit. Et eos numquam tenetur nemo dicta. Aut rerum repellat nobis necessitatibus porro eos autem.\",\"priority\":\"medium\",\"progress\":29,\"start_date\":\"2026-09-25 00:00:00\",\"due_date\":\"2026-10-08 00:00:00\",\"order\":0,\"board_id\":5}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:10','2026-09-28 00:09:10'),(318,'created','comments','App\\Models\\Comment',1,'comments','Eius tempore eum numquam ut blanditiis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Sint quibusdam accusamus ullam est.\",\"task_id\":1,\"user_id\":1}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(319,'created','comments','App\\Models\\Comment',2,'comments','Et nostrum soluta praesentium fuga eum.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Quia voluptatem est ut accusamus dolore et in.\",\"task_id\":2,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(320,'created','comments','App\\Models\\Comment',3,'comments','Quam et voluptas inventore ullam.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Sunt et quas et aliquid eveniet molestiae.\",\"task_id\":4,\"user_id\":3}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(321,'created','comments','App\\Models\\Comment',4,'comments','Quam et voluptas inventore ullam.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Quam commodi quia nihil id fugit dolorem qui in.\",\"task_id\":4,\"user_id\":3}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(322,'created','comments','App\\Models\\Comment',5,'comments','Quam et voluptas inventore ullam.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Laboriosam occaecati totam aut harum sed inventore dolores.\",\"task_id\":4,\"user_id\":3}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(323,'created','comments','App\\Models\\Comment',6,'comments','Quam et voluptas inventore ullam.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Eius occaecati eum reprehenderit repellendus.\",\"task_id\":4,\"user_id\":3}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(324,'created','comments','App\\Models\\Comment',7,'comments','Iusto iusto dolorum velit.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Eos sit temporibus iure soluta eum.\",\"task_id\":5,\"user_id\":4}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(325,'created','comments','App\\Models\\Comment',8,'comments','Rerum veritatis enim dignissimos autem.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Temporibus ut et nisi nisi et omnis maxime.\",\"task_id\":6,\"user_id\":4}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(326,'created','comments','App\\Models\\Comment',9,'comments','Rerum veritatis enim dignissimos autem.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Repellendus rem et alias.\",\"task_id\":6,\"user_id\":4}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(327,'created','comments','App\\Models\\Comment',10,'comments','Iure nemo aliquam soluta aut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Dolores voluptatem ea exercitationem necessitatibus.\",\"task_id\":7,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(328,'created','comments','App\\Models\\Comment',11,'comments','Iure nemo aliquam soluta aut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Pariatur aut repudiandae repudiandae voluptatum.\",\"task_id\":7,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(329,'created','comments','App\\Models\\Comment',12,'comments','Dicta dolorum deserunt hic.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Quisquam repellat iusto consequatur rerum.\",\"task_id\":8,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(330,'created','comments','App\\Models\\Comment',13,'comments','Dicta dolorum deserunt hic.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Qui adipisci libero dolorem et quo consequuntur.\",\"task_id\":8,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(331,'created','comments','App\\Models\\Comment',14,'comments','Dicta dolorum deserunt hic.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"At nihil fugiat tempora quos quo.\",\"task_id\":8,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(332,'created','comments','App\\Models\\Comment',15,'comments','Dicta dolorum deserunt hic.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Quod autem vel quibusdam ut.\",\"task_id\":8,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(333,'created','comments','App\\Models\\Comment',16,'comments','Quae dolores ipsa placeat fuga.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Praesentium architecto aliquid natus doloremque quia ipsa at.\",\"task_id\":9,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(334,'created','comments','App\\Models\\Comment',17,'comments','Deserunt iusto rem maxime.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Placeat et iusto est numquam ut omnis consequatur alias.\",\"task_id\":11,\"user_id\":3}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(335,'created','comments','App\\Models\\Comment',18,'comments','Deserunt iusto rem maxime.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Ipsa in est nostrum delectus.\",\"task_id\":11,\"user_id\":3}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(336,'created','comments','App\\Models\\Comment',19,'comments','Autem sequi accusantium ut voluptates.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Accusantium eaque quos enim sapiente asperiores.\",\"task_id\":16,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(337,'created','comments','App\\Models\\Comment',20,'comments','Incidunt provident molestiae et dolores.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Sint adipisci hic nemo iste mollitia reiciendis est ut.\",\"task_id\":17,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(338,'created','comments','App\\Models\\Comment',21,'comments','Non enim ut blanditiis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Modi totam quia illum ea minus est itaque.\",\"task_id\":18,\"user_id\":1}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(339,'created','comments','App\\Models\\Comment',22,'comments','Non enim ut blanditiis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Dolorem accusantium unde eligendi modi corrupti id a tempora.\",\"task_id\":18,\"user_id\":1}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(340,'created','comments','App\\Models\\Comment',23,'comments','Non enim ut blanditiis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Non exercitationem quis quibusdam inventore ea sed.\",\"task_id\":18,\"user_id\":1}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(341,'created','comments','App\\Models\\Comment',24,'comments','Non enim ut blanditiis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"A dicta occaecati modi similique est maxime odio non.\",\"task_id\":18,\"user_id\":1}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(342,'created','comments','App\\Models\\Comment',25,'comments','Eius dignissimos accusantium ut laboriosam et.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Ad et quisquam nihil quaerat iure vel harum.\",\"task_id\":20,\"user_id\":3}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(343,'created','comments','App\\Models\\Comment',26,'comments','Eius dignissimos accusantium ut laboriosam et.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Odit rem accusantium officiis numquam autem earum hic sit.\",\"task_id\":20,\"user_id\":3}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(344,'created','comments','App\\Models\\Comment',27,'comments','Eius dignissimos accusantium ut laboriosam et.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Est possimus aut consectetur culpa nobis temporibus.\",\"task_id\":20,\"user_id\":3}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(345,'created','comments','App\\Models\\Comment',28,'comments','Eius dignissimos accusantium ut laboriosam et.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Similique incidunt animi repellendus.\",\"task_id\":20,\"user_id\":3}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(346,'created','comments','App\\Models\\Comment',29,'comments','Eius dignissimos accusantium ut laboriosam et.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Facere delectus quo id odit unde qui.\",\"task_id\":20,\"user_id\":3}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(347,'created','comments','App\\Models\\Comment',30,'comments','Quis temporibus et odio.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Iure et nostrum consequatur similique.\",\"task_id\":21,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(348,'created','comments','App\\Models\\Comment',31,'comments','Quis temporibus et odio.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Impedit necessitatibus sed nostrum a.\",\"task_id\":21,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(349,'created','comments','App\\Models\\Comment',32,'comments','Quis temporibus et odio.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Cumque accusantium ut beatae enim sapiente id.\",\"task_id\":21,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(350,'created','comments','App\\Models\\Comment',33,'comments','Quis temporibus et odio.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Tempora voluptas quia non veniam voluptatem sed dolor.\",\"task_id\":21,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(351,'created','comments','App\\Models\\Comment',34,'comments','Fugiat voluptatem consequuntur.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Voluptas qui et id molestias enim earum eum.\",\"task_id\":22,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(352,'created','comments','App\\Models\\Comment',35,'comments','Fugiat voluptatem consequuntur.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Ipsa ut facilis aut et vel atque et.\",\"task_id\":22,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(353,'created','comments','App\\Models\\Comment',36,'comments','Fugiat voluptatem consequuntur.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Itaque saepe sint laudantium quia.\",\"task_id\":22,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(354,'created','comments','App\\Models\\Comment',37,'comments','Fugiat voluptatem consequuntur.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Repellendus hic suscipit tempora dolores.\",\"task_id\":22,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(355,'created','comments','App\\Models\\Comment',38,'comments','Fugiat voluptatem consequuntur.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Autem libero est quia omnis.\",\"task_id\":22,\"user_id\":2}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(356,'created','subtasks','App\\Models\\Subtask',1,'subtasks','Sint necessitatibus fugiat.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":3,\"title\":\"Sint necessitatibus fugiat.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":true,\"task_id\":1}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(357,'created','subtasks','App\\Models\\Subtask',2,'subtasks','Est sed cupiditate numquam.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":3,\"title\":\"Est sed cupiditate numquam.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":true,\"task_id\":1}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(358,'created','subtasks','App\\Models\\Subtask',3,'subtasks','Sit et aut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":3,\"title\":\"Sit et aut.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":false,\"task_id\":1}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(359,'created','subtasks','App\\Models\\Subtask',4,'subtasks','Quo veniam sit necessitatibus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":1,\"title\":\"Quo veniam sit necessitatibus.\",\"category\":\"General\",\"priority\":\"medium\",\"is_completed\":true,\"task_id\":3}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(360,'created','subtasks','App\\Models\\Subtask',5,'subtasks','In voluptate eius nisi commodi.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":1,\"title\":\"In voluptate eius nisi commodi.\",\"category\":\"General\",\"priority\":\"medium\",\"is_completed\":true,\"task_id\":3}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(361,'created','subtasks','App\\Models\\Subtask',6,'subtasks','Impedit sed mollitia.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":1,\"title\":\"Impedit sed mollitia.\",\"category\":\"General\",\"priority\":\"medium\",\"is_completed\":false,\"task_id\":3}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(362,'created','subtasks','App\\Models\\Subtask',7,'subtasks','Quia ut id dolor.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":4,\"title\":\"Quia ut id dolor.\",\"category\":\"General\",\"priority\":\"medium\",\"is_completed\":true,\"task_id\":4}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(363,'created','subtasks','App\\Models\\Subtask',8,'subtasks','Possimus alias ullam inventore.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":2,\"title\":\"Possimus alias ullam inventore.\",\"category\":\"General\",\"priority\":\"low\",\"is_completed\":false,\"task_id\":5}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(364,'created','subtasks','App\\Models\\Subtask',9,'subtasks','Eum soluta.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":4,\"title\":\"Eum soluta.\",\"category\":\"General\",\"priority\":\"low\",\"is_completed\":false,\"task_id\":9}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(365,'created','subtasks','App\\Models\\Subtask',10,'subtasks','Amet autem veniam.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":4,\"title\":\"Amet autem veniam.\",\"category\":\"General\",\"priority\":\"low\",\"is_completed\":false,\"task_id\":9}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(366,'created','subtasks','App\\Models\\Subtask',11,'subtasks','Veritatis sapiente.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":4,\"title\":\"Veritatis sapiente.\",\"category\":\"General\",\"priority\":\"low\",\"is_completed\":false,\"task_id\":9}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(367,'created','subtasks','App\\Models\\Subtask',12,'subtasks','Ex magnam consequatur id.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":4,\"title\":\"Ex magnam consequatur id.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":true,\"task_id\":11}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(368,'created','subtasks','App\\Models\\Subtask',13,'subtasks','Tempora dolorem voluptatibus eos libero.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":4,\"title\":\"Tempora dolorem voluptatibus eos libero.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":false,\"task_id\":11}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(369,'created','subtasks','App\\Models\\Subtask',14,'subtasks','Ea sed dignissimos.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":4,\"title\":\"Ea sed dignissimos.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":false,\"task_id\":11}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(370,'created','subtasks','App\\Models\\Subtask',15,'subtasks','Voluptatum saepe impedit.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":4,\"title\":\"Voluptatum saepe impedit.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":false,\"task_id\":11}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(371,'created','subtasks','App\\Models\\Subtask',16,'subtasks','Magnam ea rerum.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":1,\"title\":\"Magnam ea rerum.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":false,\"task_id\":14}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(372,'created','subtasks','App\\Models\\Subtask',17,'subtasks','Officiis natus quaerat.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":1,\"title\":\"Officiis natus quaerat.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":false,\"task_id\":14}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(373,'created','subtasks','App\\Models\\Subtask',18,'subtasks','Accusamus voluptatum quidem omnis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":1,\"title\":\"Accusamus voluptatum quidem omnis.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":false,\"task_id\":14}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(374,'created','subtasks','App\\Models\\Subtask',19,'subtasks','Tempora ipsam tenetur.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":4,\"title\":\"Tempora ipsam tenetur.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":true,\"task_id\":15}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(375,'created','subtasks','App\\Models\\Subtask',20,'subtasks','Doloremque hic at architecto.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":4,\"title\":\"Doloremque hic at architecto.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":true,\"task_id\":15}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(376,'created','subtasks','App\\Models\\Subtask',21,'subtasks','Iure neque tempore quas.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":2,\"title\":\"Iure neque tempore quas.\",\"category\":\"General\",\"priority\":\"low\",\"is_completed\":false,\"task_id\":17}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(377,'created','subtasks','App\\Models\\Subtask',22,'subtasks','Maiores aperiam optio.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":2,\"title\":\"Maiores aperiam optio.\",\"category\":\"General\",\"priority\":\"low\",\"is_completed\":true,\"task_id\":17}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(378,'created','subtasks','App\\Models\\Subtask',23,'subtasks','Molestiae ipsam ab.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":2,\"title\":\"Molestiae ipsam ab.\",\"category\":\"General\",\"priority\":\"low\",\"is_completed\":false,\"task_id\":17}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(379,'created','subtasks','App\\Models\\Subtask',24,'subtasks','Quas provident dolore.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":3,\"title\":\"Quas provident dolore.\",\"category\":\"General\",\"priority\":\"medium\",\"is_completed\":false,\"task_id\":18}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(380,'created','subtasks','App\\Models\\Subtask',25,'subtasks','Eum soluta quo eveniet.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":3,\"title\":\"Eum soluta quo eveniet.\",\"category\":\"General\",\"priority\":\"medium\",\"is_completed\":false,\"task_id\":18}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(381,'created','subtasks','App\\Models\\Subtask',26,'subtasks','Est qui assumenda.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":1,\"title\":\"Est qui assumenda.\",\"category\":\"General\",\"priority\":\"medium\",\"is_completed\":false,\"task_id\":23}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(382,'created','subtasks','App\\Models\\Subtask',27,'subtasks','Dolorum sed ut est.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":1,\"title\":\"Dolorum sed ut est.\",\"category\":\"General\",\"priority\":\"medium\",\"is_completed\":false,\"task_id\":23}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(383,'created','subtasks','App\\Models\\Subtask',28,'subtasks','Assumenda quo sed ut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":1,\"title\":\"Assumenda quo sed ut.\",\"category\":\"General\",\"priority\":\"medium\",\"is_completed\":true,\"task_id\":23}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:11','2026-09-28 00:09:11'),(384,'created','users','App\\Models\\User',5,'users','Ilene Pollich',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Ilene Pollich\",\"email\":\"crooks.amiya@example.org\",\"email_verified_at\":\"2026-09-28 04:39:11\",\"acc_status\":\"active\"}','[\"name\",\"email\",\"email_verified_at\",\"acc_status\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(385,'created','users','App\\Models\\User',6,'users','Christ Klein',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Christ Klein\",\"email\":\"trinity.schuster@example.com\",\"email_verified_at\":\"2026-09-28 04:39:12\",\"acc_status\":\"active\"}','[\"name\",\"email\",\"email_verified_at\",\"acc_status\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(386,'created','users','App\\Models\\User',7,'users','Holly Smitham',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Holly Smitham\",\"email\":\"pouros.bennett@example.org\",\"email_verified_at\":\"2026-09-28 04:39:12\",\"acc_status\":\"active\"}','[\"name\",\"email\",\"email_verified_at\",\"acc_status\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(387,'created','users','App\\Models\\User',8,'users','Elfrieda Maggio IV',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Elfrieda Maggio IV\",\"email\":\"rleuschke@example.com\",\"email_verified_at\":\"2026-09-28 04:39:12\",\"acc_status\":\"active\"}','[\"name\",\"email\",\"email_verified_at\",\"acc_status\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(388,'created','users','App\\Models\\User',9,'users','Horace Jakubowski',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Horace Jakubowski\",\"email\":\"camilla.goldner@example.com\",\"email_verified_at\":\"2026-09-28 04:39:12\",\"acc_status\":\"active\"}','[\"name\",\"email\",\"email_verified_at\",\"acc_status\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(389,'created','tags','App\\Models\\Tag',9,'tags','Kanban Tag 1',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Kanban Tag 1\",\"color\":\"warning\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(390,'created','tags','App\\Models\\Tag',10,'tags','Kanban Tag 2',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Kanban Tag 2\",\"color\":\"error\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(391,'created','tags','App\\Models\\Tag',11,'tags','Kanban Tag 3',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Kanban Tag 3\",\"color\":\"warning\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(392,'created','tags','App\\Models\\Tag',12,'tags','Kanban Tag 4',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Kanban Tag 4\",\"color\":\"primary\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(393,'created','tags','App\\Models\\Tag',13,'tags','Kanban Tag 5',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Kanban Tag 5\",\"color\":\"success\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(394,'created','tags','App\\Models\\Tag',14,'tags','Kanban Tag 6',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Kanban Tag 6\",\"color\":\"primary\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(395,'created','tags','App\\Models\\Tag',15,'tags','Kanban Tag 7',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Kanban Tag 7\",\"color\":\"info\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(396,'created','tags','App\\Models\\Tag',16,'tags','Kanban Tag 8',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Kanban Tag 8\",\"color\":\"primary\"}','[\"name\",\"color\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(397,'created','boards','App\\Models\\Board',6,'boards','To Do',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"To Do\",\"order\":0}','[\"title\",\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(398,'created','boards','App\\Models\\Board',7,'boards','In Progress',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"In Progress\",\"order\":1}','[\"title\",\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(399,'created','boards','App\\Models\\Board',8,'boards','In Review',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"In Review\",\"order\":2}','[\"title\",\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(400,'created','boards','App\\Models\\Board',9,'boards','Done',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Done\",\"order\":3}','[\"title\",\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(401,'created','tasks','App\\Models\\Task',24,'tasks','Error voluptas in voluptate.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Error voluptas in voluptate.\",\"description\":\"Corrupti dolores laborum voluptatibus labore dolorem molestias similique. Sapiente minus ipsa corporis facilis. Placeat ipsam maiores sed ut tenetur.\",\"priority\":\"urgent\",\"progress\":19,\"start_date\":\"2026-09-23 00:00:00\",\"due_date\":\"2026-10-04 00:00:00\",\"order\":0,\"board_id\":6}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(402,'created','tasks','App\\Models\\Task',25,'tasks','Cupiditate doloremque velit voluptatem dolores.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Cupiditate doloremque velit voluptatem dolores.\",\"description\":\"Eligendi qui dolorem voluptatibus laudantium. Non nam voluptas voluptates recusandae. Id maxime sit aut sit autem.\",\"priority\":\"high\",\"progress\":10,\"start_date\":\"2026-09-22 00:00:00\",\"due_date\":\"2026-10-06 00:00:00\",\"order\":0,\"board_id\":6}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(403,'created','tasks','App\\Models\\Task',26,'tasks','Est doloremque ullam quibusdam eum.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Est doloremque ullam quibusdam eum.\",\"description\":\"Illum laboriosam et nulla veniam. Voluptatibus assumenda enim eius omnis dignissimos dicta ipsum. Sit minus voluptatem dicta eum ipsa autem.\",\"priority\":\"low\",\"progress\":42,\"start_date\":\"2026-09-26 00:00:00\",\"due_date\":\"2026-10-02 00:00:00\",\"order\":0,\"board_id\":6}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(404,'created','tasks','App\\Models\\Task',27,'tasks','Sapiente est reprehenderit nihil.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Sapiente est reprehenderit nihil.\",\"description\":\"Dolor non aut sed similique repudiandae voluptate. Omnis doloribus exercitationem recusandae voluptas tempora atque. Voluptatibus architecto impedit in debitis sint dolor. Tempora qui blanditiis qui atque quas. Ut quia doloribus cumque dolores veniam.\",\"priority\":\"urgent\",\"progress\":94,\"start_date\":\"2026-09-27 00:00:00\",\"due_date\":\"2026-10-01 00:00:00\",\"order\":0,\"board_id\":6}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(405,'updated','tasks','App\\Models\\Task',25,'tasks','Cupiditate doloremque velit voluptatem dolores.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":1}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(406,'created','comments','App\\Models\\Comment',39,'comments','Cupiditate doloremque velit voluptatem dolores.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Ipsam nobis aspernatur delectus quia enim amet.\",\"task_id\":25,\"user_id\":5}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(407,'updated','tasks','App\\Models\\Task',26,'tasks','Est doloremque ullam quibusdam eum.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":2}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(408,'updated','tasks','App\\Models\\Task',27,'tasks','Sapiente est reprehenderit nihil.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":3}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(409,'created','comments','App\\Models\\Comment',40,'comments','Sapiente est reprehenderit nihil.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Velit et architecto accusamus voluptas reiciendis in nostrum eum.\",\"task_id\":27,\"user_id\":5}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(410,'created','comments','App\\Models\\Comment',41,'comments','Sapiente est reprehenderit nihil.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Et non eligendi quidem deleniti sunt.\",\"task_id\":27,\"user_id\":5}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(411,'created','comments','App\\Models\\Comment',42,'comments','Sapiente est reprehenderit nihil.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Provident aliquid voluptas numquam repudiandae earum perferendis.\",\"task_id\":27,\"user_id\":5}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(412,'created','comments','App\\Models\\Comment',43,'comments','Sapiente est reprehenderit nihil.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Expedita rerum voluptatem eum aliquam perferendis.\",\"task_id\":27,\"user_id\":5}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(413,'created','subtasks','App\\Models\\Subtask',29,'subtasks','Deleniti mollitia assumenda.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":5,\"title\":\"Deleniti mollitia assumenda.\",\"category\":\"Planning\",\"priority\":\"high\",\"is_completed\":false,\"task_id\":27}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(414,'created','subtasks','App\\Models\\Subtask',30,'subtasks','Minus ut eius sed.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":5,\"title\":\"Minus ut eius sed.\",\"category\":\"Execution\",\"priority\":\"urgent\",\"is_completed\":false,\"task_id\":27}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(415,'created','tasks','App\\Models\\Task',28,'tasks','Quidem non est.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Quidem non est.\",\"description\":\"Iusto sapiente molestias et officia. Voluptate quia et est numquam quia nisi inventore. Eius ea id aut vitae nam vitae. Exercitationem inventore voluptatem itaque saepe beatae quo.\",\"priority\":\"medium\",\"progress\":75,\"start_date\":\"2026-09-25 00:00:00\",\"due_date\":\"2026-10-08 00:00:00\",\"order\":0,\"board_id\":7}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(416,'created','tasks','App\\Models\\Task',29,'tasks','Eaque adipisci consequuntur qui nesciunt.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Eaque adipisci consequuntur qui nesciunt.\",\"description\":\"Ducimus animi repellat sit eius exercitationem tempore aut. Esse et voluptatem reprehenderit minima. Rem architecto ipsa quam non. Vel non ut expedita et expedita. Earum saepe molestias occaecati.\",\"priority\":\"medium\",\"progress\":58,\"start_date\":\"2026-09-24 00:00:00\",\"due_date\":\"2026-10-08 00:00:00\",\"order\":0,\"board_id\":7}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(417,'created','tasks','App\\Models\\Task',30,'tasks','Incidunt tenetur aut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Incidunt tenetur aut.\",\"description\":\"Sint porro perferendis blanditiis molestiae corporis consequatur. Expedita enim earum modi ut voluptatem eius eligendi porro. Dolorem natus animi sequi blanditiis excepturi tempore illum.\",\"priority\":\"urgent\",\"progress\":72,\"start_date\":\"2026-09-26 00:00:00\",\"due_date\":\"2026-10-09 00:00:00\",\"order\":0,\"board_id\":7}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(418,'created','tasks','App\\Models\\Task',31,'tasks','Voluptatem non ducimus impedit aut repellendus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Voluptatem non ducimus impedit aut repellendus.\",\"description\":\"Expedita quis provident aliquam et qui minus laboriosam. Quos inventore ea eligendi aspernatur voluptas sit adipisci. Voluptate id quia magni nihil dolorem esse. Nihil vel ea blanditiis distinctio incidunt.\",\"priority\":\"urgent\",\"progress\":49,\"start_date\":\"2026-09-21 00:00:00\",\"due_date\":\"2026-10-12 00:00:00\",\"order\":0,\"board_id\":7}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(419,'created','tasks','App\\Models\\Task',32,'tasks','Illo accusantium possimus repellat possimus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Illo accusantium possimus repellat possimus.\",\"description\":\"Enim et eveniet dolor eum dolorem. Voluptas labore aut quo vitae excepturi velit ut. Occaecati placeat nihil accusantium est nisi velit expedita. Dignissimos aut sint et ea non.\",\"priority\":\"medium\",\"progress\":82,\"start_date\":\"2026-09-24 00:00:00\",\"due_date\":\"2026-10-06 00:00:00\",\"order\":0,\"board_id\":7}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(420,'created','subtasks','App\\Models\\Subtask',31,'subtasks','Animi voluptatem voluptatem rerum.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":5,\"title\":\"Animi voluptatem voluptatem rerum.\",\"category\":\"General\",\"priority\":\"urgent\",\"is_completed\":false,\"task_id\":28}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(421,'created','subtasks','App\\Models\\Subtask',32,'subtasks','Doloribus dolorem.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":5,\"title\":\"Doloribus dolorem.\",\"category\":\"Execution\",\"priority\":\"medium\",\"is_completed\":true,\"task_id\":28}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(422,'updated','tasks','App\\Models\\Task',29,'tasks','Eaque adipisci consequuntur qui nesciunt.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":1}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(423,'created','comments','App\\Models\\Comment',44,'comments','Eaque adipisci consequuntur qui nesciunt.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Qui et laboriosam consectetur fuga et quo ut porro.\",\"task_id\":29,\"user_id\":6}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(424,'created','comments','App\\Models\\Comment',45,'comments','Eaque adipisci consequuntur qui nesciunt.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Alias nostrum et nobis facere qui nihil facilis.\",\"task_id\":29,\"user_id\":6}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(425,'updated','tasks','App\\Models\\Task',30,'tasks','Incidunt tenetur aut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":2}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(426,'created','comments','App\\Models\\Comment',46,'comments','Incidunt tenetur aut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Dicta perferendis ab totam laborum et earum voluptatem.\",\"task_id\":30,\"user_id\":5}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(427,'updated','tasks','App\\Models\\Task',31,'tasks','Voluptatem non ducimus impedit aut repellendus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":3}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(428,'created','comments','App\\Models\\Comment',47,'comments','Voluptatem non ducimus impedit aut repellendus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Sit tempore aut magnam eum temporibus nemo sit molestiae.\",\"task_id\":31,\"user_id\":7}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(429,'created','subtasks','App\\Models\\Subtask',33,'subtasks','Voluptatem blanditiis nemo quod.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":6,\"title\":\"Voluptatem blanditiis nemo quod.\",\"category\":\"Execution\",\"priority\":\"high\",\"is_completed\":true,\"task_id\":31}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(430,'updated','tasks','App\\Models\\Task',32,'tasks','Illo accusantium possimus repellat possimus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":4}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(431,'created','comments','App\\Models\\Comment',48,'comments','Illo accusantium possimus repellat possimus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Nam dicta laudantium eligendi debitis.\",\"task_id\":32,\"user_id\":6}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(432,'created','comments','App\\Models\\Comment',49,'comments','Illo accusantium possimus repellat possimus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Ratione consequatur voluptates quaerat enim libero eos.\",\"task_id\":32,\"user_id\":6}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(433,'created','subtasks','App\\Models\\Subtask',34,'subtasks','Nesciunt molestias ut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":8,\"title\":\"Nesciunt molestias ut.\",\"category\":\"Execution\",\"priority\":\"urgent\",\"is_completed\":false,\"task_id\":32}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(434,'created','tasks','App\\Models\\Task',33,'tasks','Cupiditate sit voluptatum ratione est.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Cupiditate sit voluptatum ratione est.\",\"description\":\"Ipsam alias voluptas quibusdam non modi saepe. Quibusdam et et et laudantium. Fugit accusantium eum tempore amet.\",\"priority\":\"high\",\"progress\":49,\"start_date\":\"2026-09-24 00:00:00\",\"due_date\":\"2026-10-03 00:00:00\",\"order\":0,\"board_id\":8}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(435,'created','tasks','App\\Models\\Task',34,'tasks','Velit error ut doloribus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Velit error ut doloribus.\",\"description\":\"Sapiente molestiae quasi placeat omnis facere ea. Hic sit voluptates deleniti vel magnam dolorum nobis. Ut ex facilis blanditiis sed autem. Assumenda vitae vero sed et.\",\"priority\":\"low\",\"progress\":71,\"start_date\":\"2026-09-23 00:00:00\",\"due_date\":\"2026-10-11 00:00:00\",\"order\":0,\"board_id\":8}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(436,'created','tasks','App\\Models\\Task',35,'tasks','Neque doloribus ut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Neque doloribus ut.\",\"description\":\"Rerum et optio iusto sunt omnis quasi praesentium. Repellendus inventore dicta eaque eveniet. Unde laudantium aliquid incidunt corrupti aut. Et at sapiente explicabo omnis et quidem numquam. Quis est ad minima dolorem.\",\"priority\":\"medium\",\"progress\":37,\"start_date\":\"2026-09-23 00:00:00\",\"due_date\":\"2026-10-08 00:00:00\",\"order\":0,\"board_id\":8}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(437,'created','comments','App\\Models\\Comment',50,'comments','Cupiditate sit voluptatum ratione est.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Quisquam praesentium dolores ex nostrum esse.\",\"task_id\":33,\"user_id\":6}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(438,'created','comments','App\\Models\\Comment',51,'comments','Cupiditate sit voluptatum ratione est.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Ratione vel dolore non suscipit.\",\"task_id\":33,\"user_id\":6}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(439,'created','comments','App\\Models\\Comment',52,'comments','Cupiditate sit voluptatum ratione est.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Dolorem enim in quo.\",\"task_id\":33,\"user_id\":6}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(440,'created','comments','App\\Models\\Comment',53,'comments','Cupiditate sit voluptatum ratione est.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Natus ullam suscipit vel natus quia voluptatem placeat.\",\"task_id\":33,\"user_id\":6}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(441,'updated','tasks','App\\Models\\Task',34,'tasks','Velit error ut doloribus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":1}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(442,'created','subtasks','App\\Models\\Subtask',35,'subtasks','Sit ut nihil sit.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":9,\"title\":\"Sit ut nihil sit.\",\"category\":\"Planning\",\"priority\":\"low\",\"is_completed\":false,\"task_id\":34}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(443,'updated','tasks','App\\Models\\Task',35,'tasks','Neque doloribus ut.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":2}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(444,'created','tasks','App\\Models\\Task',36,'tasks','Dolores qui dolor omnis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Dolores qui dolor omnis.\",\"description\":\"Consequuntur et sed maxime vero natus sint et. Distinctio rem veniam qui possimus ex omnis. Rerum ea voluptatibus dignissimos ex dolorem velit porro saepe.\",\"priority\":\"high\",\"progress\":67,\"start_date\":\"2026-09-26 00:00:00\",\"due_date\":\"2026-10-04 00:00:00\",\"order\":0,\"board_id\":9}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(445,'created','tasks','App\\Models\\Task',37,'tasks','Asperiores earum adipisci.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Asperiores earum adipisci.\",\"description\":\"Mollitia sed natus quis quia dolores. Libero harum explicabo aliquid consequatur hic.\",\"priority\":\"high\",\"progress\":73,\"start_date\":\"2026-09-22 00:00:00\",\"due_date\":\"2026-09-28 00:00:00\",\"order\":0,\"board_id\":9}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(446,'created','tasks','App\\Models\\Task',38,'tasks','Natus expedita facilis est quis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Natus expedita facilis est quis.\",\"description\":\"Ut mollitia rerum sit quisquam voluptas. Fugiat earum dolores minima hic labore. Inventore blanditiis est nesciunt sit ipsum voluptate libero qui.\",\"priority\":\"low\",\"progress\":41,\"start_date\":\"2026-09-28 00:00:00\",\"due_date\":\"2026-10-07 00:00:00\",\"order\":0,\"board_id\":9}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(447,'created','tasks','App\\Models\\Task',39,'tasks','Minima commodi quo facilis qui ea.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Minima commodi quo facilis qui ea.\",\"description\":\"Iure accusantium voluptatibus voluptas. Quia sed fugit illum voluptas voluptas ut atque molestias. Eius architecto molestias omnis et asperiores maiores dolorem.\",\"priority\":\"low\",\"progress\":18,\"start_date\":\"2026-09-26 00:00:00\",\"due_date\":\"2026-10-05 00:00:00\",\"order\":0,\"board_id\":9}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(448,'created','tasks','App\\Models\\Task',40,'tasks','Alias ut ullam pariatur ea.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"title\":\"Alias ut ullam pariatur ea.\",\"description\":\"Esse nesciunt atque quibusdam at quo qui. Consectetur optio repellat quam sint quis modi. Nobis vel sint aliquid non omnis similique.\",\"priority\":\"medium\",\"progress\":42,\"start_date\":\"2026-09-26 00:00:00\",\"due_date\":\"2026-10-02 00:00:00\",\"order\":0,\"board_id\":9}','[\"title\",\"description\",\"priority\",\"progress\",\"start_date\",\"due_date\",\"order\",\"board_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(449,'created','subtasks','App\\Models\\Subtask',36,'subtasks','Voluptatem architecto voluptatum temporibus.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":9,\"title\":\"Voluptatem architecto voluptatum temporibus.\",\"category\":\"Execution\",\"priority\":\"urgent\",\"is_completed\":true,\"task_id\":36}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(450,'created','subtasks','App\\Models\\Subtask',37,'subtasks','Iusto cumque veritatis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":9,\"title\":\"Iusto cumque veritatis.\",\"category\":\"Execution\",\"priority\":\"medium\",\"is_completed\":false,\"task_id\":36}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(451,'updated','tasks','App\\Models\\Task',37,'tasks','Asperiores earum adipisci.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":1}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(452,'updated','tasks','App\\Models\\Task',38,'tasks','Natus expedita facilis est quis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":2}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(453,'created','comments','App\\Models\\Comment',54,'comments','Natus expedita facilis est quis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Est tenetur ducimus et ea error sit.\",\"task_id\":38,\"user_id\":6}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(454,'created','comments','App\\Models\\Comment',55,'comments','Natus expedita facilis est quis.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Ipsum aut saepe fugiat aperiam.\",\"task_id\":38,\"user_id\":6}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(455,'created','subtasks','App\\Models\\Subtask',38,'subtasks','Voluptatem vel nesciunt.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"user_id\":8,\"title\":\"Voluptatem vel nesciunt.\",\"category\":\"Execution\",\"priority\":\"urgent\",\"is_completed\":false,\"task_id\":38}','[\"user_id\",\"title\",\"category\",\"priority\",\"is_completed\",\"task_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(456,'updated','tasks','App\\Models\\Task',39,'tasks','Minima commodi quo facilis qui ea.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":3}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(457,'created','comments','App\\Models\\Comment',56,'comments','Minima commodi quo facilis qui ea.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"content\":\"Possimus illum voluptates qui quo sit exercitationem.\",\"task_id\":39,\"user_id\":6}','[\"content\",\"task_id\",\"user_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(458,'updated','tasks','App\\Models\\Task',40,'tasks','Alias ut ullam pariatur ea.',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"order\":0}','{\"order\":4}','[\"order\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:12','2026-09-28 00:09:12'),(459,'created','invoices','App\\Models\\Invoice',1,'invoices','John Doe',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"currency_name\":\"Afghan Afghani\",\"rate\":1,\"currency_symbol\":\"\\u060b\",\"currency_shortname\":\"AFN\",\"discount\":0,\"applicant_id\":1,\"currency_id\":1,\"base_currency_id\":1,\"deptor\":\"John Doe\",\"customer_company\":null,\"customer_contact\":\"555-1234\",\"customer_email\":null,\"customer_address\":null,\"customer_tax_number\":null,\"status\":\"Issued\",\"tax_percent\":0,\"cnv_curr_shrt\":\"AFN\",\"base_curr_shrt\":\"AFN\",\"base_amount\":1500,\"subtotal\":1500,\"amount\":1500,\"notes\":\"Seeded invoice\",\"payment_method\":\"Bank Transfer\",\"payment_instructions\":[]}','[\"currency_name\",\"rate\",\"currency_symbol\",\"currency_shortname\",\"discount\",\"applicant_id\",\"currency_id\",\"base_currency_id\",\"deptor\",\"customer_company\",\"customer_contact\",\"customer_email\",\"customer_address\",\"customer_tax_number\",\"status\",\"tax_percent\",\"cnv_curr_shrt\",\"base_curr_shrt\",\"base_amount\",\"subtotal\",\"amount\",\"notes\",\"payment_method\",\"payment_instructions\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(460,'created','invoices','App\\Models\\Invoice',2,'invoices','Jane Smith',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"currency_name\":\"Afghan Afghani\",\"rate\":1,\"currency_symbol\":\"\\u060b\",\"currency_shortname\":\"AFN\",\"discount\":0,\"applicant_id\":2,\"currency_id\":1,\"base_currency_id\":1,\"deptor\":\"Jane Smith\",\"customer_company\":null,\"customer_contact\":\"555-5678\",\"customer_email\":null,\"customer_address\":null,\"customer_tax_number\":null,\"status\":\"Issued\",\"tax_percent\":0,\"cnv_curr_shrt\":\"AFN\",\"base_curr_shrt\":\"AFN\",\"base_amount\":5000,\"subtotal\":5000,\"amount\":5000,\"notes\":\"Seeded invoice\",\"payment_method\":\"Bank Transfer\",\"payment_instructions\":[]}','[\"currency_name\",\"rate\",\"currency_symbol\",\"currency_shortname\",\"discount\",\"applicant_id\",\"currency_id\",\"base_currency_id\",\"deptor\",\"customer_company\",\"customer_contact\",\"customer_email\",\"customer_address\",\"customer_tax_number\",\"status\",\"tax_percent\",\"cnv_curr_shrt\",\"base_curr_shrt\",\"base_amount\",\"subtotal\",\"amount\",\"notes\",\"payment_method\",\"payment_instructions\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(461,'created','salaries','App\\Models\\Salary',1,'salaries','2026-06',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"employee_id\":1,\"payment_date\":\"2026-06-30 00:00:00\",\"currency_id\":1,\"salary_month\":\"2026-06\",\"amount_paid\":60000,\"bonus\":0,\"worked_days\":30,\"total_days\":30,\"total_amount\":60000,\"currency_code\":\"AFN\",\"currency_name\":\"Afghan Afghani\",\"currency_symbol\":\"\\u060b\",\"currency_decimal_places\":2,\"cnv_curr_shrt\":\"AFN\",\"base_curr_shrt\":\"AFN\",\"rate\":1,\"base_amount\":60000,\"remarks\":\"Partial salary payment\"}','[\"employee_id\",\"payment_date\",\"currency_id\",\"salary_month\",\"amount_paid\",\"bonus\",\"worked_days\",\"total_days\",\"total_amount\",\"currency_code\",\"currency_name\",\"currency_symbol\",\"currency_decimal_places\",\"cnv_curr_shrt\",\"base_curr_shrt\",\"rate\",\"base_amount\",\"remarks\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(462,'created','salaries','App\\Models\\Salary',2,'salaries','2026-06',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"employee_id\":2,\"payment_date\":\"2026-06-30 00:00:00\",\"currency_id\":1,\"salary_month\":\"2026-06\",\"amount_paid\":75000,\"bonus\":0,\"worked_days\":28,\"total_days\":30,\"total_amount\":70000,\"currency_code\":\"AFN\",\"currency_name\":\"Afghan Afghani\",\"currency_symbol\":\"\\u060b\",\"currency_decimal_places\":2,\"cnv_curr_shrt\":\"AFN\",\"base_curr_shrt\":\"AFN\",\"rate\":1,\"base_amount\":70000,\"remarks\":\"2 days unpaid leave deduction applied\"}','[\"employee_id\",\"payment_date\",\"currency_id\",\"salary_month\",\"amount_paid\",\"bonus\",\"worked_days\",\"total_days\",\"total_amount\",\"currency_code\",\"currency_name\",\"currency_symbol\",\"currency_decimal_places\",\"cnv_curr_shrt\",\"base_curr_shrt\",\"rate\",\"base_amount\",\"remarks\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(463,'created','worthy_employees','App\\Models\\WorthyEmployee',1,'worthy_employees','Demo Super Admin',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"employee_id\":1,\"performance_score\":4.9,\"achievement_details\":\"Consistently exceeded Q2 velocity targets by 35% without bug regressions.\",\"suggested_amount\":4000,\"selected_amount\":4000,\"selected_type\":\"Performance\",\"is_granted\":false,\"bonus_month\":\"2026-09-01 00:00:00\"}','[\"employee_id\",\"performance_score\",\"achievement_details\",\"suggested_amount\",\"selected_amount\",\"selected_type\",\"is_granted\",\"bonus_month\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(464,'created','worthy_employees','App\\Models\\WorthyEmployee',2,'worthy_employees','John Doe',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"employee_id\":2,\"performance_score\":4.8,\"achievement_details\":\"Designed and delivered the complete mobile design system 2 weeks early.\",\"suggested_amount\":2500,\"selected_amount\":2500,\"selected_type\":\"Task Completion\",\"is_granted\":true,\"bonus_month\":\"2026-08-01 00:00:00\"}','[\"employee_id\",\"performance_score\",\"achievement_details\",\"suggested_amount\",\"selected_amount\",\"selected_type\",\"is_granted\",\"bonus_month\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(465,'created','worthy_employees','App\\Models\\WorthyEmployee',3,'worthy_employees','Jane Smith',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"employee_id\":3,\"performance_score\":4.6,\"achievement_details\":\"Received outstanding feedback and secured a 3-year contract extension.\",\"suggested_amount\":1500,\"selected_amount\":2000,\"selected_type\":\"Manager Recognition\",\"is_granted\":true,\"bonus_month\":\"2026-07-01 00:00:00\"}','[\"employee_id\",\"performance_score\",\"achievement_details\",\"suggested_amount\",\"selected_amount\",\"selected_type\",\"is_granted\",\"bonus_month\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(466,'created','worthy_employees','App\\Models\\WorthyEmployee',4,'worthy_employees','David Johnson',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"employee_id\":4,\"performance_score\":4.7,\"achievement_details\":\"Reached 5-year tenure milestone with zero critical downtime incidents.\",\"suggested_amount\":3000,\"selected_amount\":3000,\"selected_type\":\"Work Anniversary\",\"is_granted\":false,\"bonus_month\":\"2026-09-01 00:00:00\"}','[\"employee_id\",\"performance_score\",\"achievement_details\",\"suggested_amount\",\"selected_amount\",\"selected_type\",\"is_granted\",\"bonus_month\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(467,'created','marketers','App\\Models\\Marketer',1,'marketers','Sarah Johnson',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Sarah Johnson\",\"email\":\"sarah.johnson@example.com\",\"phone\":\"+93 700 000 001\",\"status\":\"active\",\"notes\":\"Demo CRM marketer\"}','[\"name\",\"email\",\"phone\",\"status\",\"notes\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(468,'created','marketers','App\\Models\\Marketer',2,'marketers','Michael Brown',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Michael Brown\",\"email\":\"michael.brown@example.com\",\"phone\":\"+93 700 000 002\",\"status\":\"active\",\"notes\":\"Demo CRM marketer\"}','[\"name\",\"email\",\"phone\",\"status\",\"notes\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(469,'created','marketers','App\\Models\\Marketer',3,'marketers','David Wilson',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"David Wilson\",\"email\":\"david.wilson@example.com\",\"phone\":\"+93 700 000 003\",\"status\":\"active\",\"notes\":\"Demo CRM marketer\"}','[\"name\",\"email\",\"phone\",\"status\",\"notes\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(470,'created','leads','App\\Models\\Lead',1,'leads','Amina Rahimi',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"email\":\"amina.rahimi@example.com\",\"name\":\"Amina Rahimi\",\"company\":\"Rahimi Family\",\"contact\":\"+93 701 111 111\",\"service_interest\":\"Student Visa Application Support\",\"stage\":\"qualified\",\"probability\":65,\"estimated_value\":20000,\"notes\":\"Interested in a master\'s programme in Canada.\",\"owner_id\":1,\"marketer_id\":1}','[\"email\",\"name\",\"company\",\"contact\",\"service_interest\",\"stage\",\"probability\",\"estimated_value\",\"notes\",\"owner_id\",\"marketer_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(471,'created','leads','App\\Models\\Lead',2,'leads','Omid Noori',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"email\":\"omid.noori@example.com\",\"name\":\"Omid Noori\",\"company\":\"Noori Education Group\",\"contact\":\"+93 702 222 222\",\"service_interest\":\"University Application Support\",\"stage\":\"contacted\",\"probability\":35,\"estimated_value\":15000,\"notes\":\"Follow up after the next intake information session.\",\"owner_id\":1,\"marketer_id\":2}','[\"email\",\"name\",\"company\",\"contact\",\"service_interest\",\"stage\",\"probability\",\"estimated_value\",\"notes\",\"owner_id\",\"marketer_id\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(472,'created','countries','App\\Models\\Country',1,'countries','Canada',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"code\":\"CA\",\"name\":\"Canada\",\"is_active\":true}','[\"code\",\"name\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(473,'created','countries','App\\Models\\Country',2,'countries','Germany',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"code\":\"DE\",\"name\":\"Germany\",\"is_active\":true}','[\"code\",\"name\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(474,'created','countries','App\\Models\\Country',3,'countries','United Kingdom',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"code\":\"GB\",\"name\":\"United Kingdom\",\"is_active\":true}','[\"code\",\"name\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(475,'created','countries','App\\Models\\Country',4,'countries','Turkey',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"code\":\"TR\",\"name\":\"Turkey\",\"is_active\":true}','[\"code\",\"name\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(476,'created','education_levels','App\\Models\\EducationLevel',1,'education_levels','Bachelor',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Bachelor\",\"sort_order\":10,\"is_active\":true}','[\"name\",\"sort_order\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(477,'created','education_levels','App\\Models\\EducationLevel',2,'education_levels','Master',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Master\",\"sort_order\":20,\"is_active\":true}','[\"name\",\"sort_order\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(478,'created','education_levels','App\\Models\\EducationLevel',3,'education_levels','PhD',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"PhD\",\"sort_order\":30,\"is_active\":true}','[\"name\",\"sort_order\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:13','2026-09-28 00:09:13'),(479,'created','languages','App\\Models\\Language',1,'languages','English',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"code\":\"en\",\"name\":\"English\",\"is_active\":true}','[\"code\",\"name\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(480,'created','languages','App\\Models\\Language',2,'languages','German',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"code\":\"de\",\"name\":\"German\",\"is_active\":true}','[\"code\",\"name\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(481,'created','languages','App\\Models\\Language',3,'languages','French',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"code\":\"fr\",\"name\":\"French\",\"is_active\":true}','[\"code\",\"name\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(482,'created','package_types','App\\Models\\Package',1,'packages','University Application Package',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"University Application Package\",\"is_active\":true,\"currency_id\":1,\"service_manifest\":[2,3]}','[\"name\",\"is_active\",\"currency_id\",\"service_manifest\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(483,'created','package_types','App\\Models\\Package',2,'packages','Scholarship Support Package',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Scholarship Support Package\",\"is_active\":true,\"currency_id\":1,\"service_manifest\":[2,4]}','[\"name\",\"is_active\",\"currency_id\",\"service_manifest\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(484,'created','package_types','App\\Models\\Package',3,'packages','Test Preparation Package',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Test Preparation Package\",\"is_active\":true,\"currency_id\":1,\"service_manifest\":[5,8]}','[\"name\",\"is_active\",\"currency_id\",\"service_manifest\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(485,'created','semesters','App\\Models\\Semester',1,'semesters','Fall 2027',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"code\":\"FALL-2027\",\"name\":\"Fall 2027\",\"sort_order\":10,\"is_active\":true}','[\"code\",\"name\",\"sort_order\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(486,'created','universities','App\\Models\\University',1,'universities','University of Toronto',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"University of Toronto\",\"country_id\":1,\"city\":\"Toronto\",\"is_active\":true}','[\"name\",\"country_id\",\"city\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(487,'created','universities','App\\Models\\University',2,'universities','Technical University of Munich',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"Technical University of Munich\",\"country_id\":2,\"city\":\"Munich\",\"is_active\":true}','[\"name\",\"country_id\",\"city\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(488,'created','universities','App\\Models\\University',3,'universities','University of Manchester',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"name\":\"University of Manchester\",\"country_id\":3,\"city\":\"Manchester\",\"is_active\":true}','[\"name\",\"country_id\",\"city\",\"is_active\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(489,'updated','applicants','App\\Models\\Applicant',1,'applicants','John Doe',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'{\"responsible_employee_id\":null,\"country_id\":null,\"university_id\":null,\"education_level_id\":null,\"language_id\":null,\"semester_id\":null,\"current_stage_id\":null,\"application_date\":null,\"notes\":null}','{\"responsible_employee_id\":1,\"country_id\":1,\"university_id\":1,\"education_level_id\":2,\"language_id\":1,\"semester_id\":1,\"current_stage_id\":2,\"application_date\":\"2026-09-18\",\"notes\":\"Demo educational visa case.\"}','[\"responsible_employee_id\",\"country_id\",\"university_id\",\"education_level_id\",\"language_id\",\"semester_id\",\"current_stage_id\",\"application_date\",\"notes\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(490,'created','applicant_universities','App\\Models\\ApplicantUniversity',1,'applicant_universities','John Doe',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"status\":\"pending\",\"applicant_id\":1,\"university_id\":1,\"intake\":\"Fall 2027\",\"semester_id\":1,\"notes\":\"Primary university choice.\"}','[\"status\",\"applicant_id\",\"university_id\",\"intake\",\"semester_id\",\"notes\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14'),(491,'created','applicant_universities','App\\Models\\ApplicantUniversity',2,'applicant_universities','John Doe',NULL,NULL,NULL,'pending',NULL,NULL,NULL,'[]','{\"status\":\"applied\",\"applicant_id\":1,\"university_id\":2,\"intake\":\"Fall 2027\",\"semester_id\":1,\"notes\":\"Alternative university application.\"}','[\"status\",\"applicant_id\",\"university_id\",\"intake\",\"semester_id\",\"notes\"]','http://localhost','GET','127.0.0.1','2026-09-28 00:09:14','2026-09-28 00:09:14');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_resource_settings`
--

DROP TABLE IF EXISTS `audit_resource_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_resource_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resource_key` varchar(80) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `audit_resource_settings_resource_key_unique` (`resource_key`),
  KEY `audit_resource_settings_updated_by_foreign` (`updated_by`),
  KEY `audit_resource_settings_enabled_resource_key_index` (`enabled`,`resource_key`),
  CONSTRAINT `audit_resource_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_resource_settings`
--

LOCK TABLES `audit_resource_settings` WRITE;
/*!40000 ALTER TABLE `audit_resource_settings` DISABLE KEYS */;
INSERT INTO `audit_resource_settings` VALUES (1,'applicants',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(2,'applicant_attachments',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(3,'applicant_stages',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(4,'applicant_universities',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(5,'case_wins',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(6,'attendances',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(7,'boards',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(8,'comments',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(9,'companies',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(10,'commission_accruals',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(11,'commission_settlements',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(12,'contract_policies',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(13,'contract_templates',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(14,'contract_template_versions',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(15,'contract_types',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(16,'contracts',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(17,'contract_services',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(18,'currencies',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(19,'countries',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(20,'customer_packages',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(21,'daily_reports',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(22,'education_levels',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(23,'educational_bonus_sources',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(24,'employees',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(25,'expenses',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(26,'incomes',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(27,'invoices',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(28,'languages',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(29,'leads',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(30,'marketers',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(31,'offices',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(32,'outsourcing_accruals',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(33,'outsourcing_settlements',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(34,'leaves',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(35,'payment_details',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(36,'packages',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(37,'pending_transactions',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(38,'permissions',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(39,'positions',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(40,'salaries',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(41,'salary_payments',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(42,'services',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(43,'semesters',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(44,'settings',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(45,'assets',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(46,'subtasks',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(47,'tags',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(48,'task_attachments',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(49,'tasks',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(50,'users',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(51,'universities',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(52,'visitors',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34'),(53,'worthy_employees',1,NULL,'2026-09-28 00:01:34','2026-09-28 00:01:34');
/*!40000 ALTER TABLE `audit_resource_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `automation_runs`
--

DROP TABLE IF EXISTS `automation_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `automation_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job` varchar(80) NOT NULL,
  `period_key` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'running',
  `started_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `finished_at` timestamp NULL DEFAULT NULL,
  `attempt` smallint(5) unsigned NOT NULL DEFAULT 1,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result`)),
  `error_message` text DEFAULT NULL,
  `error_trace` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `automation_runs_job_period_key_unique` (`job`,`period_key`),
  KEY `automation_runs_status_started_at_index` (`status`,`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `automation_runs`
--

LOCK TABLES `automation_runs` WRITE;
/*!40000 ALTER TABLE `automation_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `automation_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `board_user`
--

DROP TABLE IF EXISTS `board_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `board_user` (
  `board_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`board_id`,`user_id`),
  KEY `board_user_user_id_foreign` (`user_id`),
  CONSTRAINT `board_user_board_id_foreign` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `board_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `board_user`
--

LOCK TABLES `board_user` WRITE;
/*!40000 ALTER TABLE `board_user` DISABLE KEYS */;
INSERT INTO `board_user` VALUES (1,1),(1,2),(1,3),(1,4),(2,1),(2,2),(2,3),(2,4),(3,1),(3,2),(3,3),(4,1),(4,2),(4,3),(4,4),(5,1),(5,2),(5,3),(5,4),(6,5),(6,7),(6,8),(6,9),(7,5),(7,6),(7,8),(7,9),(8,5),(8,6),(8,7),(8,9),(9,5),(9,6),(9,7),(9,8),(9,9);
/*!40000 ALTER TABLE `board_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `boards`
--

DROP TABLE IF EXISTS `boards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `boards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `boards_project_id_order_index` (`project_id`,`order`),
  CONSTRAINT `boards_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `boards`
--

LOCK TABLES `boards` WRITE;
/*!40000 ALTER TABLE `boards` DISABLE KEYS */;
INSERT INTO `boards` VALUES (1,NULL,'Backlog',0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(2,NULL,'To Do',1,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(3,NULL,'In Progress',2,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(4,NULL,'Review',3,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(5,NULL,'Done',4,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(6,NULL,'To Do',0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(7,NULL,'In Progress',1,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(8,NULL,'In Review',2,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(9,NULL,'Done',3,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL);
/*!40000 ALTER TABLE `boards` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bonus_rule_sets`
--

DROP TABLE IF EXISTS `bonus_rule_sets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bonus_rule_sets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT 'Default task bonus rules',
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`rules`)),
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bonus_rule_sets_updated_by_foreign` (`updated_by`),
  CONSTRAINT `bonus_rule_sets_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bonus_rule_sets`
--

LOCK TABLES `bonus_rule_sets` WRITE;
/*!40000 ALTER TABLE `bonus_rule_sets` DISABLE KEYS */;
INSERT INTO `bonus_rule_sets` VALUES (1,'Default task bonus rules',1,1,'{\"completed_task_points\":10,\"priority_weights\":{\"low\":0.75,\"medium\":1,\"high\":1.5,\"urgent\":2},\"before_deadline_points\":2,\"after_deadline_points\":-2,\"reopened_penalty\":-3,\"rejected_penalty\":-5,\"default_quality_score\":3,\"quality_multiplier_enabled\":true,\"minimum_participation_percent\":10,\"minimum_completed_tasks\":1,\"monthly_score_threshold\":10,\"amount_per_point\":10,\"maximum_monthly_bonus\":1000,\"excluded_employee_ids\":[],\"excluded_departments\":[]}',NULL,'2026-09-28 00:01:33','2026-09-28 00:01:33');
/*!40000 ALTER TABLE `bonus_rule_sets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laravel-cache-truss:schema:mysql','a:5:{s:10:\"connection\";s:5:\"mysql\";s:8:\"fallback\";b:0;s:18:\"skipped_migrations\";a:0:{}s:6:\"tables\";a:103:{i:0;a:5:{s:4:\"name\";s:29:\"account_deactivation_requests\";s:7:\"columns\";a:17:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:15:\"pending_user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:6:\"reason\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'pending\'\";}i:6;a:4:{s:4:\"name\";s:14:\"requester_rank\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:15:\"requester_roles\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:8;a:4:{s:4:\"name\";s:12:\"requested_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:0;s:7:\"default\";s:19:\"current_timestamp()\";}i:9;a:4:{s:4:\"name\";s:11:\"reviewer_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:13:\"reviewer_rank\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:11:\"reviewed_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:14:\"decision_notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:12:\"cancelled_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:14:\"audit_metadata\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:5:{i:0;a:3:{s:4:\"name\";s:49:\"account_deactivation_requests_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:52:\"account_deactivation_requests_pending_user_id_unique\";s:7:\"columns\";a:1:{i:0;s:15:\"pending_user_id\";}s:6:\"unique\";b:1;}i:2;a:3:{s:4:\"name\";s:49:\"account_deactivation_requests_reviewer_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"reviewer_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:55:\"account_deactivation_requests_status_requested_at_index\";s:7:\"columns\";a:2:{i:0;s:6:\"status\";i:1;s:12:\"requested_at\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:56:\"account_deactivation_requests_user_id_requested_at_index\";s:7:\"columns\";a:2:{i:0;s:7:\"user_id\";i:1;s:12:\"requested_at\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:4:{i:0;a:6:{s:4:\"name\";s:49:\"account_deactivation_requests_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:53:\"account_deactivation_requests_pending_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:15:\"pending_user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:2;a:6:{s:4:\"name\";s:49:\"account_deactivation_requests_reviewer_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"reviewer_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:45:\"account_deactivation_requests_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:1;a:5:{s:4:\"name\";s:10:\"applicants\";s:7:\"columns\";a:26:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:7:\"contact\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:5:\"email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:7:\"address\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"tax_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:8:\"marketer\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:11:\"marketer_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:4:\"date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:19:\"parent_applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:23:\"responsible_employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"country_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:13:\"university_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:18:\"education_level_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:11:\"language_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:11:\"semester_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:16:\"current_stage_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:16:\"application_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'active\'\";}i:20;a:4:{s:4:\"name\";s:5:\"notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:21;a:4:{s:4:\"name\";s:12:\"profile_data\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:22;a:4:{s:4:\"name\";s:20:\"profile_completed_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:23;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:24;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:25;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:14:{i:0;a:3:{s:4:\"name\";s:29:\"applicants_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:35:\"applicants_current_stage_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"current_stage_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:35:\"applicants_customer_id_status_index\";s:7:\"columns\";a:1:{i:0;s:6:\"status\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:37:\"applicants_education_level_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"education_level_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:30:\"applicants_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:26:\"applicants_marketer_id_idx\";s:7:\"columns\";a:1:{i:0;s:11:\"marketer_id\";}s:6:\"unique\";b:0;}i:6;a:3:{s:4:\"name\";s:19:\"applicants_name_idx\";s:7:\"columns\";a:1:{i:0;s:4:\"name\";}s:6:\"unique\";b:0;}i:7;a:3:{s:4:\"name\";s:26:\"applicants_name_status_idx\";s:7:\"columns\";a:2:{i:0;s:4:\"name\";i:1;s:6:\"status\";}s:6:\"unique\";b:0;}i:8;a:3:{s:4:\"name\";s:34:\"applicants_parent_applicant_id_idx\";s:7:\"columns\";a:1:{i:0;s:19:\"parent_applicant_id\";}s:6:\"unique\";b:0;}i:9;a:3:{s:4:\"name\";s:57:\"applicants_responsible_employee_id_current_stage_id_index\";s:7:\"columns\";a:2:{i:0;s:23:\"responsible_employee_id\";i:1;s:16:\"current_stage_id\";}s:6:\"unique\";b:0;}i:10;a:3:{s:4:\"name\";s:28:\"applicants_semester_id_index\";s:7:\"columns\";a:1:{i:0;s:11:\"semester_id\";}s:6:\"unique\";b:0;}i:11;a:3:{s:4:\"name\";s:23:\"applicants_status_index\";s:7:\"columns\";a:1:{i:0;s:6:\"status\";}s:6:\"unique\";b:0;}i:12;a:3:{s:4:\"name\";s:32:\"applicants_university_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"university_id\";}s:6:\"unique\";b:0;}i:13;a:3:{s:4:\"name\";s:25:\"applicants_user_id_unique\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:10:{i:0;a:6:{s:4:\"name\";s:29:\"applicants_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:16:\"references_table\";s:9:\"countries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:35:\"applicants_current_stage_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"current_stage_id\";}s:16:\"references_table\";s:16:\"applicant_stages\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:37:\"applicants_education_level_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"education_level_id\";}s:16:\"references_table\";s:16:\"education_levels\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:30:\"applicants_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:16:\"references_table\";s:9:\"languages\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:30:\"applicants_marketer_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"marketer_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:5;a:6:{s:4:\"name\";s:38:\"applicants_parent_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"parent_applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:6;a:6:{s:4:\"name\";s:42:\"applicants_responsible_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:23:\"responsible_employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:7;a:6:{s:4:\"name\";s:30:\"applicants_semester_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"semester_id\";}s:16:\"references_table\";s:9:\"semesters\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:8;a:6:{s:4:\"name\";s:32:\"applicants_university_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"university_id\";}s:16:\"references_table\";s:12:\"universities\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:9;a:6:{s:4:\"name\";s:26:\"applicants_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:2;a:5:{s:4:\"name\";s:21:\"applicant_attachments\";s:7:\"columns\";a:13:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"uploaded_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:5:\"label\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:8:\"category\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:4:\"disk\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:7:\"\'local\'\";}i:6;a:4:{s:4:\"name\";s:4:\"path\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:13:\"original_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:8;a:4:{s:4:\"name\";s:9:\"mime_type\";s:4:\"type\";s:12:\"varchar(120)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:4:\"size\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:10;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:46:\"applicant_attachments_applicant_id_label_index\";s:7:\"columns\";a:2:{i:0;s:12:\"applicant_id\";i:1;s:5:\"label\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:41:\"applicant_attachments_uploaded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"uploaded_by\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:42:\"applicant_attachments_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:41:\"applicant_attachments_uploaded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"uploaded_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:3;a:5:{s:4:\"name\";s:16:\"applicant_stages\";s:7:\"columns\";a:12:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:3:\"key\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"country_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:13:\"university_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:10:\"sort_order\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:6;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:7;a:4:{s:4:\"name\";s:11:\"is_terminal\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:8;a:4:{s:4:\"name\";s:17:\"allowed_next_keys\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:27:\"applicant_stages_key_unique\";s:7:\"columns\";a:1:{i:0;s:3:\"key\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:38:\"applicant_stages_university_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"university_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:29:\"applicant_stage_context_index\";s:7:\"columns\";a:3:{i:0;s:10:\"country_id\";i:1;s:13:\"university_id\";i:2;s:9:\"is_active\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:35:\"applicant_stages_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:16:\"references_table\";s:9:\"countries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:38:\"applicant_stages_university_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"university_id\";}s:16:\"references_table\";s:12:\"universities\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:4;a:5:{s:4:\"name\";s:25:\"applicant_stage_histories\";s:7:\"columns\";a:9:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:12:\"old_stage_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:12:\"new_stage_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:10:\"changed_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"changed_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:0;s:7:\"default\";s:19:\"current_timestamp()\";}i:6;a:4:{s:4:\"name\";s:4:\"note\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:4:{i:0;a:3:{s:4:\"name\";s:55:\"applicant_stage_histories_applicant_id_changed_at_index\";s:7:\"columns\";a:2:{i:0;s:12:\"applicant_id\";i:1;s:10:\"changed_at\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:44:\"applicant_stage_histories_changed_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"changed_by\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:46:\"applicant_stage_histories_new_stage_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"new_stage_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:46:\"applicant_stage_histories_old_stage_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"old_stage_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:4:{i:0;a:6:{s:4:\"name\";s:46:\"applicant_stage_histories_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:44:\"applicant_stage_histories_changed_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"changed_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:46:\"applicant_stage_histories_new_stage_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"new_stage_id\";}s:16:\"references_table\";s:16:\"applicant_stages\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:3;a:6:{s:4:\"name\";s:46:\"applicant_stage_histories_old_stage_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"old_stage_id\";}s:16:\"references_table\";s:16:\"applicant_stages\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:5;a:5:{s:4:\"name\";s:22:\"applicant_universities\";s:7:\"columns\";a:10:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:13:\"university_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:11:\"semester_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'pending\'\";}i:5;a:4:{s:4:\"name\";s:6:\"intake\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:5:\"notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:63:\"applicant_universities_applicant_id_university_id_intake_unique\";s:7:\"columns\";a:3:{i:0;s:12:\"applicant_id\";i:1;s:13:\"university_id\";i:2;s:6:\"intake\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:42:\"applicant_universities_semester_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"semester_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:49:\"applicant_universities_university_id_status_index\";s:7:\"columns\";a:2:{i:0;s:13:\"university_id\";i:1;s:6:\"status\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:3:{i:0;a:6:{s:4:\"name\";s:43:\"applicant_universities_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:42:\"applicant_universities_semester_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"semester_id\";}s:16:\"references_table\";s:9:\"semesters\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:44:\"applicant_universities_university_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"university_id\";}s:16:\"references_table\";s:12:\"universities\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:6;a:5:{s:4:\"name\";s:6:\"assets\";s:7:\"columns\";a:11:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:13:\"serial_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:4:\"item\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:8:\"quantity\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:5:\"price\";s:4:\"type\";s:13:\"decimal(14,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:4:\"0.00\";}i:5;a:4:{s:4:\"name\";s:5:\"model\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:9:\"condition\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"\'good\'\";}i:7;a:4:{s:4:\"name\";s:9:\"office_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:8;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:24:\"assets_office_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"office_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:24:\"assets_office_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"office_id\";}s:16:\"references_table\";s:7:\"offices\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:7;a:5:{s:4:\"name\";s:17:\"asset_allocations\";s:7:\"columns\";a:6:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:8:\"asset_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:8:\"quantity\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:45:\"asset_allocations_asset_id_employee_id_unique\";s:7:\"columns\";a:2:{i:0;s:8:\"asset_id\";i:1;s:11:\"employee_id\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:37:\"asset_allocations_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:34:\"asset_allocations_asset_id_foreign\";s:7:\"columns\";a:1:{i:0;s:8:\"asset_id\";}s:16:\"references_table\";s:6:\"assets\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:37:\"asset_allocations_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:8;a:5:{s:4:\"name\";s:11:\"attendances\";s:7:\"columns\";a:38:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:9:\"office_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:4:\"date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:52:\"enum(\'present\',\'absent\',\'paid-leave\',\'unpaid-leave\')\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:19:\"leave_duration_days\";s:4:\"type\";s:12:\"decimal(3,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"0.0\";}i:6;a:4:{s:4:\"name\";s:15:\"paid_leave_days\";s:4:\"type\";s:12:\"decimal(3,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"0.0\";}i:7;a:4:{s:4:\"name\";s:17:\"unpaid_leave_days\";s:4:\"type\";s:12:\"decimal(3,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"0.0\";}i:8;a:4:{s:4:\"name\";s:9:\"logged_at\";s:4:\"type\";s:4:\"time\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:9;a:4:{s:4:\"name\";s:13:\"check_in_time\";s:4:\"type\";s:4:\"time\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:14:\"check_out_time\";s:4:\"type\";s:4:\"time\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:13:\"checked_in_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:14:\"checked_out_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:17:\"check_in_latitude\";s:4:\"type\";s:13:\"decimal(10,7)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:18:\"check_in_longitude\";s:4:\"type\";s:13:\"decimal(10,7)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:24:\"check_in_accuracy_meters\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:24:\"check_in_distance_meters\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:15:\"check_in_source\";s:4:\"type\";s:11:\"varchar(30)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:18:\"check_out_latitude\";s:4:\"type\";s:13:\"decimal(10,7)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:19:\"check_out_longitude\";s:4:\"type\";s:13:\"decimal(10,7)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:20;a:4:{s:4:\"name\";s:25:\"check_out_accuracy_meters\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:21;a:4:{s:4:\"name\";s:25:\"check_out_distance_meters\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:22;a:4:{s:4:\"name\";s:16:\"check_out_source\";s:4:\"type\";s:11:\"varchar(30)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:23;a:4:{s:4:\"name\";s:25:\"geofence_exit_detected_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:24;a:4:{s:4:\"name\";s:13:\"break_minutes\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:25;a:4:{s:4:\"name\";s:13:\"gross_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:26;a:4:{s:4:\"name\";s:18:\"net_worked_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:27;a:4:{s:4:\"name\";s:16:\"required_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:28;a:4:{s:4:\"name\";s:20:\"late_arrival_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:29;a:4:{s:4:\"name\";s:23:\"early_departure_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:30;a:4:{s:4:\"name\";s:16:\"overtime_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:31;a:4:{s:4:\"name\";s:19:\"missing_punch_state\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"\'none\'\";}i:32;a:4:{s:4:\"name\";s:18:\"is_overnight_shift\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:33;a:4:{s:4:\"name\";s:16:\"calculation_hash\";s:4:\"type\";s:11:\"varchar(64)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:34;a:4:{s:4:\"name\";s:13:\"calculated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:35;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:36;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:37;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:5:{i:0;a:3:{s:4:\"name\";s:48:\"attendances_calculated_at_calculation_hash_index\";s:7:\"columns\";a:2:{i:0;s:13:\"calculated_at\";i:1;s:16:\"calculation_hash\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:39:\"attendances_date_office_id_status_index\";s:7:\"columns\";a:3:{i:0;s:4:\"date\";i:1;s:9:\"office_id\";i:2;s:6:\"status\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:44:\"attendances_employee_id_checked_out_at_index\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:14:\"checked_out_at\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:35:\"attendances_employee_id_date_unique\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:4:\"date\";}s:6:\"unique\";b:1;}i:4;a:3:{s:4:\"name\";s:29:\"attendances_office_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"office_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:31:\"attendances_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:29:\"attendances_office_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"office_id\";}s:16:\"references_table\";s:7:\"offices\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:9;a:5:{s:4:\"name\";s:10:\"audit_logs\";s:7:\"columns\";a:22:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:5:\"event\";s:4:\"type\";s:11:\"varchar(64)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"table_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:14:\"auditable_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:12:\"auditable_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:12:\"subject_type\";s:4:\"type\";s:11:\"varchar(80)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:13:\"subject_label\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:11:\"reviewed_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"audited_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:13:\"review_status\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'pending\'\";}i:11;a:4:{s:4:\"name\";s:11:\"reviewed_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"audited_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:10:\"old_values\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:10:\"new_values\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:15:\"changed_columns\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:3:\"url\";s:4:\"type\";s:13:\"varchar(2048)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:6:\"method\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:10:\"ip_address\";s:4:\"type\";s:11:\"varchar(45)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:20;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:21;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:9:{i:0;a:3:{s:4:\"name\";s:44:\"audit_logs_auditable_type_auditable_id_index\";s:7:\"columns\";a:2:{i:0;s:14:\"auditable_type\";i:1;s:12:\"auditable_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:38:\"audit_logs_audited_at_created_at_index\";s:7:\"columns\";a:2:{i:0;s:10:\"audited_at\";i:1;s:10:\"created_at\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:38:\"audit_logs_audited_by_audited_at_index\";s:7:\"columns\";a:2:{i:0;s:10:\"audited_by\";i:1;s:10:\"audited_at\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:39:\"audit_logs_employee_id_created_at_index\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:10:\"created_at\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:30:\"audit_logs_reviewed_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"reviewed_by\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:41:\"audit_logs_review_status_created_at_index\";s:7:\"columns\";a:2:{i:0;s:13:\"review_status\";i:1;s:10:\"created_at\";}s:6:\"unique\";b:0;}i:6;a:3:{s:4:\"name\";s:40:\"audit_logs_subject_type_created_at_index\";s:7:\"columns\";a:2:{i:0;s:12:\"subject_type\";i:1;s:10:\"created_at\";}s:6:\"unique\";b:0;}i:7;a:3:{s:4:\"name\";s:44:\"audit_logs_table_name_event_created_at_index\";s:7:\"columns\";a:3:{i:0;s:10:\"table_name\";i:1;s:5:\"event\";i:2;s:10:\"created_at\";}s:6:\"unique\";b:0;}i:8;a:3:{s:4:\"name\";s:26:\"audit_logs_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:4:{i:0;a:6:{s:4:\"name\";s:29:\"audit_logs_audited_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"audited_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:30:\"audit_logs_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:30:\"audit_logs_reviewed_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"reviewed_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:26:\"audit_logs_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:10;a:5:{s:4:\"name\";s:23:\"audit_resource_settings\";s:7:\"columns\";a:6:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:12:\"resource_key\";s:4:\"type\";s:11:\"varchar(80)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:7:\"enabled\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:3;a:4:{s:4:\"name\";s:10:\"updated_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:50:\"audit_resource_settings_enabled_resource_key_index\";s:7:\"columns\";a:2:{i:0;s:7:\"enabled\";i:1;s:12:\"resource_key\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:43:\"audit_resource_settings_resource_key_unique\";s:7:\"columns\";a:1:{i:0;s:12:\"resource_key\";}s:6:\"unique\";b:1;}i:2;a:3:{s:4:\"name\";s:42:\"audit_resource_settings_updated_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"updated_by\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:42:\"audit_resource_settings_updated_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"updated_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:11;a:5:{s:4:\"name\";s:15:\"automation_runs\";s:7:\"columns\";a:13:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:3:\"job\";s:4:\"type\";s:11:\"varchar(80)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"period_key\";s:4:\"type\";s:12:\"varchar(100)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'running\'\";}i:4;a:4:{s:4:\"name\";s:10:\"started_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:0;s:7:\"default\";s:19:\"current_timestamp()\";}i:5;a:4:{s:4:\"name\";s:11:\"finished_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:7:\"attempt\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:7;a:4:{s:4:\"name\";s:10:\"parameters\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:6:\"result\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:13:\"error_message\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:11:\"error_trace\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:37:\"automation_runs_job_period_key_unique\";s:7:\"columns\";a:2:{i:0;s:3:\"job\";i:1;s:10:\"period_key\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:39:\"automation_runs_status_started_at_index\";s:7:\"columns\";a:2:{i:0;s:6:\"status\";i:1;s:10:\"started_at\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:0:{}}i:12;a:5:{s:4:\"name\";s:6:\"boards\";s:7:\"columns\";a:7:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:10:\"project_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:5:\"title\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:5:\"order\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:4;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:29:\"boards_project_id_order_index\";s:7:\"columns\";a:2:{i:0;s:10:\"project_id\";i:1;s:5:\"order\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:25:\"boards_project_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"project_id\";}s:16:\"references_table\";s:8:\"projects\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:13;a:5:{s:4:\"name\";s:10:\"board_user\";s:7:\"columns\";a:2:{i:0;a:4:{s:4:\"name\";s:8:\"board_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}}s:11:\"primary_key\";a:2:{i:0;s:8:\"board_id\";i:1;s:7:\"user_id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:26:\"board_user_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:27:\"board_user_board_id_foreign\";s:7:\"columns\";a:1:{i:0;s:8:\"board_id\";}s:16:\"references_table\";s:6:\"boards\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:26:\"board_user_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:14;a:5:{s:4:\"name\";s:15:\"bonus_rule_sets\";s:7:\"columns\";a:8:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:26:\"\'Default task bonus rules\'\";}i:2;a:4:{s:4:\"name\";s:7:\"version\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:3;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:4;a:4:{s:4:\"name\";s:5:\"rules\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:10:\"updated_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:34:\"bonus_rule_sets_updated_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"updated_by\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:34:\"bonus_rule_sets_updated_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"updated_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:15;a:5:{s:4:\"name\";s:5:\"cache\";s:7:\"columns\";a:3:{i:0;a:4:{s:4:\"name\";s:3:\"key\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:5:\"value\";s:4:\"type\";s:10:\"mediumtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"expiration\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}}s:11:\"primary_key\";a:1:{i:0;s:3:\"key\";}s:7:\"indexes\";a:0:{}s:12:\"foreign_keys\";a:0:{}}i:16;a:5:{s:4:\"name\";s:11:\"cache_locks\";s:7:\"columns\";a:3:{i:0;a:4:{s:4:\"name\";s:3:\"key\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:5:\"owner\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"expiration\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}}s:11:\"primary_key\";a:1:{i:0;s:3:\"key\";}s:7:\"indexes\";a:0:{}s:12:\"foreign_keys\";a:0:{}}i:17;a:5:{s:4:\"name\";s:9:\"case_wins\";s:7:\"columns\";a:9:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:26:\"applicant_stage_history_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:6:\"won_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:0;s:7:\"default\";s:19:\"current_timestamp()\";}i:5;a:4:{s:4:\"name\";s:9:\"stage_key\";s:4:\"type\";s:12:\"varchar(100)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:29:\"case_wins_applicant_id_unique\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:43:\"case_wins_applicant_stage_history_id_unique\";s:7:\"columns\";a:1:{i:0;s:26:\"applicant_stage_history_id\";}s:6:\"unique\";b:1;}i:2;a:3:{s:4:\"name\";s:34:\"case_wins_employee_id_won_at_index\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:6:\"won_at\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:3:{i:0;a:6:{s:4:\"name\";s:30:\"case_wins_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:1;a:6:{s:4:\"name\";s:44:\"case_wins_applicant_stage_history_id_foreign\";s:7:\"columns\";a:1:{i:0;s:26:\"applicant_stage_history_id\";}s:16:\"references_table\";s:25:\"applicant_stage_histories\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:29:\"case_wins_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:18;a:5:{s:4:\"name\";s:8:\"comments\";s:7:\"columns\";a:7:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"task_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:7:\"content\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:24:\"comments_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"task_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:24:\"comments_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:24:\"comments_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"task_id\";}s:16:\"references_table\";s:5:\"tasks\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:24:\"comments_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:19;a:5:{s:4:\"name\";s:19:\"commission_accruals\";s:7:\"columns\";a:22:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:9:\"income_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:10:\"invoice_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:18:\"invoice_service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:10:\"service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:16:\"base_currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:24:\"commission_settlement_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"entry_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'earned\'\";}i:9;a:4:{s:4:\"name\";s:15:\"commission_role\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";s:10:\"\'delivery\'\";}i:10;a:4:{s:4:\"name\";s:9:\"earned_at\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:11;a:4:{s:4:\"name\";s:26:\"source_payment_base_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:12;a:4:{s:4:\"name\";s:20:\"eligible_base_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:13;a:4:{s:4:\"name\";s:10:\"percentage\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:14;a:4:{s:4:\"name\";s:22:\"commission_base_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:15;a:4:{s:4:\"name\";s:17:\"adjustment_reason\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:11:\"recorded_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'pending\'\";}i:18;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:20;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:21;a:4:{s:4:\"name\";s:19:\"contract_service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:11:{i:0;a:3:{s:4:\"name\";s:44:\"commission_accruals_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:52:\"commission_accruals_commission_settlement_id_foreign\";s:7:\"columns\";a:1:{i:0;s:24:\"commission_settlement_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:47:\"commission_accruals_contract_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"contract_service_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:54:\"commission_accruals_employee_id_status_earned_at_index\";s:7:\"columns\";a:3:{i:0;s:11:\"employee_id\";i:1;s:6:\"status\";i:2;s:9:\"earned_at\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:38:\"commission_accruals_invoice_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"invoice_id\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:39:\"commission_accruals_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:6:\"unique\";b:0;}i:6;a:3:{s:4:\"name\";s:38:\"commission_accruals_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:6:\"unique\";b:0;}i:7;a:3:{s:4:\"name\";s:32:\"commission_accrual_income_fk_idx\";s:7:\"columns\";a:1:{i:0;s:9:\"income_id\";}s:6:\"unique\";b:0;}i:8;a:3:{s:4:\"name\";s:41:\"commission_accrual_invoice_service_fk_idx\";s:7:\"columns\";a:1:{i:0;s:18:\"invoice_service_id\";}s:6:\"unique\";b:0;}i:9;a:3:{s:4:\"name\";s:34:\"commission_accrual_role_status_idx\";s:7:\"columns\";a:4:{i:0;s:11:\"employee_id\";i:1;s:15:\"commission_role\";i:2;s:6:\"status\";i:3;s:9:\"earned_at\";}s:6:\"unique\";b:0;}i:10;a:3:{s:4:\"name\";s:46:\"commission_accrual_source_employee_role_unique\";s:7:\"columns\";a:4:{i:0;s:9:\"income_id\";i:1;s:18:\"invoice_service_id\";i:2;s:11:\"employee_id\";i:3;s:15:\"commission_role\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:9:{i:0;a:6:{s:4:\"name\";s:44:\"commission_accruals_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:1;a:6:{s:4:\"name\";s:52:\"commission_accruals_commission_settlement_id_foreign\";s:7:\"columns\";a:1:{i:0;s:24:\"commission_settlement_id\";}s:16:\"references_table\";s:22:\"commission_settlements\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:47:\"commission_accruals_contract_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"contract_service_id\";}s:16:\"references_table\";s:16:\"contract_service\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:39:\"commission_accruals_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:4;a:6:{s:4:\"name\";s:37:\"commission_accruals_income_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"income_id\";}s:16:\"references_table\";s:7:\"incomes\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:5;a:6:{s:4:\"name\";s:38:\"commission_accruals_invoice_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"invoice_id\";}s:16:\"references_table\";s:8:\"invoices\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:6;a:6:{s:4:\"name\";s:46:\"commission_accruals_invoice_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"invoice_service_id\";}s:16:\"references_table\";s:15:\"invoice_service\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:7;a:6:{s:4:\"name\";s:39:\"commission_accruals_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:8;a:6:{s:4:\"name\";s:38:\"commission_accruals_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:16:\"references_table\";s:8:\"services\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:20;a:5:{s:4:\"name\";s:22:\"commission_settlements\";s:7:\"columns\";a:19:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:17:\"settlement_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:16:\"base_currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:12:\"period_start\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:10:\"period_end\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:17:\"gross_base_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:7:\"\'draft\'\";}i:8;a:4:{s:4:\"name\";s:5:\"notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:11:\"recorded_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:11:\"approved_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:11:\"approved_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:7:\"paid_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:12:\"payment_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:7:\"paid_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:10:\"expense_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:7:{i:0;a:3:{s:4:\"name\";s:42:\"commission_settlements_approved_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"approved_by\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:47:\"commission_settlements_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:40:\"commission_settlements_expense_id_unique\";s:7:\"columns\";a:1:{i:0;s:10:\"expense_id\";}s:6:\"unique\";b:1;}i:3;a:3:{s:4:\"name\";s:38:\"commission_settlements_paid_by_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"paid_by\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:42:\"commission_settlements_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:47:\"commission_settlements_settlement_number_unique\";s:7:\"columns\";a:1:{i:0;s:17:\"settlement_number\";}s:6:\"unique\";b:1;}i:6;a:3:{s:4:\"name\";s:32:\"commission_settlement_period_idx\";s:7:\"columns\";a:4:{i:0;s:11:\"employee_id\";i:1;s:6:\"status\";i:2;s:12:\"period_start\";i:3;s:10:\"period_end\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:6:{i:0;a:6:{s:4:\"name\";s:42:\"commission_settlements_approved_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"approved_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:47:\"commission_settlements_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:2;a:6:{s:4:\"name\";s:42:\"commission_settlements_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:3;a:6:{s:4:\"name\";s:41:\"commission_settlements_expense_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"expense_id\";}s:16:\"references_table\";s:8:\"expenses\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:38:\"commission_settlements_paid_by_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"paid_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:5;a:6:{s:4:\"name\";s:42:\"commission_settlements_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:21;a:5:{s:4:\"name\";s:9:\"companies\";s:7:\"columns\";a:25:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:10:\"legal_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"trade_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:19:\"registration_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"tax_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:11:\"national_id\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"legal_form\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:5:\"email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:5:\"phone\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:7:\"website\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:7:\"country\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:8:\"province\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:4:\"city\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:11:\"postal_code\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:12:\"address_line\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:8:\"industry\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:19:\"representative_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:20:\"representative_title\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:20:\"representative_phone\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:20;a:4:{s:4:\"name\";s:20:\"representative_email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:21;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:22;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:23;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:24;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:0:{}s:12:\"foreign_keys\";a:0:{}}i:22;a:5:{s:4:\"name\";s:9:\"contracts\";s:7:\"columns\";a:34:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:15:\"contract_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:11:\"template_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:28:\"contract_template_version_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:19:\"contract_country_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:20:\"contract_language_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:19:\"sold_by_employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:11:\"marketer_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:17:\"source_service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"company_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:16:\"contract_type_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:4:\"type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:16;a:4:{s:4:\"name\";s:13:\"contract_role\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'primary\'\";}i:17;a:4:{s:4:\"name\";s:18:\"parent_contract_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:10:\"start_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:19;a:4:{s:4:\"name\";s:12:\"counterparty\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:20;a:4:{s:4:\"name\";s:20:\"counterparty_company\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:21;a:4:{s:4:\"name\";s:18:\"counterparty_email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:22;a:4:{s:4:\"name\";s:20:\"counterparty_contact\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:23;a:4:{s:4:\"name\";s:20:\"counterparty_address\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:24;a:4:{s:4:\"name\";s:22:\"company_signatory_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:25;a:4:{s:4:\"name\";s:12:\"is_cancelled\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:26;a:4:{s:4:\"name\";s:12:\"cancelled_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:27;a:4:{s:4:\"name\";s:10:\"cover_text\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:28;a:4:{s:4:\"name\";s:7:\"content\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:29;a:4:{s:4:\"name\";s:9:\"is_custom\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:30;a:4:{s:4:\"name\";s:13:\"snapshot_data\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:31;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:32;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:33;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:14:{i:0;a:3:{s:4:\"name\";s:28:\"contracts_applicant_id_index\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:28:\"contracts_company_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"company_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:37:\"contracts_contract_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"contract_country_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:38:\"contracts_contract_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:20:\"contract_language_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:32:\"contracts_contract_number_unique\";s:7:\"columns\";a:1:{i:0;s:15:\"contract_number\";}s:6:\"unique\";b:1;}i:5;a:3:{s:4:\"name\";s:46:\"contracts_contract_template_version_id_foreign\";s:7:\"columns\";a:1:{i:0;s:28:\"contract_template_version_id\";}s:6:\"unique\";b:0;}i:6;a:3:{s:4:\"name\";s:34:\"contracts_contract_type_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"contract_type_id\";}s:6:\"unique\";b:0;}i:7;a:3:{s:4:\"name\";s:29:\"contracts_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:6:\"unique\";b:0;}i:8;a:3:{s:4:\"name\";s:29:\"contracts_marketer_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"marketer_id\";}s:6:\"unique\";b:0;}i:9;a:3:{s:4:\"name\";s:36:\"contracts_parent_contract_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"parent_contract_id\";}s:6:\"unique\";b:0;}i:10;a:3:{s:4:\"name\";s:31:\"contracts_service_primary_index\";s:7:\"columns\";a:3:{i:0;s:12:\"applicant_id\";i:1;s:17:\"source_service_id\";i:2;s:13:\"contract_role\";}s:6:\"unique\";b:0;}i:11;a:3:{s:4:\"name\";s:37:\"contracts_sold_by_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"sold_by_employee_id\";}s:6:\"unique\";b:0;}i:12;a:3:{s:4:\"name\";s:35:\"contracts_source_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:17:\"source_service_id\";}s:6:\"unique\";b:0;}i:13;a:3:{s:4:\"name\";s:29:\"contracts_template_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"template_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:12:{i:0;a:6:{s:4:\"name\";s:30:\"contracts_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:28:\"contracts_company_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"company_id\";}s:16:\"references_table\";s:9:\"companies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:37:\"contracts_contract_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"contract_country_id\";}s:16:\"references_table\";s:9:\"countries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:38:\"contracts_contract_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:20:\"contract_language_id\";}s:16:\"references_table\";s:9:\"languages\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:46:\"contracts_contract_template_version_id_foreign\";s:7:\"columns\";a:1:{i:0;s:28:\"contract_template_version_id\";}s:16:\"references_table\";s:26:\"contract_template_versions\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:5;a:6:{s:4:\"name\";s:34:\"contracts_contract_type_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"contract_type_id\";}s:16:\"references_table\";s:14:\"contract_types\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:6;a:6:{s:4:\"name\";s:29:\"contracts_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:7;a:6:{s:4:\"name\";s:29:\"contracts_marketer_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"marketer_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:8;a:6:{s:4:\"name\";s:36:\"contracts_parent_contract_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"parent_contract_id\";}s:16:\"references_table\";s:9:\"contracts\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:9;a:6:{s:4:\"name\";s:37:\"contracts_sold_by_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"sold_by_employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:10;a:6:{s:4:\"name\";s:35:\"contracts_source_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:17:\"source_service_id\";}s:16:\"references_table\";s:8:\"services\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:11;a:6:{s:4:\"name\";s:29:\"contracts_template_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"template_id\";}s:16:\"references_table\";s:18:\"contract_templates\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:23;a:5:{s:4:\"name\";s:25:\"contract_document_designs\";s:7:\"columns\";a:12:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:16:\"contract_type_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:10:\"letterhead\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:12:\"cover_design\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:6:\"safe_x\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:4:\"8.50\";}i:6;a:4:{s:4:\"name\";s:6:\"safe_y\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:5:\"15.00\";}i:7;a:4:{s:4:\"name\";s:10:\"safe_width\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:5:\"83.00\";}i:8;a:4:{s:4:\"name\";s:11:\"safe_height\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:5:\"70.00\";}i:9;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:35:\"contract_design_type_service_unique\";s:7:\"columns\";a:2:{i:0;s:16:\"contract_type_id\";i:1;s:10:\"service_id\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:44:\"contract_document_designs_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:50:\"contract_document_designs_contract_type_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"contract_type_id\";}s:16:\"references_table\";s:14:\"contract_types\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:44:\"contract_document_designs_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:16:\"references_table\";s:8:\"services\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:24;a:5:{s:4:\"name\";s:17:\"contract_policies\";s:7:\"columns\";a:11:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:5:\"label\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:4:\"type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:10:\"country_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:11:\"language_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:6;a:4:{s:4:\"name\";s:6:\"header\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:7:\"content\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:8;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:4:{i:0;a:3:{s:4:\"name\";s:36:\"contract_policies_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:37:\"contract_policies_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:35:\"contract_policies_type_active_index\";s:7:\"columns\";a:2:{i:0;s:4:\"type\";i:1;s:9:\"is_active\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:27:\"contract_policy_scope_index\";s:7:\"columns\";a:4:{i:0;s:4:\"type\";i:1;s:10:\"country_id\";i:2;s:11:\"language_id\";i:3;s:9:\"is_active\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:36:\"contract_policies_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:16:\"references_table\";s:9:\"countries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:37:\"contract_policies_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:16:\"references_table\";s:9:\"languages\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:25;a:5:{s:4:\"name\";s:33:\"contract_policy_contract_template\";s:7:\"columns\";a:3:{i:0;a:4:{s:4:\"name\";s:20:\"contract_template_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:18:\"contract_policy_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"sort_order\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}}s:11:\"primary_key\";a:2:{i:0;s:20:\"contract_template_id\";i:1;s:18:\"contract_policy_id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:60:\"contract_policy_contract_template_contract_policy_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"contract_policy_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:60:\"contract_policy_contract_template_contract_policy_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"contract_policy_id\";}s:16:\"references_table\";s:17:\"contract_policies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:62:\"contract_policy_contract_template_contract_template_id_foreign\";s:7:\"columns\";a:1:{i:0;s:20:\"contract_template_id\";}s:16:\"references_table\";s:18:\"contract_templates\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:26;a:5:{s:4:\"name\";s:16:\"contract_service\";s:7:\"columns\";a:18:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"contract_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:3:\"fee\";s:4:\"type\";s:13:\"decimal(15,2)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:19:\"customer_package_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:19:\"sold_by_employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:11:\"marketer_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:22:\"outsourcing_company_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:22:\"outsourcing_percentage\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"country_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:18:\"education_level_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:11:\"language_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:5:\"notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:11:{i:0;a:3:{s:4:\"name\";s:37:\"contract_service_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:35:\"contract_service_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:36:\"contract_service_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:44:\"contract_service_customer_package_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"customer_package_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:43:\"contract_service_education_level_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"education_level_id\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:36:\"contract_service_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:6:\"unique\";b:0;}i:6;a:3:{s:4:\"name\";s:36:\"contract_service_marketer_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"marketer_id\";}s:6:\"unique\";b:0;}i:7;a:3:{s:4:\"name\";s:47:\"contract_service_outsourcing_company_id_foreign\";s:7:\"columns\";a:1:{i:0;s:22:\"outsourcing_company_id\";}s:6:\"unique\";b:0;}i:8;a:3:{s:4:\"name\";s:28:\"contract_service_service_idx\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:6:\"unique\";b:0;}i:9;a:3:{s:4:\"name\";s:44:\"contract_service_sold_by_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"sold_by_employee_id\";}s:6:\"unique\";b:0;}i:10;a:3:{s:4:\"name\";s:23:\"contract_service_unique\";s:7:\"columns\";a:2:{i:0;s:11:\"contract_id\";i:1;s:10:\"service_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:11:{i:0;a:6:{s:4:\"name\";s:37:\"contract_service_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:36:\"contract_service_contract_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"contract_id\";}s:16:\"references_table\";s:9:\"contracts\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:2;a:6:{s:4:\"name\";s:35:\"contract_service_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:16:\"references_table\";s:9:\"countries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:36:\"contract_service_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:44:\"contract_service_customer_package_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"customer_package_id\";}s:16:\"references_table\";s:17:\"customer_packages\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:5;a:6:{s:4:\"name\";s:43:\"contract_service_education_level_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"education_level_id\";}s:16:\"references_table\";s:16:\"education_levels\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:6;a:6:{s:4:\"name\";s:36:\"contract_service_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:16:\"references_table\";s:9:\"languages\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:7;a:6:{s:4:\"name\";s:36:\"contract_service_marketer_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"marketer_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:8;a:6:{s:4:\"name\";s:47:\"contract_service_outsourcing_company_id_foreign\";s:7:\"columns\";a:1:{i:0;s:22:\"outsourcing_company_id\";}s:16:\"references_table\";s:9:\"companies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:9;a:6:{s:4:\"name\";s:35:\"contract_service_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:16:\"references_table\";s:8:\"services\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:10;a:6:{s:4:\"name\";s:44:\"contract_service_sold_by_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"sold_by_employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:27;a:5:{s:4:\"name\";s:25:\"contract_service_employee\";s:7:\"columns\";a:6:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:19:\"contract_service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:10:\"percentage\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:53:\"contract_service_employee_contract_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"contract_service_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:32:\"contract_service_employee_unique\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:19:\"contract_service_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:53:\"contract_service_employee_contract_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"contract_service_id\";}s:16:\"references_table\";s:16:\"contract_service\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:45:\"contract_service_employee_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:28;a:5:{s:4:\"name\";s:18:\"contract_templates\";s:7:\"columns\";a:14:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"position_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:16:\"contract_type_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:10:\"service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:7;a:4:{s:4:\"name\";s:10:\"created_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:4:\"type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"cover_text\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:10;a:4:{s:4:\"name\";s:7:\"content\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:11;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:4:{i:0;a:3:{s:4:\"name\";s:43:\"contract_templates_contract_type_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"contract_type_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:37:\"contract_templates_created_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"created_by\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:36:\"contract_templates_position_id_index\";s:7:\"columns\";a:1:{i:0;s:11:\"position_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:35:\"contract_templates_service_id_index\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:4:{i:0;a:6:{s:4:\"name\";s:43:\"contract_templates_contract_type_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"contract_type_id\";}s:16:\"references_table\";s:14:\"contract_types\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:37:\"contract_templates_created_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"created_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:38:\"contract_templates_position_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"position_id\";}s:16:\"references_table\";s:9:\"positions\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:37:\"contract_templates_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:16:\"references_table\";s:8:\"services\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:29;a:5:{s:4:\"name\";s:26:\"contract_template_versions\";s:7:\"columns\";a:18:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:20:\"contract_template_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"language_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:10:\"country_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:14:\"version_number\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:18:\"\'Untitled version\'\";}i:6;a:4:{s:4:\"name\";s:7:\"content\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:7:\"\'draft\'\";}i:8;a:4:{s:4:\"name\";s:10:\"is_current\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:9;a:4:{s:4:\"name\";s:17:\"current_scope_key\";s:4:\"type\";s:12:\"varchar(100)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"created_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:12:\"activated_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:12:\"activated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:11:\"archived_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:11:\"archived_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:9:{i:0;a:3:{s:4:\"name\";s:47:\"contract_template_versions_activated_by_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"activated_by\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:46:\"contract_template_versions_archived_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"archived_by\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:45:\"contract_template_versions_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:45:\"contract_template_versions_created_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"created_by\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:51:\"contract_template_versions_current_scope_key_unique\";s:7:\"columns\";a:1:{i:0;s:17:\"current_scope_key\";}s:6:\"unique\";b:1;}i:5;a:3:{s:4:\"name\";s:46:\"contract_template_versions_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:6:\"unique\";b:0;}i:6;a:3:{s:4:\"name\";s:34:\"ctv_country_language_current_index\";s:7:\"columns\";a:4:{i:0;s:20:\"contract_template_id\";i:1;s:10:\"country_id\";i:2;s:11:\"language_id\";i:3;s:10:\"is_current\";}s:6:\"unique\";b:0;}i:7;a:3:{s:4:\"name\";s:44:\"ctv_template_country_language_version_unique\";s:7:\"columns\";a:4:{i:0;s:20:\"contract_template_id\";i:1;s:10:\"country_id\";i:2;s:11:\"language_id\";i:3;s:14:\"version_number\";}s:6:\"unique\";b:1;}i:8;a:3:{s:4:\"name\";s:35:\"ctv_template_language_current_index\";s:7:\"columns\";a:3:{i:0;s:20:\"contract_template_id\";i:1;s:11:\"language_id\";i:2;s:10:\"is_current\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:6:{i:0;a:6:{s:4:\"name\";s:47:\"contract_template_versions_activated_by_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"activated_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:46:\"contract_template_versions_archived_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"archived_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:55:\"contract_template_versions_contract_template_id_foreign\";s:7:\"columns\";a:1:{i:0;s:20:\"contract_template_id\";}s:16:\"references_table\";s:18:\"contract_templates\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:3;a:6:{s:4:\"name\";s:45:\"contract_template_versions_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:16:\"references_table\";s:9:\"countries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:45:\"contract_template_versions_created_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"created_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:5;a:6:{s:4:\"name\";s:46:\"contract_template_versions_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:16:\"references_table\";s:9:\"languages\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:30;a:5:{s:4:\"name\";s:14:\"contract_types\";s:7:\"columns\";a:9:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:4:\"slug\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:9:\"is_system\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:5;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:6;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:26:\"contract_types_name_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"name\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:26:\"contract_types_slug_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"slug\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:31;a:5:{s:4:\"name\";s:9:\"countries\";s:7:\"columns\";a:7:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:4:\"code\";s:4:\"type\";s:10:\"varchar(3)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:4;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:21:\"countries_code_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"code\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:21:\"countries_name_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"name\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:32;a:5:{s:4:\"name\";s:16:\"crm_interactions\";s:7:\"columns\";a:12:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:16:\"contactable_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:14:\"contactable_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:10:\"created_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:7:\"channel\";s:4:\"type\";s:47:\"enum(\'call\',\'message\',\'email\',\'meeting\',\'note\')\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"\'note\'\";}i:5;a:4:{s:4:\"name\";s:5:\"title\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:5:\"notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:12:\"contacted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:0;s:7:\"default\";s:19:\"current_timestamp()\";}i:8;a:4:{s:4:\"name\";s:12:\"follow_up_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:54:\"crm_interactions_contactable_type_contactable_id_index\";s:7:\"columns\";a:2:{i:0;s:16:\"contactable_type\";i:1;s:14:\"contactable_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:48:\"crm_interactions_contacted_at_follow_up_at_index\";s:7:\"columns\";a:2:{i:0;s:12:\"contacted_at\";i:1;s:12:\"follow_up_at\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:35:\"crm_interactions_created_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"created_by\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:35:\"crm_interactions_created_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"created_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:33;a:5:{s:4:\"name\";s:10:\"currencies\";s:7:\"columns\";a:11:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:6:\"symbol\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:4:\"code\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:7:\"is_base\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:5;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:6;a:4:{s:4:\"name\";s:14:\"decimal_places\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"2\";}i:7;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:4:\"rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";s:10:\"1.00000000\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:0:{}s:12:\"foreign_keys\";a:0:{}}i:34;a:5:{s:4:\"name\";s:23:\"currency_rate_histories\";s:7:\"columns\";a:8:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:16:\"base_currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:4:\"rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:6:\"reason\";s:4:\"type\";s:11:\"varchar(40)\";s:8:\"nullable\";b:0;s:7:\"default\";s:14:\"\'rate_updated\'\";}i:5;a:4:{s:4:\"name\";s:11:\"recorded_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:48:\"currency_rate_histories_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:52:\"currency_rate_histories_currency_id_created_at_index\";s:7:\"columns\";a:2:{i:0;s:11:\"currency_id\";i:1;s:10:\"created_at\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:43:\"currency_rate_histories_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:3:{i:0;a:6:{s:4:\"name\";s:48:\"currency_rate_histories_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:43:\"currency_rate_histories_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:2;a:6:{s:4:\"name\";s:43:\"currency_rate_histories_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:35;a:5:{s:4:\"name\";s:17:\"customer_packages\";s:7:\"columns\";a:13:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:10:\"package_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:19:\"sold_by_employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:3:\"fee\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:12:\"purchased_at\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:11:\"varchar(30)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'active\'\";}i:8;a:4:{s:4:\"name\";s:12:\"issuance_key\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:9;a:4:{s:4:\"name\";s:5:\"notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:5:{i:0;a:3:{s:4:\"name\";s:37:\"customer_packages_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:55:\"customer_packages_customer_id_applicant_id_status_index\";s:7:\"columns\";a:2:{i:0;s:12:\"applicant_id\";i:1;s:6:\"status\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:37:\"customer_packages_issuance_key_unique\";s:7:\"columns\";a:1:{i:0;s:12:\"issuance_key\";}s:6:\"unique\";b:1;}i:3;a:3:{s:4:\"name\";s:36:\"customer_packages_package_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"package_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:45:\"customer_packages_sold_by_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"sold_by_employee_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:4:{i:0;a:6:{s:4:\"name\";s:38:\"customer_packages_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:37:\"customer_packages_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:36:\"customer_packages_package_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"package_id\";}s:16:\"references_table\";s:13:\"package_types\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:3;a:6:{s:4:\"name\";s:45:\"customer_packages_sold_by_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"sold_by_employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:36;a:5:{s:4:\"name\";s:15:\"daily_analytics\";s:7:\"columns\";a:21:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:18:\"attendance_present\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:3;a:4:{s:4:\"name\";s:17:\"attendance_absent\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:4;a:4:{s:4:\"name\";s:21:\"attendance_paid_leave\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:5;a:4:{s:4:\"name\";s:21:\"attendance_incomplete\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:6;a:4:{s:4:\"name\";s:14:\"worked_minutes\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:7;a:4:{s:4:\"name\";s:16:\"overtime_minutes\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:8;a:4:{s:4:\"name\";s:12:\"income_total\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"0.0000\";}i:9;a:4:{s:4:\"name\";s:13:\"expense_total\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"0.0000\";}i:10;a:4:{s:4:\"name\";s:13:\"tasks_created\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:11;a:4:{s:4:\"name\";s:15:\"tasks_completed\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:12;a:4:{s:4:\"name\";s:17:\"contracts_created\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:13;a:4:{s:4:\"name\";s:19:\"contracts_cancelled\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:14;a:4:{s:4:\"name\";s:19:\"employees_onboarded\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:15;a:4:{s:4:\"name\";s:17:\"bonus_nominations\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:16;a:4:{s:4:\"name\";s:15:\"bonuses_granted\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:17;a:4:{s:4:\"name\";s:16:\"bonus_base_total\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"0.0000\";}i:18;a:4:{s:4:\"name\";s:13:\"calculated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:0;s:7:\"default\";s:19:\"current_timestamp()\";}i:19;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:20;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:27:\"daily_analytics_date_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"date\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:37;a:5:{s:4:\"name\";s:13:\"daily_reports\";s:7:\"columns\";a:13:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:13:\"attendance_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:11:\"report_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:7:\"summary\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:12:\"achievements\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"challenges\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:13:\"next_day_plan\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:14:\"support_needed\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:12:\"submitted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:0;s:7:\"default\";s:19:\"current_timestamp()\";}i:10;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:34:\"daily_reports_attendance_id_unique\";s:7:\"columns\";a:1:{i:0;s:13:\"attendance_id\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:44:\"daily_reports_report_date_submitted_at_index\";s:7:\"columns\";a:2:{i:0;s:11:\"report_date\";i:1;s:12:\"submitted_at\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:40:\"daily_reports_user_id_report_date_unique\";s:7:\"columns\";a:2:{i:0;s:7:\"user_id\";i:1;s:11:\"report_date\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:35:\"daily_reports_attendance_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"attendance_id\";}s:16:\"references_table\";s:11:\"attendances\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:1;a:6:{s:4:\"name\";s:29:\"daily_reports_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:38;a:5:{s:4:\"name\";s:11:\"departments\";s:7:\"columns\";a:9:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:4:\"code\";s:4:\"type\";s:11:\"varchar(40)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:19:\"manager_employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:6;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:23:\"departments_code_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"code\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:39:\"departments_manager_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"manager_employee_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:23:\"departments_name_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"name\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:39:\"departments_manager_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"manager_employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:39;a:5:{s:4:\"name\";s:25:\"educational_bonus_sources\";s:7:\"columns\";a:12:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:11:\"source_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:9:\"source_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:13:\"source_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:12:\"bonus_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"0.000000\";}i:7;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'pending\'\";}i:8;a:4:{s:4:\"name\";s:11:\"rule_source\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:4:{i:0;a:3:{s:4:\"name\";s:46:\"educational_bonus_sources_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:45:\"educational_bonus_sources_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:53:\"educational_bonus_sources_source_type_source_id_index\";s:7:\"columns\";a:2:{i:0;s:11:\"source_type\";i:1;s:9:\"source_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:31:\"educational_bonus_source_unique\";s:7:\"columns\";a:3:{i:0;s:11:\"source_type\";i:1;s:9:\"source_id\";i:2;s:11:\"employee_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:46:\"educational_bonus_sources_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:45:\"educational_bonus_sources_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:40;a:5:{s:4:\"name\";s:16:\"education_levels\";s:7:\"columns\";a:8:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:4;a:4:{s:4:\"name\";s:10:\"sort_order\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:5;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:28:\"education_levels_name_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"name\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:41;a:5:{s:4:\"name\";s:9:\"employees\";s:7:\"columns\";a:33:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:11:\"father_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:7:\"contact\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:9:\"id_number\";s:4:\"type\";s:12:\"varchar(100)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:19:\"guarantor_id_number\";s:4:\"type\";s:12:\"varchar(100)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:15:\"guarantor_phone\";s:4:\"type\";s:11:\"varchar(30)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:17:\"guarantor_address\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:17:\"guarantor_licence\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:13:\"education_lvl\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:11;a:4:{s:4:\"name\";s:11:\"position_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:12;a:4:{s:4:\"name\";s:15:\"employment_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:10:\"\'salaried\'\";}i:13;a:4:{s:4:\"name\";s:9:\"office_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:6:\"salary\";s:4:\"type\";s:13:\"decimal(10,2)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:18:\"total_worked_hours\";s:4:\"type\";s:13:\"decimal(14,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:4:\"0.00\";}i:16;a:4:{s:4:\"name\";s:4:\"addr\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:17;a:4:{s:4:\"name\";s:6:\"avatar\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:13:\"document_path\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:13:\"document_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:20;a:4:{s:4:\"name\";s:13:\"document_mime\";s:4:\"type\";s:12:\"varchar(120)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:21;a:4:{s:4:\"name\";s:13:\"document_size\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:22;a:4:{s:4:\"name\";s:10:\"department\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:23;a:4:{s:4:\"name\";s:13:\"department_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:24;a:4:{s:4:\"name\";s:13:\"supervisor_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:25;a:4:{s:4:\"name\";s:9:\"join_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:26;a:4:{s:4:\"name\";s:15:\"work_start_time\";s:4:\"type\";s:4:\"time\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:27;a:4:{s:4:\"name\";s:13:\"work_end_time\";s:4:\"type\";s:4:\"time\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:28;a:4:{s:4:\"name\";s:17:\"contract_duration\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:29;a:4:{s:4:\"name\";s:19:\"bank_account_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:30;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:31;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:32;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:6:{i:0;a:3:{s:4:\"name\";s:31:\"employees_department_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"department_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:26:\"employees_id_number_unique\";s:7:\"columns\";a:1:{i:0;s:9:\"id_number\";}s:6:\"unique\";b:1;}i:2;a:3:{s:4:\"name\";s:27:\"employees_office_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"office_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:29:\"employees_position_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"position_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:31:\"employees_supervisor_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"supervisor_id\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:24:\"employees_user_id_unique\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:5:{i:0;a:6:{s:4:\"name\";s:31:\"employees_department_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"department_id\";}s:16:\"references_table\";s:11:\"departments\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:27:\"employees_office_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"office_id\";}s:16:\"references_table\";s:7:\"offices\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:29:\"employees_position_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"position_id\";}s:16:\"references_table\";s:9:\"positions\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:3;a:6:{s:4:\"name\";s:31:\"employees_supervisor_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"supervisor_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:25:\"employees_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:42;a:5:{s:4:\"name\";s:28:\"employee_service_commissions\";s:7:\"columns\";a:6:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:10:\"percentage\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:58:\"employee_service_commissions_employee_id_service_id_unique\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:10:\"service_id\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:47:\"employee_service_commissions_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:48:\"employee_service_commissions_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:47:\"employee_service_commissions_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:16:\"references_table\";s:8:\"services\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:43;a:5:{s:4:\"name\";s:8:\"expenses\";s:7:\"columns\";a:28:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"recorded_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:10:\"visitor_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:9:\"salary_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:16:\"base_currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:4:\"type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:9;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:10;a:4:{s:4:\"name\";s:13:\"document_path\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:13:\"document_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:13:\"document_mime\";s:4:\"type\";s:12:\"varchar(120)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:13:\"document_size\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:6:\"amount\";s:4:\"type\";s:13:\"decimal(12,2)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:15;a:4:{s:4:\"name\";s:8:\"quantity\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:16;a:4:{s:4:\"name\";s:8:\"currency\";s:4:\"type\";s:10:\"varchar(3)\";s:8:\"nullable\";b:0;s:7:\"default\";s:5:\"\'USD\'\";}i:17;a:4:{s:4:\"name\";s:4:\"date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:18;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:20;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:21;a:4:{s:4:\"name\";s:13:\"cnv_curr_shrt\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:22;a:4:{s:4:\"name\";s:14:\"base_curr_shrt\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:23;a:4:{s:4:\"name\";s:4:\"rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:24;a:4:{s:4:\"name\";s:11:\"base_amount\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:25;a:4:{s:4:\"name\";s:10:\"company_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:26;a:4:{s:4:\"name\";s:10:\"project_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:27;a:4:{s:4:\"name\";s:22:\"pending_transaction_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:10:{i:0;a:3:{s:4:\"name\";s:32:\"expenses_applicant_id_date_index\";s:7:\"columns\";a:2:{i:0;s:12:\"applicant_id\";i:1;s:4:\"date\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:33:\"expenses_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:28:\"expenses_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:28:\"expenses_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:38:\"expenses_pending_transaction_id_unique\";s:7:\"columns\";a:1:{i:0;s:22:\"pending_transaction_id\";}s:6:\"unique\";b:1;}i:5;a:3:{s:4:\"name\";s:26:\"expenses_recorded_by_index\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:6:\"unique\";b:0;}i:6;a:3:{s:4:\"name\";s:25:\"expenses_salary_id_unique\";s:7:\"columns\";a:1:{i:0;s:9:\"salary_id\";}s:6:\"unique\";b:1;}i:7;a:3:{s:4:\"name\";s:27:\"expenses_visitor_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"visitor_id\";}s:6:\"unique\";b:0;}i:8;a:3:{s:4:\"name\";s:19:\"expense_company_idx\";s:7:\"columns\";a:1:{i:0;s:10:\"company_id\";}s:6:\"unique\";b:0;}i:9;a:3:{s:4:\"name\";s:19:\"expense_project_idx\";s:7:\"columns\";a:1:{i:0;s:10:\"project_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:10:{i:0;a:6:{s:4:\"name\";s:29:\"expenses_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:33:\"expenses_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:27:\"expenses_company_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"company_id\";}s:16:\"references_table\";s:9:\"companies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:28:\"expenses_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:4;a:6:{s:4:\"name\";s:28:\"expenses_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:5;a:6:{s:4:\"name\";s:39:\"expenses_pending_transaction_id_foreign\";s:7:\"columns\";a:1:{i:0;s:22:\"pending_transaction_id\";}s:16:\"references_table\";s:20:\"pending_transactions\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:6;a:6:{s:4:\"name\";s:27:\"expenses_project_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"project_id\";}s:16:\"references_table\";s:8:\"projects\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:7;a:6:{s:4:\"name\";s:28:\"expenses_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:8;a:6:{s:4:\"name\";s:26:\"expenses_salary_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"salary_id\";}s:16:\"references_table\";s:8:\"salaries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:9;a:6:{s:4:\"name\";s:27:\"expenses_visitor_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"visitor_id\";}s:16:\"references_table\";s:8:\"visitors\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:44;a:5:{s:4:\"name\";s:11:\"failed_jobs\";s:7:\"columns\";a:7:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"uuid\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"connection\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:5:\"queue\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:7:\"payload\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:9:\"exception\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:9:\"failed_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:0;s:7:\"default\";s:19:\"current_timestamp()\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:23:\"failed_jobs_uuid_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"uuid\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:45;a:5:{s:4:\"name\";s:7:\"incomes\";s:7:\"columns\";a:25:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"recorded_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:14:\"receipt_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"visitor_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"invoice_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:16:\"base_currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:4:\"type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:10;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:11;a:4:{s:4:\"name\";s:6:\"amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:12;a:4:{s:4:\"name\";s:8:\"quantity\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:13;a:4:{s:4:\"name\";s:8:\"currency\";s:4:\"type\";s:10:\"varchar(3)\";s:8:\"nullable\";b:0;s:7:\"default\";s:5:\"\'USD\'\";}i:14;a:4:{s:4:\"name\";s:4:\"date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:15;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:13:\"cnv_curr_shrt\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:19;a:4:{s:4:\"name\";s:14:\"base_curr_shrt\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:20;a:4:{s:4:\"name\";s:4:\"rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:21;a:4:{s:4:\"name\";s:11:\"base_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:22;a:4:{s:4:\"name\";s:14:\"invoice_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:23;a:4:{s:4:\"name\";s:10:\"company_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:24;a:4:{s:4:\"name\";s:22:\"pending_transaction_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:10:{i:0;a:3:{s:4:\"name\";s:31:\"incomes_applicant_id_date_index\";s:7:\"columns\";a:2:{i:0;s:12:\"applicant_id\";i:1;s:4:\"date\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:32:\"incomes_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:27:\"incomes_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:27:\"incomes_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:24:\"incomes_invoice_id_index\";s:7:\"columns\";a:1:{i:0;s:10:\"invoice_id\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:37:\"incomes_pending_transaction_id_unique\";s:7:\"columns\";a:1:{i:0;s:22:\"pending_transaction_id\";}s:6:\"unique\";b:1;}i:6;a:3:{s:4:\"name\";s:29:\"incomes_receipt_number_unique\";s:7:\"columns\";a:1:{i:0;s:14:\"receipt_number\";}s:6:\"unique\";b:1;}i:7;a:3:{s:4:\"name\";s:25:\"incomes_recorded_by_index\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:6:\"unique\";b:0;}i:8;a:3:{s:4:\"name\";s:26:\"incomes_visitor_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"visitor_id\";}s:6:\"unique\";b:0;}i:9;a:3:{s:4:\"name\";s:18:\"income_company_idx\";s:7:\"columns\";a:1:{i:0;s:10:\"company_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:9:{i:0;a:6:{s:4:\"name\";s:28:\"incomes_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:32:\"incomes_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:26:\"incomes_company_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"company_id\";}s:16:\"references_table\";s:9:\"companies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:27:\"incomes_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:4;a:6:{s:4:\"name\";s:27:\"incomes_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:5;a:6:{s:4:\"name\";s:26:\"incomes_invoice_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"invoice_id\";}s:16:\"references_table\";s:8:\"invoices\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:6;a:6:{s:4:\"name\";s:38:\"incomes_pending_transaction_id_foreign\";s:7:\"columns\";a:1:{i:0;s:22:\"pending_transaction_id\";}s:16:\"references_table\";s:20:\"pending_transactions\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:7;a:6:{s:4:\"name\";s:27:\"incomes_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:8;a:6:{s:4:\"name\";s:26:\"incomes_visitor_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"visitor_id\";}s:16:\"references_table\";s:8:\"visitors\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:46;a:5:{s:4:\"name\";s:8:\"invoices\";s:7:\"columns\";a:35:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"recorded_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:14:\"invoice_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:16:\"base_currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:6:\"deptor\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'Issued\'\";}i:8;a:4:{s:4:\"name\";s:10:\"issue_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:8:\"due_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:11:\"tax_percent\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:4:\"0.00\";}i:11;a:4:{s:4:\"name\";s:13:\"currency_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:12;a:4:{s:4:\"name\";s:15:\"currency_symbol\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:13;a:4:{s:4:\"name\";s:18:\"currency_shortname\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:14;a:4:{s:4:\"name\";s:8:\"subtotal\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"0.000000\";}i:15;a:4:{s:4:\"name\";s:6:\"amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"0.000000\";}i:16;a:4:{s:4:\"name\";s:8:\"discount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"0.000000\";}i:17;a:4:{s:4:\"name\";s:5:\"notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:16:\"customer_company\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:14:\"customer_email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:20;a:4:{s:4:\"name\";s:16:\"customer_contact\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:21;a:4:{s:4:\"name\";s:16:\"customer_address\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:22;a:4:{s:4:\"name\";s:19:\"customer_tax_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:23;a:4:{s:4:\"name\";s:17:\"payment_detail_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:24;a:4:{s:4:\"name\";s:14:\"payment_method\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:25;a:4:{s:4:\"name\";s:20:\"payment_instructions\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:26;a:4:{s:4:\"name\";s:9:\"voided_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:27;a:4:{s:4:\"name\";s:11:\"void_reason\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:28;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:29;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:30;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:31;a:4:{s:4:\"name\";s:13:\"cnv_curr_shrt\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:32;a:4:{s:4:\"name\";s:14:\"base_curr_shrt\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:33;a:4:{s:4:\"name\";s:4:\"rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:34;a:4:{s:4:\"name\";s:11:\"base_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:6:{i:0;a:3:{s:4:\"name\";s:25:\"invoices_applicant_id_idx\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:33:\"invoices_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:28:\"invoices_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:30:\"invoices_invoice_number_unique\";s:7:\"columns\";a:1:{i:0;s:14:\"invoice_number\";}s:6:\"unique\";b:1;}i:4;a:3:{s:4:\"name\";s:34:\"invoices_payment_detail_id_foreign\";s:7:\"columns\";a:1:{i:0;s:17:\"payment_detail_id\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:26:\"invoices_recorded_by_index\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:5:{i:0;a:6:{s:4:\"name\";s:29:\"invoices_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:1;a:6:{s:4:\"name\";s:33:\"invoices_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:28:\"invoices_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:3;a:6:{s:4:\"name\";s:34:\"invoices_payment_detail_id_foreign\";s:7:\"columns\";a:1:{i:0;s:17:\"payment_detail_id\";}s:16:\"references_table\";s:15:\"payment_details\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:28:\"invoices_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:47;a:5:{s:4:\"name\";s:15:\"invoice_service\";s:7:\"columns\";a:24:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:10:\"invoice_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:20:\"marketer_employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:22:\"outsourcing_company_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:31:\"snapshot_outsourcing_percentage\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:30:\"snapshot_commission_percentage\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:39:\"snapshot_marketer_commission_percentage\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:13:\"snapshot_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:10;a:4:{s:4:\"name\";s:20:\"snapshot_service_fee\";s:4:\"type\";s:13:\"decimal(14,2)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:11;a:4:{s:4:\"name\";s:30:\"snapshot_service_currency_code\";s:4:\"type\";s:10:\"varchar(3)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:12;a:4:{s:4:\"name\";s:32:\"snapshot_service_currency_symbol\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:13;a:4:{s:4:\"name\";s:21:\"snapshot_service_rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:22:\"snapshot_converted_fee\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:15;a:4:{s:4:\"name\";s:8:\"quantity\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:16;a:4:{s:4:\"name\";s:10:\"line_total\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:17;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:22:\"snapshot_cnv_curr_shrt\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:20;a:4:{s:4:\"name\";s:23:\"snapshot_base_curr_shrt\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:21;a:4:{s:4:\"name\";s:13:\"snapshot_rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:22;a:4:{s:4:\"name\";s:21:\"snapshot_invoice_rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:23;a:4:{s:4:\"name\";s:19:\"contract_service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:6:{i:0;a:3:{s:4:\"name\";s:43:\"invoice_service_contract_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"contract_service_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:44:\"invoice_service_employee_id_service_id_index\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:10:\"service_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:44:\"invoice_service_invoice_id_service_id_unique\";s:7:\"columns\";a:2:{i:0;s:10:\"invoice_id\";i:1;s:10:\"service_id\";}s:6:\"unique\";b:1;}i:3;a:3:{s:4:\"name\";s:37:\"invoice_service_marketer_employee_idx\";s:7:\"columns\";a:1:{i:0;s:20:\"marketer_employee_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:39:\"invoice_service_outsourcing_company_idx\";s:7:\"columns\";a:1:{i:0;s:22:\"outsourcing_company_id\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:34:\"invoice_service_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:6:{i:0;a:6:{s:4:\"name\";s:43:\"invoice_service_contract_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"contract_service_id\";}s:16:\"references_table\";s:16:\"contract_service\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:35:\"invoice_service_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:2;a:6:{s:4:\"name\";s:34:\"invoice_service_invoice_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"invoice_id\";}s:16:\"references_table\";s:8:\"invoices\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:3;a:6:{s:4:\"name\";s:44:\"invoice_service_marketer_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:20:\"marketer_employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:46:\"invoice_service_outsourcing_company_id_foreign\";s:7:\"columns\";a:1:{i:0;s:22:\"outsourcing_company_id\";}s:16:\"references_table\";s:9:\"companies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:5;a:6:{s:4:\"name\";s:34:\"invoice_service_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:16:\"references_table\";s:8:\"services\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:48;a:5:{s:4:\"name\";s:37:\"invoice_service_commission_recipients\";s:7:\"columns\";a:6:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:18:\"invoice_service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:10:\"percentage\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:57:\"invoice_service_commission_recipients_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:43:\"invoice_service_commission_recipient_unique\";s:7:\"columns\";a:2:{i:0;s:18:\"invoice_service_id\";i:1;s:11:\"employee_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:57:\"invoice_service_commission_recipients_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:1;a:6:{s:4:\"name\";s:64:\"invoice_service_commission_recipients_invoice_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"invoice_service_id\";}s:16:\"references_table\";s:15:\"invoice_service\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:49;a:5:{s:4:\"name\";s:4:\"jobs\";s:7:\"columns\";a:7:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:5:\"queue\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:7:\"payload\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:8:\"attempts\";s:4:\"type\";s:19:\"tinyint(3) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:11:\"reserved_at\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:12:\"available_at\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:16:\"jobs_queue_index\";s:7:\"columns\";a:1:{i:0;s:5:\"queue\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:0:{}}i:50;a:5:{s:4:\"name\";s:11:\"job_batches\";s:7:\"columns\";a:10:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"total_jobs\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:12:\"pending_jobs\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:11:\"failed_jobs\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:14:\"failed_job_ids\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:7:\"options\";s:4:\"type\";s:10:\"mediumtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:12:\"cancelled_at\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:9;a:4:{s:4:\"name\";s:11:\"finished_at\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:0:{}s:12:\"foreign_keys\";a:0:{}}i:51;a:5:{s:4:\"name\";s:9:\"languages\";s:7:\"columns\";a:7:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:4:\"code\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:4;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:21:\"languages_code_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"code\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:21:\"languages_name_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"name\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:52;a:5:{s:4:\"name\";s:5:\"leads\";s:7:\"columns\";a:21:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:10:\"visitor_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:7:\"company\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:7:\"contact\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:5:\"email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:16:\"service_interest\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:8:\"marketer\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:11:\"marketer_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:5:\"stage\";s:4:\"type\";s:59:\"enum(\'new\',\'contacted\',\'qualified\',\'proposal\',\'won\',\'lost\')\";s:8:\"nullable\";b:0;s:7:\"default\";s:5:\"\'new\'\";}i:11;a:4:{s:4:\"name\";s:11:\"probability\";s:4:\"type\";s:19:\"tinyint(3) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:2:\"10\";}i:12;a:4:{s:4:\"name\";s:21:\"probability_is_manual\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:13;a:4:{s:4:\"name\";s:15:\"estimated_value\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:17:\"next_follow_up_at\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:5:\"notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:12:\"converted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:8:\"owner_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:20;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:5:{i:0;a:3:{s:4:\"name\";s:25:\"leads_applicant_id_unique\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:21:\"leads_marketer_id_idx\";s:7:\"columns\";a:1:{i:0;s:11:\"marketer_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:22:\"leads_owner_id_foreign\";s:7:\"columns\";a:1:{i:0;s:8:\"owner_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:35:\"leads_stage_next_follow_up_at_index\";s:7:\"columns\";a:2:{i:0;s:5:\"stage\";i:1;s:17:\"next_follow_up_at\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:24:\"leads_visitor_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"visitor_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:4:{i:0;a:6:{s:4:\"name\";s:26:\"leads_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:25:\"leads_marketer_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"marketer_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:22:\"leads_owner_id_foreign\";s:7:\"columns\";a:1:{i:0;s:8:\"owner_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:24:\"leads_visitor_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"visitor_id\";}s:16:\"references_table\";s:8:\"visitors\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:53;a:5:{s:4:\"name\";s:6:\"leaves\";s:7:\"columns\";a:17:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:4:\"date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:4:\"year\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:13:\"duration_days\";s:4:\"type\";s:12:\"decimal(3,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"1.0\";}i:5;a:4:{s:4:\"name\";s:6:\"reason\";s:4:\"type\";s:13:\"varchar(1000)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:13:\"approval_note\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"is_granted\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:8;a:4:{s:4:\"name\";s:7:\"is_paid\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:9:\"paid_days\";s:4:\"type\";s:12:\"decimal(3,1)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:13:\"paid_override\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:11;a:4:{s:4:\"name\";s:12:\"requested_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"granted_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:10:\"granted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:4:{i:0;a:3:{s:4:\"name\";s:38:\"paid_time_offs_employee_id_date_unique\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:4:\"date\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:37:\"paid_time_offs_employee_id_year_index\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:4:\"year\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:33:\"paid_time_offs_granted_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"granted_by\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:35:\"paid_time_offs_requested_by_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"requested_by\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:3:{i:0;a:6:{s:4:\"name\";s:34:\"paid_time_offs_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:33:\"paid_time_offs_granted_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"granted_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:35:\"paid_time_offs_requested_by_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"requested_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:54;a:5:{s:4:\"name\";s:9:\"marketers\";s:7:\"columns\";a:11:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:5:\"email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:5:\"phone\";s:4:\"type\";s:11:\"varchar(50)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:25:\"enum(\'active\',\'inactive\')\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'active\'\";}i:6;a:4:{s:4:\"name\";s:5:\"notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:28:\"marketers_employee_id_unique\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:24:\"marketers_user_id_unique\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:29:\"marketers_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:25:\"marketers_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:55;a:5:{s:4:\"name\";s:10:\"migrations\";s:7:\"columns\";a:3:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:9:\"migration\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:5:\"batch\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:0:{}s:12:\"foreign_keys\";a:0:{}}i:56;a:5:{s:4:\"name\";s:17:\"monthly_analytics\";s:7:\"columns\";a:22:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"year\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:5:\"month\";s:4:\"type\";s:19:\"tinyint(3) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:18:\"attendance_present\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:4;a:4:{s:4:\"name\";s:17:\"attendance_absent\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:5;a:4:{s:4:\"name\";s:21:\"attendance_paid_leave\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:6;a:4:{s:4:\"name\";s:21:\"attendance_incomplete\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:7;a:4:{s:4:\"name\";s:14:\"worked_minutes\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:8;a:4:{s:4:\"name\";s:16:\"overtime_minutes\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:9;a:4:{s:4:\"name\";s:12:\"income_total\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"0.0000\";}i:10;a:4:{s:4:\"name\";s:13:\"expense_total\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"0.0000\";}i:11;a:4:{s:4:\"name\";s:13:\"tasks_created\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:12;a:4:{s:4:\"name\";s:15:\"tasks_completed\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:13;a:4:{s:4:\"name\";s:17:\"contracts_created\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:14;a:4:{s:4:\"name\";s:19:\"contracts_cancelled\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:15;a:4:{s:4:\"name\";s:19:\"employees_onboarded\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:16;a:4:{s:4:\"name\";s:17:\"bonus_nominations\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:17;a:4:{s:4:\"name\";s:15:\"bonuses_granted\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:18;a:4:{s:4:\"name\";s:16:\"bonus_base_total\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"0.0000\";}i:19;a:4:{s:4:\"name\";s:13:\"calculated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:0;s:7:\"default\";s:19:\"current_timestamp()\";}i:20;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:21;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:35:\"monthly_analytics_year_month_unique\";s:7:\"columns\";a:2:{i:0;s:4:\"year\";i:1;s:5:\"month\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:57;a:5:{s:4:\"name\";s:28:\"monthly_attendance_summaries\";s:7:\"columns\";a:19:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:4:\"year\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:5:\"month\";s:4:\"type\";s:19:\"tinyint(3) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:12:\"present_days\";s:4:\"type\";s:12:\"decimal(5,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"0.0\";}i:5;a:4:{s:4:\"name\";s:11:\"absent_days\";s:4:\"type\";s:12:\"decimal(5,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"0.0\";}i:6;a:4:{s:4:\"name\";s:15:\"paid_leave_days\";s:4:\"type\";s:12:\"decimal(5,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"0.0\";}i:7;a:4:{s:4:\"name\";s:17:\"unpaid_leave_days\";s:4:\"type\";s:12:\"decimal(5,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"0.0\";}i:8;a:4:{s:4:\"name\";s:15:\"incomplete_days\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:9;a:4:{s:4:\"name\";s:13:\"gross_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:10;a:4:{s:4:\"name\";s:13:\"break_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:11;a:4:{s:4:\"name\";s:18:\"net_worked_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:12;a:4:{s:4:\"name\";s:16:\"required_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:13;a:4:{s:4:\"name\";s:20:\"late_arrival_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:14;a:4:{s:4:\"name\";s:23:\"early_departure_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:15;a:4:{s:4:\"name\";s:16:\"overtime_minutes\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:16;a:4:{s:4:\"name\";s:13:\"calculated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:0;s:7:\"default\";s:19:\"current_timestamp()\";}i:17;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:41:\"monthly_attendance_employee_period_unique\";s:7:\"columns\";a:3:{i:0;s:11:\"employee_id\";i:1;s:4:\"year\";i:2;s:5:\"month\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:45:\"monthly_attendance_summaries_year_month_index\";s:7:\"columns\";a:2:{i:0;s:4:\"year\";i:1;s:5:\"month\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:48:\"monthly_attendance_summaries_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:58;a:5:{s:4:\"name\";s:13:\"notifications\";s:7:\"columns\";a:11:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"type\";s:4:\"type\";s:11:\"varchar(50)\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"\'info\'\";}i:2;a:4:{s:4:\"name\";s:16:\"notification_key\";s:4:\"type\";s:12:\"varchar(100)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:5:\"title\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:7:\"message\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:4:\"data\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:9:\"date_time\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:0;s:7:\"default\";s:19:\"current_timestamp()\";}i:7;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:9:\"go_to_url\";s:4:\"type\";s:13:\"varchar(2048)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:41:\"notifications_employee_id_date_time_index\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:9:\"date_time\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:46:\"notifications_notification_key_date_time_index\";s:7:\"columns\";a:2:{i:0;s:16:\"notification_key\";i:1;s:9:\"date_time\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:33:\"notifications_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:59;a:5:{s:4:\"name\";s:17:\"notification_user\";s:7:\"columns\";a:7:{i:0;a:4:{s:4:\"name\";s:15:\"notification_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:7:\"is_read\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:7:\"read_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:0:{}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:48:\"notification_user_notification_id_user_id_unique\";s:7:\"columns\";a:2:{i:0;s:15:\"notification_id\";i:1;s:7:\"user_id\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:50:\"notification_user_user_id_deleted_at_is_read_index\";s:7:\"columns\";a:3:{i:0;s:7:\"user_id\";i:1;s:10:\"deleted_at\";i:2;s:7:\"is_read\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:41:\"notification_user_notification_id_foreign\";s:7:\"columns\";a:1:{i:0;s:15:\"notification_id\";}s:16:\"references_table\";s:13:\"notifications\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:33:\"notification_user_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:60;a:5:{s:4:\"name\";s:7:\"offices\";s:7:\"columns\";a:15:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:12:\"is_singleton\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:2;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:4:\"code\";s:4:\"type\";s:11:\"varchar(30)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:5:\"email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:5:\"phone\";s:4:\"type\";s:11:\"varchar(50)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:7:\"address\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:8:\"latitude\";s:4:\"type\";s:13:\"decimal(10,7)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:9:\"longitude\";s:4:\"type\";s:13:\"decimal(10,7)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:22:\"geofence_radius_meters\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"100\";}i:10;a:4:{s:4:\"name\";s:16:\"geofence_enabled\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:11;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:25:\"enum(\'active\',\'inactive\')\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'active\'\";}i:12;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:27:\"offices_is_singleton_unique\";s:7:\"columns\";a:1:{i:0;s:12:\"is_singleton\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:61;a:5:{s:4:\"name\";s:20:\"outsourcing_accruals\";s:7:\"columns\";a:21:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:9:\"income_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:10:\"invoice_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:18:\"invoice_service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"company_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:10:\"service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:19:\"contract_service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:16:\"base_currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:8;a:4:{s:4:\"name\";s:25:\"outsourcing_settlement_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:9:\"earned_at\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:10;a:4:{s:4:\"name\";s:26:\"source_payment_base_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:11;a:4:{s:4:\"name\";s:20:\"eligible_base_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:12;a:4:{s:4:\"name\";s:10:\"percentage\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:13;a:4:{s:4:\"name\";s:23:\"outsourcing_base_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:14;a:4:{s:4:\"name\";s:10:\"entry_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'earned\'\";}i:15;a:4:{s:4:\"name\";s:17:\"adjustment_reason\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:11:\"recorded_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'pending\'\";}i:18;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:20;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:9:{i:0;a:3:{s:4:\"name\";s:45:\"outsourcing_accruals_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:48:\"outsourcing_accruals_contract_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"contract_service_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:39:\"outsourcing_accruals_invoice_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"invoice_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:47:\"outsourcing_accruals_invoice_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"invoice_service_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:54:\"outsourcing_accruals_outsourcing_settlement_id_foreign\";s:7:\"columns\";a:1:{i:0;s:25:\"outsourcing_settlement_id\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:40:\"outsourcing_accruals_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:6:\"unique\";b:0;}i:6;a:3:{s:4:\"name\";s:39:\"outsourcing_accruals_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:6:\"unique\";b:0;}i:7;a:3:{s:4:\"name\";s:38:\"outsourcing_accrual_company_status_idx\";s:7:\"columns\";a:3:{i:0;s:10:\"company_id\";i:1;s:6:\"status\";i:2;s:9:\"earned_at\";}s:6:\"unique\";b:0;}i:8;a:3:{s:4:\"name\";s:33:\"outsourcing_accrual_source_unique\";s:7:\"columns\";a:2:{i:0;s:9:\"income_id\";i:1;s:18:\"invoice_service_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:9:{i:0;a:6:{s:4:\"name\";s:45:\"outsourcing_accruals_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:1;a:6:{s:4:\"name\";s:39:\"outsourcing_accruals_company_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"company_id\";}s:16:\"references_table\";s:9:\"companies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:2;a:6:{s:4:\"name\";s:48:\"outsourcing_accruals_contract_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"contract_service_id\";}s:16:\"references_table\";s:16:\"contract_service\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:38:\"outsourcing_accruals_income_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"income_id\";}s:16:\"references_table\";s:7:\"incomes\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:4;a:6:{s:4:\"name\";s:39:\"outsourcing_accruals_invoice_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"invoice_id\";}s:16:\"references_table\";s:8:\"invoices\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:5;a:6:{s:4:\"name\";s:47:\"outsourcing_accruals_invoice_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"invoice_service_id\";}s:16:\"references_table\";s:15:\"invoice_service\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:6;a:6:{s:4:\"name\";s:54:\"outsourcing_accruals_outsourcing_settlement_id_foreign\";s:7:\"columns\";a:1:{i:0;s:25:\"outsourcing_settlement_id\";}s:16:\"references_table\";s:23:\"outsourcing_settlements\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:7;a:6:{s:4:\"name\";s:40:\"outsourcing_accruals_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:8;a:6:{s:4:\"name\";s:39:\"outsourcing_accruals_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:16:\"references_table\";s:8:\"services\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:62;a:5:{s:4:\"name\";s:23:\"outsourcing_settlements\";s:7:\"columns\";a:19:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:17:\"settlement_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:10:\"company_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:16:\"base_currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:12:\"period_start\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:10:\"period_end\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:17:\"gross_base_amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:7:\"\'draft\'\";}i:8;a:4:{s:4:\"name\";s:5:\"notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:11:\"recorded_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:11:\"approved_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:11:\"approved_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:7:\"paid_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:12:\"payment_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:7:\"paid_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:10:\"expense_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:7:{i:0;a:3:{s:4:\"name\";s:43:\"outsourcing_settlements_approved_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"approved_by\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:48:\"outsourcing_settlements_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:41:\"outsourcing_settlements_expense_id_unique\";s:7:\"columns\";a:1:{i:0;s:10:\"expense_id\";}s:6:\"unique\";b:1;}i:3;a:3:{s:4:\"name\";s:39:\"outsourcing_settlements_paid_by_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"paid_by\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:43:\"outsourcing_settlements_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:48:\"outsourcing_settlements_settlement_number_unique\";s:7:\"columns\";a:1:{i:0;s:17:\"settlement_number\";}s:6:\"unique\";b:1;}i:6;a:3:{s:4:\"name\";s:33:\"outsourcing_settlement_period_idx\";s:7:\"columns\";a:4:{i:0;s:10:\"company_id\";i:1;s:6:\"status\";i:2;s:12:\"period_start\";i:3;s:10:\"period_end\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:6:{i:0;a:6:{s:4:\"name\";s:43:\"outsourcing_settlements_approved_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"approved_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:48:\"outsourcing_settlements_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:2;a:6:{s:4:\"name\";s:42:\"outsourcing_settlements_company_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"company_id\";}s:16:\"references_table\";s:9:\"companies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:3;a:6:{s:4:\"name\";s:42:\"outsourcing_settlements_expense_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"expense_id\";}s:16:\"references_table\";s:8:\"expenses\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:39:\"outsourcing_settlements_paid_by_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"paid_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:5;a:6:{s:4:\"name\";s:43:\"outsourcing_settlements_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:63;a:5:{s:4:\"name\";s:15:\"package_service\";s:7:\"columns\";a:7:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:10:\"package_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:13:\"allocated_fee\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"sort_order\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:5;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:44:\"package_service_package_id_service_id_unique\";s:7:\"columns\";a:2:{i:0;s:10:\"package_id\";i:1;s:10:\"service_id\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:34:\"package_service_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:34:\"package_service_package_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"package_id\";}s:16:\"references_table\";s:13:\"package_types\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:34:\"package_service_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:16:\"references_table\";s:8:\"services\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:64;a:5:{s:4:\"name\";s:13:\"package_types\";s:7:\"columns\";a:11:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:12:\"service_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:5;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:3:\"fee\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:16:\"service_manifest\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:33:\"package_types_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:25:\"package_types_name_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"name\";}s:6:\"unique\";b:1;}i:2;a:3:{s:4:\"name\";s:32:\"package_types_service_type_index\";s:7:\"columns\";a:1:{i:0;s:12:\"service_type\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:33:\"package_types_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:65;a:5:{s:4:\"name\";s:21:\"password_reset_tokens\";s:7:\"columns\";a:3:{i:0;a:4:{s:4:\"name\";s:5:\"email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:5:\"token\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:5:\"email\";}s:7:\"indexes\";a:0:{}s:12:\"foreign_keys\";a:0:{}}i:66;a:5:{s:4:\"name\";s:15:\"payment_details\";s:7:\"columns\";a:6:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:6:\"method\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"instruction\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:28:\"payment_details_method_index\";s:7:\"columns\";a:1:{i:0;s:6:\"method\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:0:{}}i:67;a:5:{s:4:\"name\";s:20:\"pending_transactions\";s:7:\"columns\";a:20:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:16:\"transaction_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:6:\"reason\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:6:\"amount\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:12:\"requested_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:11:\"approver_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:15:\"approval_status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:7:\"\'draft\'\";}i:10;a:4:{s:4:\"name\";s:16:\"rejection_reason\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:12:\"requested_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:11:\"approved_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:11:\"rejected_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:9:\"posted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:18:\"posted_record_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:16:\"posted_record_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:7:{i:0;a:3:{s:4:\"name\";s:41:\"pending_transactions_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:59:\"pending_transactions_approval_status_transaction_type_index\";s:7:\"columns\";a:2:{i:0;s:15:\"approval_status\";i:1;s:16:\"transaction_type\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:40:\"pending_transactions_approver_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"approver_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:40:\"pending_transactions_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:40:\"pending_transactions_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:62:\"pending_transactions_posted_record_type_posted_record_id_index\";s:7:\"columns\";a:2:{i:0;s:18:\"posted_record_type\";i:1;s:16:\"posted_record_id\";}s:6:\"unique\";b:0;}i:6;a:3:{s:4:\"name\";s:41:\"pending_transactions_requested_by_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"requested_by\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:5:{i:0;a:6:{s:4:\"name\";s:41:\"pending_transactions_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:40:\"pending_transactions_approver_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"approver_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:40:\"pending_transactions_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:3;a:6:{s:4:\"name\";s:40:\"pending_transactions_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:41:\"pending_transactions_requested_by_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"requested_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:68;a:5:{s:4:\"name\";s:11:\"permissions\";s:7:\"columns\";a:7:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:8:\"category\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'general\'\";}i:3;a:4:{s:4:\"name\";s:10:\"guard_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:5:\"\'web\'\";}i:4;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:23:\"permissions_name_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"name\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:69;a:5:{s:4:\"name\";s:22:\"personal_access_tokens\";s:7:\"columns\";a:10:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:14:\"tokenable_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:12:\"tokenable_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:5:\"token\";s:4:\"type\";s:11:\"varchar(64)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:9:\"abilities\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:12:\"last_used_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"expires_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:39:\"personal_access_tokens_expires_at_index\";s:7:\"columns\";a:1:{i:0;s:10:\"expires_at\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:56:\"personal_access_tokens_tokenable_type_tokenable_id_index\";s:7:\"columns\";a:2:{i:0;s:14:\"tokenable_type\";i:1;s:12:\"tokenable_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:35:\"personal_access_tokens_token_unique\";s:7:\"columns\";a:1:{i:0;s:5:\"token\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:70;a:5:{s:4:\"name\";s:9:\"positions\";s:7:\"columns\";a:14:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:5:\"title\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:6:\"salary\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:13:\"currency_code\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:15:\"currency_symbol\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:23:\"currency_decimal_places\";s:4:\"type\";s:19:\"tinyint(3) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:4:\"rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:10;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:10:\"system_key\";s:4:\"type\";s:11:\"varchar(80)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:29:\"positions_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:27:\"positions_system_key_unique\";s:7:\"columns\";a:1:{i:0;s:10:\"system_key\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:29:\"positions_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:71;a:5:{s:4:\"name\";s:13:\"position_user\";s:7:\"columns\";a:7:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"position_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:11:\"assigned_at\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:8:\"ended_at\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:40:\"position_user_position_id_user_id_unique\";s:7:\"columns\";a:2:{i:0;s:11:\"position_id\";i:1;s:7:\"user_id\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:29:\"position_user_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:33:\"position_user_position_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"position_id\";}s:16:\"references_table\";s:9:\"positions\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:29:\"position_user_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:72;a:5:{s:4:\"name\";s:8:\"projects\";s:7:\"columns\";a:11:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:11:\"varchar(30)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'active\'\";}i:5;a:4:{s:4:\"name\";s:10:\"start_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:8:\"due_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"created_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:25:\"projects_applicant_id_idx\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:27:\"projects_created_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"created_by\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:33:\"projects_customer_id_status_index\";s:7:\"columns\";a:1:{i:0;s:6:\"status\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:29:\"projects_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:27:\"projects_created_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"created_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:73;a:5:{s:4:\"name\";s:15:\"project_service\";s:7:\"columns\";a:4:{i:0;a:4:{s:4:\"name\";s:10:\"project_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:10:\"service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:2:{i:0;s:10:\"project_id\";i:1;s:10:\"service_id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:34:\"project_service_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:34:\"project_service_project_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"project_id\";}s:16:\"references_table\";s:8:\"projects\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:34:\"project_service_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:16:\"references_table\";s:8:\"services\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:74;a:5:{s:4:\"name\";s:16:\"recurring_events\";s:7:\"columns\";a:15:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:8:\"owner_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"event_type\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'active\'\";}i:6;a:4:{s:4:\"name\";s:9:\"frequency\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:8:\"interval\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:8;a:4:{s:4:\"name\";s:10:\"start_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:9;a:4:{s:4:\"name\";s:20:\"next_occurrence_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:10;a:4:{s:4:\"name\";s:20:\"last_occurrence_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:8:\"end_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:7:\"payload\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:13;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:33:\"recurring_events_owner_id_foreign\";s:7:\"columns\";a:1:{i:0;s:8:\"owner_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:50:\"recurring_events_status_next_occurrence_date_index\";s:7:\"columns\";a:2:{i:0;s:6:\"status\";i:1;s:20:\"next_occurrence_date\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:33:\"recurring_events_owner_id_foreign\";s:7:\"columns\";a:1:{i:0;s:8:\"owner_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:75;a:5:{s:4:\"name\";s:27:\"recurring_event_occurrences\";s:7:\"columns\";a:12:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:18:\"recurring_event_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:15:\"occurrence_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'pending\'\";}i:4;a:4:{s:4:\"name\";s:15:\"notification_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:11:\"result_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:9:\"result_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:11:\"notified_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:12:\"completed_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"skipped_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:4:{i:0;a:3:{s:4:\"name\";s:51:\"recurring_event_occurrences_notification_id_foreign\";s:7:\"columns\";a:1:{i:0;s:15:\"notification_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:55:\"recurring_event_occurrences_result_type_result_id_index\";s:7:\"columns\";a:2:{i:0;s:11:\"result_type\";i:1;s:9:\"result_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:56:\"recurring_event_occurrences_status_occurrence_date_index\";s:7:\"columns\";a:2:{i:0;s:6:\"status\";i:1;s:15:\"occurrence_date\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:32:\"rec_event_occurrence_date_unique\";s:7:\"columns\";a:2:{i:0;s:18:\"recurring_event_id\";i:1;s:15:\"occurrence_date\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:51:\"recurring_event_occurrences_notification_id_foreign\";s:7:\"columns\";a:1:{i:0;s:15:\"notification_id\";}s:16:\"references_table\";s:13:\"notifications\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:54:\"recurring_event_occurrences_recurring_event_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"recurring_event_id\";}s:16:\"references_table\";s:16:\"recurring_events\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:76;a:5:{s:4:\"name\";s:5:\"roles\";s:7:\"columns\";a:5:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:5:\"level\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:3;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:17:\"roles_level_index\";s:7:\"columns\";a:1:{i:0;s:5:\"level\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:17:\"roles_name_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"name\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:77;a:5:{s:4:\"name\";s:16:\"role_permissions\";s:7:\"columns\";a:5:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"role_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:13:\"permission_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:38:\"role_permissions_permission_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"permission_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:30:\"role_permissions_role_id_index\";s:7:\"columns\";a:1:{i:0;s:7:\"role_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:45:\"role_permissions_role_id_permission_id_unique\";s:7:\"columns\";a:2:{i:0;s:7:\"role_id\";i:1;s:13:\"permission_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:38:\"role_permissions_permission_id_foreign\";s:7:\"columns\";a:1:{i:0;s:13:\"permission_id\";}s:16:\"references_table\";s:11:\"permissions\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:32:\"role_permissions_role_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"role_id\";}s:16:\"references_table\";s:5:\"roles\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:78;a:5:{s:4:\"name\";s:8:\"salaries\";s:7:\"columns\";a:34:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"recorded_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:14:\"payslip_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:16:\"base_currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:12:\"salary_month\";s:4:\"type\";s:10:\"varchar(7)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:11:\"amount_paid\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:8;a:4:{s:4:\"name\";s:11:\"worked_days\";s:4:\"type\";s:12:\"decimal(5,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:4:\"30.0\";}i:9;a:4:{s:4:\"name\";s:10:\"total_days\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";s:2:\"30\";}i:10;a:4:{s:4:\"name\";s:15:\"paid_leave_days\";s:4:\"type\";s:12:\"decimal(5,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"0.0\";}i:11;a:4:{s:4:\"name\";s:11:\"absent_days\";s:4:\"type\";s:12:\"decimal(5,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"0.0\";}i:12;a:4:{s:4:\"name\";s:17:\"unpaid_leave_days\";s:4:\"type\";s:12:\"decimal(5,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"0.0\";}i:13;a:4:{s:4:\"name\";s:5:\"bonus\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";s:10:\"0.00000000\";}i:14;a:4:{s:4:\"name\";s:12:\"total_amount\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:15;a:4:{s:4:\"name\";s:10:\"amount_due\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:17:\"amount_paid_total\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";s:10:\"0.00000000\";}i:17;a:4:{s:4:\"name\";s:14:\"payment_status\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"\'open\'\";}i:18;a:4:{s:4:\"name\";s:7:\"remarks\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:12:\"payment_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:20;a:4:{s:4:\"name\";s:13:\"currency_code\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:21;a:4:{s:4:\"name\";s:13:\"currency_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:22;a:4:{s:4:\"name\";s:15:\"currency_symbol\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:23;a:4:{s:4:\"name\";s:23:\"currency_decimal_places\";s:4:\"type\";s:19:\"tinyint(3) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:24;a:4:{s:4:\"name\";s:24:\"currency_snapshot_source\";s:4:\"type\";s:11:\"varchar(40)\";s:8:\"nullable\";b:0;s:7:\"default\";s:22:\"\'recorded_at_creation\'\";}i:25;a:4:{s:4:\"name\";s:22:\"currency_snapshot_note\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:26;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:27;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:28;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:29;a:4:{s:4:\"name\";s:13:\"cnv_curr_shrt\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:30;a:4:{s:4:\"name\";s:14:\"base_curr_shrt\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:31;a:4:{s:4:\"name\";s:4:\"rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:32;a:4:{s:4:\"name\";s:11:\"base_amount\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:33;a:4:{s:4:\"name\";s:22:\"pending_transaction_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:6:{i:0;a:3:{s:4:\"name\";s:33:\"salaries_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:28:\"salaries_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:30:\"salaries_employee_month_unique\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:12:\"salary_month\";}s:6:\"unique\";b:1;}i:3;a:3:{s:4:\"name\";s:30:\"salaries_payslip_number_unique\";s:7:\"columns\";a:1:{i:0;s:14:\"payslip_number\";}s:6:\"unique\";b:1;}i:4;a:3:{s:4:\"name\";s:38:\"salaries_pending_transaction_id_unique\";s:7:\"columns\";a:1:{i:0;s:22:\"pending_transaction_id\";}s:6:\"unique\";b:1;}i:5;a:3:{s:4:\"name\";s:26:\"salaries_recorded_by_index\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:5:{i:0;a:6:{s:4:\"name\";s:33:\"salaries_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:28:\"salaries_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:2;a:6:{s:4:\"name\";s:28:\"salaries_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:3;a:6:{s:4:\"name\";s:39:\"salaries_pending_transaction_id_foreign\";s:7:\"columns\";a:1:{i:0;s:22:\"pending_transaction_id\";}s:16:\"references_table\";s:20:\"pending_transactions\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:28:\"salaries_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:79;a:5:{s:4:\"name\";s:17:\"salary_deductions\";s:7:\"columns\";a:7:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:9:\"salary_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:6:\"amount\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:10:\"sort_order\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:5;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:35:\"salary_deductions_salary_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"salary_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:35:\"salary_deductions_salary_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"salary_id\";}s:16:\"references_table\";s:8:\"salaries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:80;a:5:{s:4:\"name\";s:15:\"salary_payments\";s:7:\"columns\";a:12:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:14:\"payment_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:9:\"salary_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:10:\"expense_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:22:\"pending_transaction_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:6:\"amount\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:12:\"payment_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:7:\"remarks\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:11:\"recorded_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:5:{i:0;a:3:{s:4:\"name\";s:33:\"salary_payments_expense_id_unique\";s:7:\"columns\";a:1:{i:0;s:10:\"expense_id\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:37:\"salary_payments_payment_number_unique\";s:7:\"columns\";a:1:{i:0;s:14:\"payment_number\";}s:6:\"unique\";b:1;}i:2;a:3:{s:4:\"name\";s:45:\"salary_payments_pending_transaction_id_unique\";s:7:\"columns\";a:1:{i:0;s:22:\"pending_transaction_id\";}s:6:\"unique\";b:1;}i:3;a:3:{s:4:\"name\";s:35:\"salary_payments_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:44:\"salary_payments_salary_id_payment_date_index\";s:7:\"columns\";a:2:{i:0;s:9:\"salary_id\";i:1;s:12:\"payment_date\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:4:{i:0;a:6:{s:4:\"name\";s:34:\"salary_payments_expense_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"expense_id\";}s:16:\"references_table\";s:8:\"expenses\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:46:\"salary_payments_pending_transaction_id_foreign\";s:7:\"columns\";a:1:{i:0;s:22:\"pending_transaction_id\";}s:16:\"references_table\";s:20:\"pending_transactions\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:35:\"salary_payments_recorded_by_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"recorded_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:3;a:6:{s:4:\"name\";s:33:\"salary_payments_salary_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"salary_id\";}s:16:\"references_table\";s:8:\"salaries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:81;a:5:{s:4:\"name\";s:22:\"salary_worthy_employee\";s:7:\"columns\";a:12:{i:0;a:4:{s:4:\"name\";s:9:\"salary_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:18:\"worthy_employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:13:\"source_amount\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:20:\"source_currency_code\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:11:\"source_rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:20:\"salary_currency_code\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:11:\"salary_rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:16:\"converted_amount\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:18:\"conversion_formula\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:26:\"conversion_snapshot_source\";s:4:\"type\";s:11:\"varchar(40)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:2:{i:0;s:9:\"salary_id\";i:1;s:18:\"worthy_employee_id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:48:\"salary_worthy_employee_worthy_employee_id_unique\";s:7:\"columns\";a:1:{i:0;s:18:\"worthy_employee_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:40:\"salary_worthy_employee_salary_id_foreign\";s:7:\"columns\";a:1:{i:0;s:9:\"salary_id\";}s:16:\"references_table\";s:8:\"salaries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:49:\"salary_worthy_employee_worthy_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"worthy_employee_id\";}s:16:\"references_table\";s:16:\"worthy_employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:82;a:5:{s:4:\"name\";s:12:\"scholarships\";s:7:\"columns\";a:14:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:10:\"country_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:18:\"education_level_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:11:\"language_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:15:\"package_type_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:4:\"date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:9;a:4:{s:4:\"name\";s:3:\"fee\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:10;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'active\'\";}i:11;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:7:{i:0;a:3:{s:4:\"name\";s:33:\"scholarships_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:53:\"scholarships_country_id_education_level_id_date_index\";s:7:\"columns\";a:3:{i:0;s:10:\"country_id\";i:1;s:18:\"education_level_id\";i:2;s:4:\"date\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:32:\"scholarships_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:39:\"scholarships_education_level_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"education_level_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:42:\"scholarships_employee_id_status_date_index\";s:7:\"columns\";a:3:{i:0;s:11:\"employee_id\";i:1;s:6:\"status\";i:2;s:4:\"date\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:32:\"scholarships_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:6:\"unique\";b:0;}i:6;a:3:{s:4:\"name\";s:36:\"scholarships_package_type_id_foreign\";s:7:\"columns\";a:1:{i:0;s:15:\"package_type_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:7:{i:0;a:6:{s:4:\"name\";s:33:\"scholarships_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:31:\"scholarships_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:16:\"references_table\";s:9:\"countries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:32:\"scholarships_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:3;a:6:{s:4:\"name\";s:39:\"scholarships_education_level_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"education_level_id\";}s:16:\"references_table\";s:16:\"education_levels\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:32:\"scholarships_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:5;a:6:{s:4:\"name\";s:32:\"scholarships_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:16:\"references_table\";s:9:\"languages\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:6;a:6:{s:4:\"name\";s:36:\"scholarships_package_type_id_foreign\";s:7:\"columns\";a:1:{i:0;s:15:\"package_type_id\";}s:16:\"references_table\";s:13:\"package_types\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:83;a:5:{s:4:\"name\";s:9:\"semesters\";s:7:\"columns\";a:8:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:4:\"code\";s:4:\"type\";s:11:\"varchar(30)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:10:\"sort_order\";s:4:\"type\";s:16:\"int(10) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:4;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:5;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:21:\"semesters_code_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"code\";}s:6:\"unique\";b:1;}i:1;a:3:{s:4:\"name\";s:21:\"semesters_name_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"name\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:84;a:5:{s:4:\"name\";s:8:\"services\";s:7:\"columns\";a:10:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:8:\"category\";s:4:\"type\";s:11:\"varchar(40)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'general\'\";}i:3;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:3:\"fee\";s:4:\"type\";s:13:\"decimal(10,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:4:\"0.00\";}i:5;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:23:\"services_category_index\";s:7:\"columns\";a:1:{i:0;s:8:\"category\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:28:\"services_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:28:\"services_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:85;a:5:{s:4:\"name\";s:8:\"sessions\";s:7:\"columns\";a:6:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:10:\"ip_address\";s:4:\"type\";s:11:\"varchar(45)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:10:\"user_agent\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:7:\"payload\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:13:\"last_activity\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:28:\"sessions_last_activity_index\";s:7:\"columns\";a:1:{i:0;s:13:\"last_activity\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:22:\"sessions_user_id_index\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:0:{}}i:86;a:5:{s:4:\"name\";s:8:\"settings\";s:7:\"columns\";a:46:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:14:\"navigation_clr\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:9:\"cards_clr\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:10:\"footer_clr\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:24:\"paid_leave_days_per_year\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:5;a:4:{s:4:\"name\";s:13:\"website_title\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:12:\"website_logo\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:17:\"company_signature\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:19:\"contract_letterhead\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:21:\"contract_cover_design\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:13:\"company_email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:9:\"mail_host\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:9:\"mail_port\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:15:\"mail_encryption\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:13:\"mail_username\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:13:\"mail_password\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:14:\"mail_from_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:17;a:4:{s:4:\"name\";s:13:\"mail_reply_to\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:8:\"timezone\";s:4:\"type\";s:11:\"varchar(64)\";s:8:\"nullable\";b:0;s:7:\"default\";s:12:\"\'Asia/Kabul\'\";}i:19;a:4:{s:4:\"name\";s:15:\"website_address\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:20;a:4:{s:4:\"name\";s:5:\"phone\";s:4:\"type\";s:11:\"varchar(50)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:21;a:4:{s:4:\"name\";s:16:\"physical_address\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:22;a:4:{s:4:\"name\";s:18:\"short_company_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:23;a:4:{s:4:\"name\";s:21:\"original_company_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:24;a:4:{s:4:\"name\";s:16:\"business_licence\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:25;a:4:{s:4:\"name\";s:16:\"office_open_time\";s:4:\"type\";s:4:\"time\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:26;a:4:{s:4:\"name\";s:17:\"office_close_time\";s:4:\"type\";s:4:\"time\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:27;a:4:{s:4:\"name\";s:21:\"default_break_minutes\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:28;a:4:{s:4:\"name\";s:28:\"automatic_attendance_enabled\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:29;a:4:{s:4:\"name\";s:13:\"primary_color\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:30;a:4:{s:4:\"name\";s:15:\"secondary_color\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:31;a:4:{s:4:\"name\";s:13:\"success_color\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'#15803D\'\";}i:32;a:4:{s:4:\"name\";s:10:\"info_color\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'#0284C7\'\";}i:33;a:4:{s:4:\"name\";s:13:\"warning_color\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'#D97706\'\";}i:34;a:4:{s:4:\"name\";s:11:\"error_color\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'#DC2626\'\";}i:35;a:4:{s:4:\"name\";s:22:\"light_background_color\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'#F5F7F4\'\";}i:36;a:4:{s:4:\"name\";s:19:\"light_surface_color\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'#FFFEFA\'\";}i:37;a:4:{s:4:\"name\";s:21:\"dark_background_color\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'#102321\'\";}i:38;a:4:{s:4:\"name\";s:18:\"dark_surface_color\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'#173330\'\";}i:39;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:40;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:41;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:42;a:4:{s:4:\"name\";s:26:\"contract_letterhead_safe_x\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:4:\"8.50\";}i:43;a:4:{s:4:\"name\";s:26:\"contract_letterhead_safe_y\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:5:\"15.00\";}i:44;a:4:{s:4:\"name\";s:30:\"contract_letterhead_safe_width\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:5:\"83.00\";}i:45;a:4:{s:4:\"name\";s:31:\"contract_letterhead_safe_height\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:0;s:7:\"default\";s:5:\"70.00\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:0:{}s:12:\"foreign_keys\";a:0:{}}i:87;a:5:{s:4:\"name\";s:8:\"subtasks\";s:7:\"columns\";a:10:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"task_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:5:\"title\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:8:\"category\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'General\'\";}i:5;a:4:{s:4:\"name\";s:8:\"priority\";s:4:\"type\";s:36:\"enum(\'low\',\'medium\',\'high\',\'urgent\')\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'medium\'\";}i:6;a:4:{s:4:\"name\";s:12:\"is_completed\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:7;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:24:\"subtasks_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"task_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:24:\"subtasks_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:24:\"subtasks_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"task_id\";}s:16:\"references_table\";s:5:\"tasks\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:24:\"subtasks_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:88;a:5:{s:4:\"name\";s:15:\"support_tickets\";s:7:\"columns\";a:13:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:13:\"ticket_number\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:8:\"category\";s:4:\"type\";s:11:\"varchar(40)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:8:\"severity\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'normal\'\";}i:5;a:4:{s:4:\"name\";s:7:\"subject\";s:4:\"type\";s:12:\"varchar(160)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:8:\"page_url\";s:4:\"type\";s:13:\"varchar(2048)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:11:\"varchar(20)\";s:8:\"nullable\";b:0;s:7:\"default\";s:6:\"\'open\'\";}i:9;a:4:{s:4:\"name\";s:15:\"recipient_email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:12:\"submitted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:37:\"support_tickets_status_severity_index\";s:7:\"columns\";a:2:{i:0;s:6:\"status\";i:1;s:8:\"severity\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:36:\"support_tickets_ticket_number_unique\";s:7:\"columns\";a:1:{i:0;s:13:\"ticket_number\";}s:6:\"unique\";b:1;}i:2;a:3:{s:4:\"name\";s:40:\"support_tickets_user_id_created_at_index\";s:7:\"columns\";a:2:{i:0;s:7:\"user_id\";i:1;s:10:\"created_at\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:31:\"support_tickets_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:89;a:5:{s:4:\"name\";s:26:\"support_ticket_attachments\";s:7:\"columns\";a:9:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:17:\"support_ticket_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:4:\"disk\";s:4:\"type\";s:11:\"varchar(40)\";s:8:\"nullable\";b:0;s:7:\"default\";s:7:\"\'local\'\";}i:3;a:4:{s:4:\"name\";s:4:\"path\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:13:\"original_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:5;a:4:{s:4:\"name\";s:9:\"mime_type\";s:4:\"type\";s:12:\"varchar(120)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:4:\"size\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:50:\"support_ticket_attachments_support_ticket_id_index\";s:7:\"columns\";a:1:{i:0;s:17:\"support_ticket_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:52:\"support_ticket_attachments_support_ticket_id_foreign\";s:7:\"columns\";a:1:{i:0;s:17:\"support_ticket_id\";}s:16:\"references_table\";s:15:\"support_tickets\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:90;a:5:{s:4:\"name\";s:4:\"tags\";s:7:\"columns\";a:6:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:5:\"color\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:16:\"tags_name_unique\";s:7:\"columns\";a:1:{i:0;s:4:\"name\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:91;a:5:{s:4:\"name\";s:5:\"tasks\";s:7:\"columns\";a:20:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:8:\"board_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:5:\"title\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:7:\"results\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:8:\"priority\";s:4:\"type\";s:36:\"enum(\'low\',\'medium\',\'high\',\'urgent\')\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'medium\'\";}i:7;a:4:{s:4:\"name\";s:8:\"progress\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:8;a:4:{s:4:\"name\";s:10:\"start_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:8:\"due_date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:12:\"completed_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:14:\"reopened_count\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:12;a:4:{s:4:\"name\";s:14:\"rejected_count\";s:4:\"type\";s:20:\"smallint(5) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:13;a:4:{s:4:\"name\";s:21:\"manager_quality_score\";s:4:\"type\";s:12:\"decimal(3,2)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:19:\"quality_reviewed_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:19:\"quality_reviewed_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:16;a:4:{s:4:\"name\";s:5:\"order\";s:4:\"type\";s:7:\"int(11)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:17;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:18;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:19;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:4:{i:0;a:3:{s:4:\"name\";s:24:\"tasks_applicant_id_index\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:22:\"tasks_board_id_foreign\";s:7:\"columns\";a:1:{i:0;s:8:\"board_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:33:\"tasks_completed_at_progress_index\";s:7:\"columns\";a:2:{i:0;s:12:\"completed_at\";i:1;s:8:\"progress\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:33:\"tasks_quality_reviewed_by_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"quality_reviewed_by\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:3:{i:0;a:6:{s:4:\"name\";s:26:\"tasks_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:22:\"tasks_board_id_foreign\";s:7:\"columns\";a:1:{i:0;s:8:\"board_id\";}s:16:\"references_table\";s:6:\"boards\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:2;a:6:{s:4:\"name\";s:33:\"tasks_quality_reviewed_by_foreign\";s:7:\"columns\";a:1:{i:0;s:19:\"quality_reviewed_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:92;a:5:{s:4:\"name\";s:16:\"task_attachments\";s:7:\"columns\";a:12:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"task_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:7:\"section\";s:4:\"type\";s:23:\"enum(\'input\',\'results\')\";s:8:\"nullable\";b:0;s:7:\"default\";s:9:\"\'results\'\";}i:4;a:4:{s:4:\"name\";s:4:\"disk\";s:4:\"type\";s:11:\"varchar(32)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'public\'\";}i:5;a:4:{s:4:\"name\";s:4:\"path\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:13:\"original_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:9:\"mime_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:4:\"size\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:9;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:32:\"task_attachments_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"task_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:32:\"task_attachments_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:32:\"task_attachments_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"task_id\";}s:16:\"references_table\";s:5:\"tasks\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:32:\"task_attachments_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}}}i:93;a:5:{s:4:\"name\";s:17:\"task_dependencies\";s:7:\"columns\";a:2:{i:0;a:4:{s:4:\"name\";s:7:\"task_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:18:\"depends_on_task_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}}s:11:\"primary_key\";a:2:{i:0;s:7:\"task_id\";i:1;s:18:\"depends_on_task_id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:44:\"task_dependencies_depends_on_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"depends_on_task_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:44:\"task_dependencies_depends_on_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"depends_on_task_id\";}s:16:\"references_table\";s:5:\"tasks\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:33:\"task_dependencies_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"task_id\";}s:16:\"references_table\";s:5:\"tasks\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:94;a:5:{s:4:\"name\";s:12:\"task_service\";s:7:\"columns\";a:4:{i:0;a:4:{s:4:\"name\";s:7:\"task_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:10:\"service_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:2:{i:0;s:7:\"task_id\";i:1;s:10:\"service_id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:31:\"task_service_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:31:\"task_service_service_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"service_id\";}s:16:\"references_table\";s:8:\"services\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:1;a:6:{s:4:\"name\";s:28:\"task_service_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"task_id\";}s:16:\"references_table\";s:5:\"tasks\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:95;a:5:{s:4:\"name\";s:8:\"task_tag\";s:7:\"columns\";a:2:{i:0;a:4:{s:4:\"name\";s:7:\"task_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:6:\"tag_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}}s:11:\"primary_key\";a:2:{i:0;s:7:\"task_id\";i:1;s:6:\"tag_id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:23:\"task_tag_tag_id_foreign\";s:7:\"columns\";a:1:{i:0;s:6:\"tag_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:23:\"task_tag_tag_id_foreign\";s:7:\"columns\";a:1:{i:0;s:6:\"tag_id\";}s:16:\"references_table\";s:4:\"tags\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:24:\"task_tag_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"task_id\";}s:16:\"references_table\";s:5:\"tasks\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:96;a:5:{s:4:\"name\";s:9:\"task_user\";s:7:\"columns\";a:3:{i:0;a:4:{s:4:\"name\";s:7:\"task_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:20:\"contribution_percent\";s:4:\"type\";s:12:\"decimal(5,2)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:2:{i:0;s:7:\"task_id\";i:1;s:7:\"user_id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:25:\"task_user_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:25:\"task_user_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"task_id\";}s:16:\"references_table\";s:5:\"tasks\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:25:\"task_user_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:97;a:5:{s:4:\"name\";s:11:\"test_offers\";s:7:\"columns\";a:14:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:12:\"applicant_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:2;a:4:{s:4:\"name\";s:10:\"country_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:18:\"education_level_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:11:\"language_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:15:\"package_type_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:4:\"date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:9;a:4:{s:4:\"name\";s:3:\"fee\";s:4:\"type\";s:13:\"decimal(20,6)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:10;a:4:{s:4:\"name\";s:6:\"status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'active\'\";}i:11;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:7:{i:0;a:3:{s:4:\"name\";s:32:\"test_offers_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:52:\"test_offers_country_id_education_level_id_date_index\";s:7:\"columns\";a:3:{i:0;s:10:\"country_id\";i:1;s:18:\"education_level_id\";i:2;s:4:\"date\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:31:\"test_offers_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:38:\"test_offers_education_level_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"education_level_id\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:41:\"test_offers_employee_id_status_date_index\";s:7:\"columns\";a:3:{i:0;s:11:\"employee_id\";i:1;s:6:\"status\";i:2;s:4:\"date\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:31:\"test_offers_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:6:\"unique\";b:0;}i:6;a:3:{s:4:\"name\";s:35:\"test_offers_package_type_id_foreign\";s:7:\"columns\";a:1:{i:0;s:15:\"package_type_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:7:{i:0;a:6:{s:4:\"name\";s:32:\"test_offers_applicant_id_foreign\";s:7:\"columns\";a:1:{i:0;s:12:\"applicant_id\";}s:16:\"references_table\";s:10:\"applicants\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:30:\"test_offers_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:16:\"references_table\";s:9:\"countries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:31:\"test_offers_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:3;a:6:{s:4:\"name\";s:38:\"test_offers_education_level_id_foreign\";s:7:\"columns\";a:1:{i:0;s:18:\"education_level_id\";}s:16:\"references_table\";s:16:\"education_levels\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:31:\"test_offers_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"restrict\";}i:5;a:6:{s:4:\"name\";s:31:\"test_offers_language_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"language_id\";}s:16:\"references_table\";s:9:\"languages\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:6;a:6:{s:4:\"name\";s:35:\"test_offers_package_type_id_foreign\";s:7:\"columns\";a:1:{i:0;s:15:\"package_type_id\";}s:16:\"references_table\";s:13:\"package_types\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:98;a:5:{s:4:\"name\";s:12:\"universities\";s:7:\"columns\";a:10:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:10:\"country_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:4:\"city\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:7:\"website\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:11:\"description\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:9:\"is_active\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"1\";}i:7;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:2:{i:0;a:3:{s:4:\"name\";s:31:\"universities_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:35:\"universities_name_country_id_unique\";s:7:\"columns\";a:2:{i:0;s:4:\"name\";i:1;s:10:\"country_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:31:\"universities_country_id_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"country_id\";}s:16:\"references_table\";s:9:\"countries\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:99;a:5:{s:4:\"name\";s:5:\"users\";s:7:\"columns\";a:16:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:5:\"email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:5:\"phone\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:7:\"address\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:10:\"acc_status\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";s:8:\"\'active\'\";}i:6;a:4:{s:4:\"name\";s:16:\"account_settings\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:17:\"two_factor_secret\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:23:\"two_factor_confirmed_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:17:\"email_verified_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:17:\"must_set_password\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:11;a:4:{s:4:\"name\";s:8:\"password\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:12;a:4:{s:4:\"name\";s:14:\"remember_token\";s:4:\"type\";s:12:\"varchar(100)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:14;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:15;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:18:\"users_email_unique\";s:7:\"columns\";a:1:{i:0;s:5:\"email\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:0:{}}i:100;a:5:{s:4:\"name\";s:10:\"user_roles\";s:7:\"columns\";a:5:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:7:\"user_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:7:\"role_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:3:{i:0;a:3:{s:4:\"name\";s:26:\"user_roles_role_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"role_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:24:\"user_roles_user_id_index\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:6:\"unique\";b:0;}i:2;a:3:{s:4:\"name\";s:33:\"user_roles_user_id_role_id_unique\";s:7:\"columns\";a:2:{i:0;s:7:\"user_id\";i:1;s:7:\"role_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:2:{i:0;a:6:{s:4:\"name\";s:26:\"user_roles_role_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"role_id\";}s:16:\"references_table\";s:5:\"roles\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:1;a:6:{s:4:\"name\";s:26:\"user_roles_user_id_foreign\";s:7:\"columns\";a:1:{i:0;s:7:\"user_id\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}}}i:101;a:5:{s:4:\"name\";s:8:\"visitors\";s:7:\"columns\";a:14:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:4:\"name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:7:\"company\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:3;a:4:{s:4:\"name\";s:7:\"contact\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:4;a:4:{s:4:\"name\";s:5:\"email\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:7:\"service\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:6;a:4:{s:4:\"name\";s:8:\"marketer\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:7;a:4:{s:4:\"name\";s:11:\"marketer_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:5:\"notes\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:4:\"date\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:10;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:12;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:12:\"converted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:1:{i:0;a:3:{s:4:\"name\";s:24:\"visitors_marketer_id_idx\";s:7:\"columns\";a:1:{i:0;s:11:\"marketer_id\";}s:6:\"unique\";b:0;}}s:12:\"foreign_keys\";a:1:{i:0;a:6:{s:4:\"name\";s:28:\"visitors_marketer_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"marketer_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}i:102;a:5:{s:4:\"name\";s:16:\"worthy_employees\";s:7:\"columns\";a:32:{i:0;a:4:{s:4:\"name\";s:2:\"id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:1;a:4:{s:4:\"name\";s:11:\"employee_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:2;a:4:{s:4:\"name\";s:11:\"currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:3;a:4:{s:4:\"name\";s:16:\"base_currency_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:4;a:4:{s:4:\"name\";s:13:\"currency_code\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:5;a:4:{s:4:\"name\";s:13:\"currency_name\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:6;a:4:{s:4:\"name\";s:15:\"currency_symbol\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:7;a:4:{s:4:\"name\";s:23:\"currency_decimal_places\";s:4:\"type\";s:19:\"tinyint(3) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:8;a:4:{s:4:\"name\";s:18:\"base_currency_code\";s:4:\"type\";s:11:\"varchar(10)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:9;a:4:{s:4:\"name\";s:4:\"rate\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:10;a:4:{s:4:\"name\";s:11:\"base_amount\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:11;a:4:{s:4:\"name\";s:24:\"currency_snapshot_source\";s:4:\"type\";s:11:\"varchar(40)\";s:8:\"nullable\";b:0;s:7:\"default\";s:22:\"\'recorded_at_creation\'\";}i:12;a:4:{s:4:\"name\";s:14:\"source_task_id\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:13;a:4:{s:4:\"name\";s:12:\"is_automatic\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:14;a:4:{s:4:\"name\";s:17:\"performance_score\";s:4:\"type\";s:12:\"decimal(3,1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:3:\"0.0\";}i:15;a:4:{s:4:\"name\";s:19:\"achievement_details\";s:4:\"type\";s:4:\"text\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:16;a:4:{s:4:\"name\";s:16:\"suggested_amount\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:17;a:4:{s:4:\"name\";s:15:\"selected_amount\";s:4:\"type\";s:13:\"decimal(20,8)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:18;a:4:{s:4:\"name\";s:13:\"selected_type\";s:4:\"type\";s:12:\"varchar(255)\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:19;a:4:{s:4:\"name\";s:14:\"granted_amount\";s:4:\"type\";s:13:\"decimal(20,4)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:20;a:4:{s:4:\"name\";s:10:\"is_granted\";s:4:\"type\";s:10:\"tinyint(1)\";s:8:\"nullable\";b:0;s:7:\"default\";s:1:\"0\";}i:21;a:4:{s:4:\"name\";s:11:\"bonus_month\";s:4:\"type\";s:4:\"date\";s:8:\"nullable\";b:0;s:7:\"default\";N;}i:22;a:4:{s:4:\"name\";s:15:\"calculation_key\";s:4:\"type\";s:11:\"varchar(80)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:23;a:4:{s:4:\"name\";s:17:\"calculation_score\";s:4:\"type\";s:13:\"decimal(12,4)\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:24;a:4:{s:4:\"name\";s:18:\"calculation_inputs\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:25;a:4:{s:4:\"name\";s:13:\"rule_snapshot\";s:4:\"type\";s:8:\"longtext\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:26;a:4:{s:4:\"name\";s:13:\"calculated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:27;a:4:{s:4:\"name\";s:10:\"granted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:28;a:4:{s:4:\"name\";s:10:\"granted_by\";s:4:\"type\";s:19:\"bigint(20) unsigned\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:29;a:4:{s:4:\"name\";s:10:\"created_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:30;a:4:{s:4:\"name\";s:10:\"updated_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}i:31;a:4:{s:4:\"name\";s:10:\"deleted_at\";s:4:\"type\";s:9:\"timestamp\";s:8:\"nullable\";b:1;s:7:\"default\";s:4:\"NULL\";}}s:11:\"primary_key\";a:1:{i:0;s:2:\"id\";}s:7:\"indexes\";a:6:{i:0;a:3:{s:4:\"name\";s:41:\"worthy_employees_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:6:\"unique\";b:0;}i:1;a:3:{s:4:\"name\";s:39:\"worthy_employees_calculation_key_unique\";s:7:\"columns\";a:1:{i:0;s:15:\"calculation_key\";}s:6:\"unique\";b:1;}i:2;a:3:{s:4:\"name\";s:36:\"worthy_employees_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:6:\"unique\";b:0;}i:3;a:3:{s:4:\"name\";s:35:\"worthy_employees_granted_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"granted_by\";}s:6:\"unique\";b:0;}i:4;a:3:{s:4:\"name\";s:39:\"worthy_employees_source_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:14:\"source_task_id\";}s:6:\"unique\";b:0;}i:5;a:3:{s:4:\"name\";s:27:\"worthy_employee_task_unique\";s:7:\"columns\";a:2:{i:0;s:11:\"employee_id\";i:1;s:14:\"source_task_id\";}s:6:\"unique\";b:1;}}s:12:\"foreign_keys\";a:5:{i:0;a:6:{s:4:\"name\";s:41:\"worthy_employees_base_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:16:\"base_currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:1;a:6:{s:4:\"name\";s:36:\"worthy_employees_currency_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"currency_id\";}s:16:\"references_table\";s:10:\"currencies\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:2;a:6:{s:4:\"name\";s:36:\"worthy_employees_employee_id_foreign\";s:7:\"columns\";a:1:{i:0;s:11:\"employee_id\";}s:16:\"references_table\";s:9:\"employees\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:7:\"cascade\";}i:3;a:6:{s:4:\"name\";s:35:\"worthy_employees_granted_by_foreign\";s:7:\"columns\";a:1:{i:0;s:10:\"granted_by\";}s:16:\"references_table\";s:5:\"users\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}i:4;a:6:{s:4:\"name\";s:39:\"worthy_employees_source_task_id_foreign\";s:7:\"columns\";a:1:{i:0;s:14:\"source_task_id\";}s:16:\"references_table\";s:5:\"tasks\";s:18:\"references_columns\";a:1:{i:0;s:2:\"id\";}s:9:\"on_update\";s:8:\"restrict\";s:9:\"on_delete\";s:8:\"set null\";}}}}s:12:\"generated_at\";s:25:\"2026-09-28T04:31:53+00:00\";}',1790573513);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `case_wins`
--

DROP TABLE IF EXISTS `case_wins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `case_wins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `applicant_stage_history_id` bigint(20) unsigned DEFAULT NULL,
  `won_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `stage_key` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `case_wins_applicant_id_unique` (`applicant_id`),
  UNIQUE KEY `case_wins_applicant_stage_history_id_unique` (`applicant_stage_history_id`),
  KEY `case_wins_employee_id_won_at_index` (`employee_id`,`won_at`),
  CONSTRAINT `case_wins_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`),
  CONSTRAINT `case_wins_applicant_stage_history_id_foreign` FOREIGN KEY (`applicant_stage_history_id`) REFERENCES `applicant_stage_histories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `case_wins_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `case_wins`
--

LOCK TABLES `case_wins` WRITE;
/*!40000 ALTER TABLE `case_wins` DISABLE KEYS */;
/*!40000 ALTER TABLE `case_wins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_task_id_foreign` (`task_id`),
  KEY `comments_user_id_foreign` (`user_id`),
  CONSTRAINT `comments_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
INSERT INTO `comments` VALUES (1,1,1,'Sint quibusdam accusamus ullam est.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(2,2,2,'Quia voluptatem est ut accusamus dolore et in.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(3,4,3,'Sunt et quas et aliquid eveniet molestiae.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(4,4,3,'Quam commodi quia nihil id fugit dolorem qui in.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(5,4,3,'Laboriosam occaecati totam aut harum sed inventore dolores.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(6,4,3,'Eius occaecati eum reprehenderit repellendus.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(7,5,4,'Eos sit temporibus iure soluta eum.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(8,6,4,'Temporibus ut et nisi nisi et omnis maxime.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(9,6,4,'Repellendus rem et alias.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(10,7,2,'Dolores voluptatem ea exercitationem necessitatibus.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(11,7,2,'Pariatur aut repudiandae repudiandae voluptatum.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(12,8,2,'Quisquam repellat iusto consequatur rerum.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(13,8,2,'Qui adipisci libero dolorem et quo consequuntur.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(14,8,2,'At nihil fugiat tempora quos quo.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(15,8,2,'Quod autem vel quibusdam ut.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(16,9,2,'Praesentium architecto aliquid natus doloremque quia ipsa at.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(17,11,3,'Placeat et iusto est numquam ut omnis consequatur alias.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(18,11,3,'Ipsa in est nostrum delectus.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(19,16,2,'Accusantium eaque quos enim sapiente asperiores.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(20,17,2,'Sint adipisci hic nemo iste mollitia reiciendis est ut.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(21,18,1,'Modi totam quia illum ea minus est itaque.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(22,18,1,'Dolorem accusantium unde eligendi modi corrupti id a tempora.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(23,18,1,'Non exercitationem quis quibusdam inventore ea sed.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(24,18,1,'A dicta occaecati modi similique est maxime odio non.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(25,20,3,'Ad et quisquam nihil quaerat iure vel harum.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(26,20,3,'Odit rem accusantium officiis numquam autem earum hic sit.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(27,20,3,'Est possimus aut consectetur culpa nobis temporibus.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(28,20,3,'Similique incidunt animi repellendus.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(29,20,3,'Facere delectus quo id odit unde qui.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(30,21,2,'Iure et nostrum consequatur similique.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(31,21,2,'Impedit necessitatibus sed nostrum a.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(32,21,2,'Cumque accusantium ut beatae enim sapiente id.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(33,21,2,'Tempora voluptas quia non veniam voluptatem sed dolor.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(34,22,2,'Voluptas qui et id molestias enim earum eum.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(35,22,2,'Ipsa ut facilis aut et vel atque et.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(36,22,2,'Itaque saepe sint laudantium quia.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(37,22,2,'Repellendus hic suscipit tempora dolores.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(38,22,2,'Autem libero est quia omnis.','2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(39,25,5,'Ipsam nobis aspernatur delectus quia enim amet.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(40,27,5,'Velit et architecto accusamus voluptas reiciendis in nostrum eum.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(41,27,5,'Et non eligendi quidem deleniti sunt.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(42,27,5,'Provident aliquid voluptas numquam repudiandae earum perferendis.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(43,27,5,'Expedita rerum voluptatem eum aliquam perferendis.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(44,29,6,'Qui et laboriosam consectetur fuga et quo ut porro.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(45,29,6,'Alias nostrum et nobis facere qui nihil facilis.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(46,30,5,'Dicta perferendis ab totam laborum et earum voluptatem.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(47,31,7,'Sit tempore aut magnam eum temporibus nemo sit molestiae.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(48,32,6,'Nam dicta laudantium eligendi debitis.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(49,32,6,'Ratione consequatur voluptates quaerat enim libero eos.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(50,33,6,'Quisquam praesentium dolores ex nostrum esse.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(51,33,6,'Ratione vel dolore non suscipit.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(52,33,6,'Dolorem enim in quo.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(53,33,6,'Natus ullam suscipit vel natus quia voluptatem placeat.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(54,38,6,'Est tenetur ducimus et ea error sit.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(55,38,6,'Ipsum aut saepe fugiat aperiam.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(56,39,6,'Possimus illum voluptates qui quo sit exercitationem.','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL);
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commission_accruals`
--

DROP TABLE IF EXISTS `commission_accruals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commission_accruals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `income_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_service_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `base_currency_id` bigint(20) unsigned NOT NULL,
  `commission_settlement_id` bigint(20) unsigned DEFAULT NULL,
  `entry_type` varchar(255) NOT NULL DEFAULT 'earned',
  `commission_role` varchar(20) NOT NULL DEFAULT 'delivery',
  `earned_at` date NOT NULL,
  `source_payment_base_amount` decimal(20,6) NOT NULL,
  `eligible_base_amount` decimal(20,6) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `commission_base_amount` decimal(20,6) NOT NULL,
  `adjustment_reason` text DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `contract_service_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commission_accrual_source_employee_role_unique` (`income_id`,`invoice_service_id`,`employee_id`,`commission_role`),
  KEY `commission_accruals_invoice_id_foreign` (`invoice_id`),
  KEY `commission_accruals_service_id_foreign` (`service_id`),
  KEY `commission_accruals_base_currency_id_foreign` (`base_currency_id`),
  KEY `commission_accruals_commission_settlement_id_foreign` (`commission_settlement_id`),
  KEY `commission_accruals_employee_id_status_earned_at_index` (`employee_id`,`status`,`earned_at`),
  KEY `commission_accruals_recorded_by_foreign` (`recorded_by`),
  KEY `commission_accrual_income_fk_idx` (`income_id`),
  KEY `commission_accrual_invoice_service_fk_idx` (`invoice_service_id`),
  KEY `commission_accrual_role_status_idx` (`employee_id`,`commission_role`,`status`,`earned_at`),
  KEY `commission_accruals_contract_service_id_foreign` (`contract_service_id`),
  CONSTRAINT `commission_accruals_base_currency_id_foreign` FOREIGN KEY (`base_currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `commission_accruals_commission_settlement_id_foreign` FOREIGN KEY (`commission_settlement_id`) REFERENCES `commission_settlements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commission_accruals_contract_service_id_foreign` FOREIGN KEY (`contract_service_id`) REFERENCES `contract_service` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commission_accruals_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `commission_accruals_income_id_foreign` FOREIGN KEY (`income_id`) REFERENCES `incomes` (`id`),
  CONSTRAINT `commission_accruals_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`),
  CONSTRAINT `commission_accruals_invoice_service_id_foreign` FOREIGN KEY (`invoice_service_id`) REFERENCES `invoice_service` (`id`),
  CONSTRAINT `commission_accruals_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commission_accruals_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commission_accruals`
--

LOCK TABLES `commission_accruals` WRITE;
/*!40000 ALTER TABLE `commission_accruals` DISABLE KEYS */;
/*!40000 ALTER TABLE `commission_accruals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commission_settlements`
--

DROP TABLE IF EXISTS `commission_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `commission_settlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `settlement_number` varchar(255) DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `base_currency_id` bigint(20) unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `gross_base_amount` decimal(20,6) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_by` bigint(20) unsigned DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `expense_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `commission_settlements_settlement_number_unique` (`settlement_number`),
  UNIQUE KEY `commission_settlements_expense_id_unique` (`expense_id`),
  KEY `commission_settlements_base_currency_id_foreign` (`base_currency_id`),
  KEY `commission_settlements_recorded_by_foreign` (`recorded_by`),
  KEY `commission_settlements_approved_by_foreign` (`approved_by`),
  KEY `commission_settlements_paid_by_foreign` (`paid_by`),
  KEY `commission_settlement_period_idx` (`employee_id`,`status`,`period_start`,`period_end`),
  CONSTRAINT `commission_settlements_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commission_settlements_base_currency_id_foreign` FOREIGN KEY (`base_currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `commission_settlements_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `commission_settlements_expense_id_foreign` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commission_settlements_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `commission_settlements_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commission_settlements`
--

LOCK TABLES `commission_settlements` WRITE;
/*!40000 ALTER TABLE `commission_settlements` DISABLE KEYS */;
/*!40000 ALTER TABLE `commission_settlements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `companies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `legal_name` varchar(255) NOT NULL,
  `trade_name` varchar(255) DEFAULT NULL,
  `registration_number` varchar(255) DEFAULT NULL,
  `tax_number` varchar(255) DEFAULT NULL,
  `national_id` varchar(255) DEFAULT NULL,
  `legal_form` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `province` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `postal_code` varchar(255) DEFAULT NULL,
  `address_line` text DEFAULT NULL,
  `industry` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `representative_name` varchar(255) DEFAULT NULL,
  `representative_title` varchar(255) DEFAULT NULL,
  `representative_phone` varchar(255) DEFAULT NULL,
  `representative_email` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contract_document_designs`
--

DROP TABLE IF EXISTS `contract_document_designs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_document_designs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_type_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `letterhead` varchar(255) DEFAULT NULL,
  `cover_design` varchar(255) DEFAULT NULL,
  `safe_x` decimal(5,2) NOT NULL DEFAULT 8.50,
  `safe_y` decimal(5,2) NOT NULL DEFAULT 15.00,
  `safe_width` decimal(5,2) NOT NULL DEFAULT 83.00,
  `safe_height` decimal(5,2) NOT NULL DEFAULT 70.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_design_type_service_unique` (`contract_type_id`,`service_id`),
  KEY `contract_document_designs_service_id_foreign` (`service_id`),
  CONSTRAINT `contract_document_designs_contract_type_id_foreign` FOREIGN KEY (`contract_type_id`) REFERENCES `contract_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contract_document_designs_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_document_designs`
--

LOCK TABLES `contract_document_designs` WRITE;
/*!40000 ALTER TABLE `contract_document_designs` DISABLE KEYS */;
/*!40000 ALTER TABLE `contract_document_designs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contract_policies`
--

DROP TABLE IF EXISTS `contract_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_policies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `language_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `header` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contract_policies_type_active_index` (`type`,`is_active`),
  KEY `contract_policies_country_id_foreign` (`country_id`),
  KEY `contract_policies_language_id_foreign` (`language_id`),
  KEY `contract_policy_scope_index` (`type`,`country_id`,`language_id`,`is_active`),
  CONSTRAINT `contract_policies_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_policies_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_policies`
--

LOCK TABLES `contract_policies` WRITE;
/*!40000 ALTER TABLE `contract_policies` DISABLE KEYS */;
/*!40000 ALTER TABLE `contract_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contract_policy_contract_template`
--

DROP TABLE IF EXISTS `contract_policy_contract_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_policy_contract_template` (
  `contract_template_id` bigint(20) unsigned NOT NULL,
  `contract_policy_id` bigint(20) unsigned NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`contract_template_id`,`contract_policy_id`),
  KEY `contract_policy_contract_template_contract_policy_id_foreign` (`contract_policy_id`),
  CONSTRAINT `contract_policy_contract_template_contract_policy_id_foreign` FOREIGN KEY (`contract_policy_id`) REFERENCES `contract_policies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contract_policy_contract_template_contract_template_id_foreign` FOREIGN KEY (`contract_template_id`) REFERENCES `contract_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_policy_contract_template`
--

LOCK TABLES `contract_policy_contract_template` WRITE;
/*!40000 ALTER TABLE `contract_policy_contract_template` DISABLE KEYS */;
/*!40000 ALTER TABLE `contract_policy_contract_template` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contract_service`
--

DROP TABLE IF EXISTS `contract_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_service` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `fee` decimal(15,2) DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `customer_package_id` bigint(20) unsigned DEFAULT NULL,
  `sold_by_employee_id` bigint(20) unsigned DEFAULT NULL,
  `marketer_id` bigint(20) unsigned DEFAULT NULL,
  `outsourcing_company_id` bigint(20) unsigned DEFAULT NULL,
  `outsourcing_percentage` decimal(5,2) DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `education_level_id` bigint(20) unsigned DEFAULT NULL,
  `language_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_service_unique` (`contract_id`,`service_id`),
  KEY `contract_service_currency_id_foreign` (`currency_id`),
  KEY `contract_service_applicant_id_foreign` (`applicant_id`),
  KEY `contract_service_customer_package_id_foreign` (`customer_package_id`),
  KEY `contract_service_sold_by_employee_id_foreign` (`sold_by_employee_id`),
  KEY `contract_service_marketer_id_foreign` (`marketer_id`),
  KEY `contract_service_outsourcing_company_id_foreign` (`outsourcing_company_id`),
  KEY `contract_service_country_id_foreign` (`country_id`),
  KEY `contract_service_education_level_id_foreign` (`education_level_id`),
  KEY `contract_service_language_id_foreign` (`language_id`),
  KEY `contract_service_service_idx` (`service_id`),
  CONSTRAINT `contract_service_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_service_contract_id_foreign` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contract_service_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_service_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_service_customer_package_id_foreign` FOREIGN KEY (`customer_package_id`) REFERENCES `customer_packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_service_education_level_id_foreign` FOREIGN KEY (`education_level_id`) REFERENCES `education_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_service_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_service_marketer_id_foreign` FOREIGN KEY (`marketer_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_service_outsourcing_company_id_foreign` FOREIGN KEY (`outsourcing_company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_service_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `contract_service_sold_by_employee_id_foreign` FOREIGN KEY (`sold_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_service`
--

LOCK TABLES `contract_service` WRITE;
/*!40000 ALTER TABLE `contract_service` DISABLE KEYS */;
INSERT INTO `contract_service` VALUES (1,1,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(2,2,2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(3,3,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(4,4,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(5,4,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL);
/*!40000 ALTER TABLE `contract_service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contract_service_employee`
--

DROP TABLE IF EXISTS `contract_service_employee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_service_employee` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_service_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `percentage` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_service_employee_unique` (`employee_id`,`contract_service_id`),
  KEY `contract_service_employee_contract_service_id_foreign` (`contract_service_id`),
  CONSTRAINT `contract_service_employee_contract_service_id_foreign` FOREIGN KEY (`contract_service_id`) REFERENCES `contract_service` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contract_service_employee_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_service_employee`
--

LOCK TABLES `contract_service_employee` WRITE;
/*!40000 ALTER TABLE `contract_service_employee` DISABLE KEYS */;
/*!40000 ALTER TABLE `contract_service_employee` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contract_template_versions`
--

DROP TABLE IF EXISTS `contract_template_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_template_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_template_id` bigint(20) unsigned NOT NULL,
  `language_id` bigint(20) unsigned NOT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `version_number` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT 'Untitled version',
  `content` longtext NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `is_current` tinyint(1) NOT NULL DEFAULT 0,
  `current_scope_key` varchar(100) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `activated_by` bigint(20) unsigned DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `archived_by` bigint(20) unsigned DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ctv_template_country_language_version_unique` (`contract_template_id`,`country_id`,`language_id`,`version_number`),
  UNIQUE KEY `contract_template_versions_current_scope_key_unique` (`current_scope_key`),
  KEY `contract_template_versions_language_id_foreign` (`language_id`),
  KEY `contract_template_versions_created_by_foreign` (`created_by`),
  KEY `ctv_template_language_current_index` (`contract_template_id`,`language_id`,`is_current`),
  KEY `contract_template_versions_country_id_foreign` (`country_id`),
  KEY `ctv_country_language_current_index` (`contract_template_id`,`country_id`,`language_id`,`is_current`),
  KEY `contract_template_versions_activated_by_foreign` (`activated_by`),
  KEY `contract_template_versions_archived_by_foreign` (`archived_by`),
  CONSTRAINT `contract_template_versions_activated_by_foreign` FOREIGN KEY (`activated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_template_versions_archived_by_foreign` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_template_versions_contract_template_id_foreign` FOREIGN KEY (`contract_template_id`) REFERENCES `contract_templates` (`id`),
  CONSTRAINT `contract_template_versions_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_template_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_template_versions_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_template_versions`
--

LOCK TABLES `contract_template_versions` WRITE;
/*!40000 ALTER TABLE `contract_template_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `contract_template_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contract_templates`
--

DROP TABLE IF EXISTS `contract_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_templates` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint(20) unsigned DEFAULT NULL,
  `contract_type_id` bigint(20) unsigned DEFAULT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `cover_text` text NOT NULL,
  `content` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contract_templates_contract_type_id_foreign` (`contract_type_id`),
  KEY `contract_templates_position_id_index` (`position_id`),
  KEY `contract_templates_created_by_foreign` (`created_by`),
  KEY `contract_templates_service_id_index` (`service_id`),
  CONSTRAINT `contract_templates_contract_type_id_foreign` FOREIGN KEY (`contract_type_id`) REFERENCES `contract_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_templates_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_templates_position_id_foreign` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_templates_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_templates`
--

LOCK TABLES `contract_templates` WRITE;
/*!40000 ALTER TABLE `contract_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `contract_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contract_types`
--

DROP TABLE IF EXISTS `contract_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contract_types_name_unique` (`name`),
  UNIQUE KEY `contract_types_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_types`
--

LOCK TABLES `contract_types` WRITE;
/*!40000 ALTER TABLE `contract_types` DISABLE KEYS */;
INSERT INTO `contract_types` VALUES (1,'Employment',NULL,'employment',1,1,'2026-09-28 00:01:24','2026-09-28 00:01:24',NULL),(2,'Customer',NULL,'customer',1,1,'2026-09-28 00:01:24','2026-09-28 00:01:24',NULL),(3,'Other',NULL,'other',1,1,'2026-09-28 00:01:24','2026-09-28 00:01:24',NULL);
/*!40000 ALTER TABLE `contract_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contracts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_number` varchar(255) DEFAULT NULL,
  `template_id` bigint(20) unsigned DEFAULT NULL,
  `contract_template_version_id` bigint(20) unsigned DEFAULT NULL,
  `contract_country_id` bigint(20) unsigned DEFAULT NULL,
  `contract_language_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `sold_by_employee_id` bigint(20) unsigned DEFAULT NULL,
  `marketer_id` bigint(20) unsigned DEFAULT NULL,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `source_service_id` bigint(20) unsigned DEFAULT NULL,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `contract_type_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `contract_role` varchar(255) NOT NULL DEFAULT 'primary',
  `parent_contract_id` bigint(20) unsigned DEFAULT NULL,
  `start_date` date NOT NULL,
  `counterparty` varchar(255) NOT NULL,
  `counterparty_company` varchar(255) DEFAULT NULL,
  `counterparty_email` varchar(255) DEFAULT NULL,
  `counterparty_contact` varchar(255) DEFAULT NULL,
  `counterparty_address` text DEFAULT NULL,
  `company_signatory_name` varchar(255) DEFAULT NULL,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT 0,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cover_text` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `is_custom` tinyint(1) NOT NULL DEFAULT 0,
  `snapshot_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`snapshot_data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contracts_contract_number_unique` (`contract_number`),
  KEY `contracts_template_id_foreign` (`template_id`),
  KEY `contracts_employee_id_foreign` (`employee_id`),
  KEY `contracts_company_id_foreign` (`company_id`),
  KEY `contracts_contract_type_id_foreign` (`contract_type_id`),
  KEY `contracts_applicant_id_index` (`applicant_id`),
  KEY `contracts_contract_template_version_id_foreign` (`contract_template_version_id`),
  KEY `contracts_sold_by_employee_id_foreign` (`sold_by_employee_id`),
  KEY `contracts_marketer_id_foreign` (`marketer_id`),
  KEY `contracts_contract_country_id_foreign` (`contract_country_id`),
  KEY `contracts_contract_language_id_foreign` (`contract_language_id`),
  KEY `contracts_source_service_id_foreign` (`source_service_id`),
  KEY `contracts_parent_contract_id_foreign` (`parent_contract_id`),
  KEY `contracts_service_primary_index` (`applicant_id`,`source_service_id`,`contract_role`),
  CONSTRAINT `contracts_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_contract_country_id_foreign` FOREIGN KEY (`contract_country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_contract_language_id_foreign` FOREIGN KEY (`contract_language_id`) REFERENCES `languages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_contract_template_version_id_foreign` FOREIGN KEY (`contract_template_version_id`) REFERENCES `contract_template_versions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_contract_type_id_foreign` FOREIGN KEY (`contract_type_id`) REFERENCES `contract_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_marketer_id_foreign` FOREIGN KEY (`marketer_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_parent_contract_id_foreign` FOREIGN KEY (`parent_contract_id`) REFERENCES `contracts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_sold_by_employee_id_foreign` FOREIGN KEY (`sold_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_source_service_id_foreign` FOREIGN KEY (`source_service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contracts_template_id_foreign` FOREIGN KEY (`template_id`) REFERENCES `contract_templates` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contracts`
--

LOCK TABLES `contracts` WRITE;
/*!40000 ALTER TABLE `contracts` DISABLE KEYS */;
INSERT INTO `contracts` VALUES (1,'CTR-2026-000001',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL,2,'Seeded service agreement','Customer','active','primary',NULL,'2026-09-28','John Doe',NULL,NULL,'555-1234',NULL,NULL,0,NULL,NULL,'',1,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(2,'CTR-2026-000002',NULL,NULL,NULL,NULL,NULL,NULL,NULL,2,NULL,NULL,2,'Seeded service agreement','Customer','active','primary',NULL,'2026-09-28','Jane Smith',NULL,NULL,'555-5678',NULL,NULL,0,NULL,NULL,'',1,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(3,'CTR-2026-000003',NULL,NULL,NULL,NULL,NULL,NULL,NULL,3,NULL,NULL,2,'Seeded service agreement','Customer','active','primary',NULL,'2026-09-28','Bob Wilson',NULL,NULL,'555-9012',NULL,NULL,0,NULL,NULL,'',1,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(4,'CTR-2026-000004',NULL,NULL,NULL,NULL,NULL,NULL,NULL,4,NULL,NULL,2,'Seeded service agreement','Customer','active','primary',NULL,'2026-09-28','Alice Cooper',NULL,NULL,'555-3456',NULL,NULL,0,NULL,NULL,'',1,NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL);
/*!40000 ALTER TABLE `contracts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(3) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `countries_name_unique` (`name`),
  UNIQUE KEY `countries_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `countries`
--

LOCK TABLES `countries` WRITE;
/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
INSERT INTO `countries` VALUES (1,'Canada','CA',1,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(2,'Germany','DE',1,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(3,'United Kingdom','GB',1,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(4,'Turkey','TR',1,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL);
/*!40000 ALTER TABLE `countries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crm_interactions`
--

DROP TABLE IF EXISTS `crm_interactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crm_interactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contactable_type` varchar(255) DEFAULT NULL,
  `contactable_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `channel` enum('call','message','email','meeting','note') NOT NULL DEFAULT 'note',
  `title` varchar(255) NOT NULL,
  `notes` text NOT NULL,
  `contacted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `follow_up_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `crm_interactions_contactable_type_contactable_id_index` (`contactable_type`,`contactable_id`),
  KEY `crm_interactions_created_by_foreign` (`created_by`),
  KEY `crm_interactions_contacted_at_follow_up_at_index` (`contacted_at`,`follow_up_at`),
  CONSTRAINT `crm_interactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crm_interactions`
--

LOCK TABLES `crm_interactions` WRITE;
/*!40000 ALTER TABLE `crm_interactions` DISABLE KEYS */;
INSERT INTO `crm_interactions` VALUES (1,'App\\Models\\Lead',1,1,'call','Initial qualification call','Discussed requirements, timeline, and next steps.','2026-09-27 00:09:13','2026-10-01 00:09:13','2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(2,'App\\Models\\Lead',2,1,'call','Initial qualification call','Discussed requirements, timeline, and next steps.','2026-09-26 00:09:13','2026-10-02 00:09:13','2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(3,'App\\Models\\Visitor',1,1,'message','Visitor follow-up','Sent an introduction and service information.','2026-09-27 00:09:13','2026-09-30 00:09:13','2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(4,'App\\Models\\Visitor',2,1,'message','Visitor follow-up','Sent an introduction and service information.','2026-09-27 00:09:13','2026-09-30 00:09:13','2026-09-28 00:09:13','2026-09-28 00:09:13',NULL);
/*!40000 ALTER TABLE `crm_interactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `currencies`
--

DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currencies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `symbol` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `is_base` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `decimal_places` int(11) NOT NULL DEFAULT 2,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `rate` decimal(20,8) NOT NULL DEFAULT 1.00000000,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currencies`
--

LOCK TABLES `currencies` WRITE;
/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
INSERT INTO `currencies` VALUES (1,'Afghan Afghani','؋','AFN',1,1,2,'2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,1.00000000),(2,'United States Dollar','$','USD',0,0,2,'2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,0.01400000),(3,'Euro','€','EUR',0,0,2,'2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,0.01200000),(4,'British Pound','£','GBP',0,0,2,'2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,0.01000000),(5,'Japanese Yen','¥','JPY',0,0,0,'2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,2.16000000),(6,'Canadian Dollar','CA$','CAD',0,0,2,'2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,0.01900000),(7,'Australian Dollar','AU$','AUD',0,0,2,'2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,0.02100000),(8,'Swiss Franc','CHF','CHF',0,0,2,'2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,0.01100000),(9,'Chinese Yuan','¥','CNY',0,0,2,'2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,0.09900000),(10,'Indian Rupee','₹','INR',0,0,2,'2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,1.17000000),(11,'Russian Ruble','₽','RUB',0,0,2,'2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,1.15000000);
/*!40000 ALTER TABLE `currencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `currency_rate_histories`
--

DROP TABLE IF EXISTS `currency_rate_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `currency_rate_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `currency_id` bigint(20) unsigned NOT NULL,
  `base_currency_id` bigint(20) unsigned DEFAULT NULL,
  `rate` decimal(20,8) NOT NULL,
  `reason` varchar(40) NOT NULL DEFAULT 'rate_updated',
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `currency_rate_histories_base_currency_id_foreign` (`base_currency_id`),
  KEY `currency_rate_histories_recorded_by_foreign` (`recorded_by`),
  KEY `currency_rate_histories_currency_id_created_at_index` (`currency_id`,`created_at`),
  CONSTRAINT `currency_rate_histories_base_currency_id_foreign` FOREIGN KEY (`base_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `currency_rate_histories_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `currency_rate_histories_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currency_rate_histories`
--

LOCK TABLES `currency_rate_histories` WRITE;
/*!40000 ALTER TABLE `currency_rate_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `currency_rate_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customer_packages`
--

DROP TABLE IF EXISTS `customer_packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `customer_packages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `package_id` bigint(20) unsigned NOT NULL,
  `sold_by_employee_id` bigint(20) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `fee` decimal(20,6) DEFAULT NULL,
  `purchased_at` date NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `issuance_key` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_packages_issuance_key_unique` (`issuance_key`),
  KEY `customer_packages_package_id_foreign` (`package_id`),
  KEY `customer_packages_sold_by_employee_id_foreign` (`sold_by_employee_id`),
  KEY `customer_packages_currency_id_foreign` (`currency_id`),
  KEY `customer_packages_customer_id_applicant_id_status_index` (`applicant_id`,`status`),
  CONSTRAINT `customer_packages_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_packages_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customer_packages_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `package_types` (`id`),
  CONSTRAINT `customer_packages_sold_by_employee_id_foreign` FOREIGN KEY (`sold_by_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customer_packages`
--

LOCK TABLES `customer_packages` WRITE;
/*!40000 ALTER TABLE `customer_packages` DISABLE KEYS */;
/*!40000 ALTER TABLE `customer_packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_analytics`
--

DROP TABLE IF EXISTS `daily_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_analytics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `attendance_present` int(10) unsigned NOT NULL DEFAULT 0,
  `attendance_absent` int(10) unsigned NOT NULL DEFAULT 0,
  `attendance_paid_leave` int(10) unsigned NOT NULL DEFAULT 0,
  `attendance_incomplete` int(10) unsigned NOT NULL DEFAULT 0,
  `worked_minutes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `overtime_minutes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `income_total` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `expense_total` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `tasks_created` int(10) unsigned NOT NULL DEFAULT 0,
  `tasks_completed` int(10) unsigned NOT NULL DEFAULT 0,
  `contracts_created` int(10) unsigned NOT NULL DEFAULT 0,
  `contracts_cancelled` int(10) unsigned NOT NULL DEFAULT 0,
  `employees_onboarded` int(10) unsigned NOT NULL DEFAULT 0,
  `bonus_nominations` int(10) unsigned NOT NULL DEFAULT 0,
  `bonuses_granted` int(10) unsigned NOT NULL DEFAULT 0,
  `bonus_base_total` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_analytics_date_unique` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_analytics`
--

LOCK TABLES `daily_analytics` WRITE;
/*!40000 ALTER TABLE `daily_analytics` DISABLE KEYS */;
/*!40000 ALTER TABLE `daily_analytics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `daily_reports`
--

DROP TABLE IF EXISTS `daily_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `attendance_id` bigint(20) unsigned NOT NULL,
  `report_date` date NOT NULL,
  `summary` text NOT NULL,
  `achievements` text DEFAULT NULL,
  `challenges` text DEFAULT NULL,
  `next_day_plan` text DEFAULT NULL,
  `support_needed` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_reports_user_id_report_date_unique` (`user_id`,`report_date`),
  UNIQUE KEY `daily_reports_attendance_id_unique` (`attendance_id`),
  KEY `daily_reports_report_date_submitted_at_index` (`report_date`,`submitted_at`),
  CONSTRAINT `daily_reports_attendance_id_foreign` FOREIGN KEY (`attendance_id`) REFERENCES `attendances` (`id`),
  CONSTRAINT `daily_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `daily_reports`
--

LOCK TABLES `daily_reports` WRITE;
/*!40000 ALTER TABLE `daily_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `daily_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `manager_employee_id` bigint(20) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `departments_name_unique` (`name`),
  UNIQUE KEY `departments_code_unique` (`code`),
  KEY `departments_manager_employee_id_foreign` (`manager_employee_id`),
  CONSTRAINT `departments_manager_employee_id_foreign` FOREIGN KEY (`manager_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `education_levels`
--

DROP TABLE IF EXISTS `education_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `education_levels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `education_levels_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `education_levels`
--

LOCK TABLES `education_levels` WRITE;
/*!40000 ALTER TABLE `education_levels` DISABLE KEYS */;
INSERT INTO `education_levels` VALUES (1,'Bachelor',NULL,1,10,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(2,'Master',NULL,1,20,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(3,'PhD',NULL,1,30,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL);
/*!40000 ALTER TABLE `education_levels` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `educational_bonus_sources`
--

DROP TABLE IF EXISTS `educational_bonus_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `educational_bonus_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `source_type` varchar(255) NOT NULL,
  `source_id` bigint(20) unsigned NOT NULL,
  `source_amount` decimal(20,6) NOT NULL,
  `bonus_amount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `rule_source` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `educational_bonus_source_unique` (`source_type`,`source_id`,`employee_id`),
  KEY `educational_bonus_sources_employee_id_foreign` (`employee_id`),
  KEY `educational_bonus_sources_applicant_id_foreign` (`applicant_id`),
  KEY `educational_bonus_sources_source_type_source_id_index` (`source_type`,`source_id`),
  CONSTRAINT `educational_bonus_sources_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `educational_bonus_sources_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `educational_bonus_sources`
--

LOCK TABLES `educational_bonus_sources` WRITE;
/*!40000 ALTER TABLE `educational_bonus_sources` DISABLE KEYS */;
/*!40000 ALTER TABLE `educational_bonus_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_service_commissions`
--

DROP TABLE IF EXISTS `employee_service_commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_service_commissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_service_commissions_employee_id_service_id_unique` (`employee_id`,`service_id`),
  KEY `employee_service_commissions_service_id_foreign` (`service_id`),
  CONSTRAINT `employee_service_commissions_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_service_commissions_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_service_commissions`
--

LOCK TABLES `employee_service_commissions` WRITE;
/*!40000 ALTER TABLE `employee_service_commissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_service_commissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `father_name` varchar(255) NOT NULL,
  `contact` varchar(255) NOT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `guarantor_id_number` varchar(100) DEFAULT NULL,
  `guarantor_phone` varchar(30) DEFAULT NULL,
  `guarantor_address` text DEFAULT NULL,
  `guarantor_licence` varchar(255) DEFAULT NULL,
  `education_lvl` varchar(255) NOT NULL,
  `position_id` bigint(20) unsigned NOT NULL,
  `employment_type` varchar(255) NOT NULL DEFAULT 'salaried',
  `office_id` bigint(20) unsigned DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `total_worked_hours` decimal(14,2) NOT NULL DEFAULT 0.00,
  `addr` text NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `document_mime` varchar(120) DEFAULT NULL,
  `document_size` bigint(20) unsigned DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `supervisor_id` bigint(20) unsigned DEFAULT NULL,
  `join_date` date NOT NULL,
  `work_start_time` time DEFAULT NULL,
  `work_end_time` time DEFAULT NULL,
  `contract_duration` varchar(255) NOT NULL,
  `bank_account_number` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_user_id_unique` (`user_id`),
  UNIQUE KEY `employees_id_number_unique` (`id_number`),
  KEY `employees_position_id_foreign` (`position_id`),
  KEY `employees_office_id_foreign` (`office_id`),
  KEY `employees_department_id_foreign` (`department_id`),
  KEY `employees_supervisor_id_foreign` (`supervisor_id`),
  CONSTRAINT `employees_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_office_id_foreign` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_position_id_foreign` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employees_supervisor_id_foreign` FOREIGN KEY (`supervisor_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,1,'Demo Super Admin','N/A','0700000000',NULL,NULL,NULL,NULL,NULL,'Bachelor',6,'salaried',NULL,150000.00,0.00,'Demo Office, Kabul',NULL,NULL,NULL,NULL,NULL,'Administration',NULL,NULL,'2026-09-28',NULL,NULL,'Permanent',NULL,'2026-09-28 00:09:07','2026-09-28 00:09:07',NULL),(2,2,'John Doe','Michael Doe','555-1234',NULL,NULL,NULL,NULL,NULL,'Bachelor',1,'salaried',NULL,5000.00,0.00,'123 Main St, Cityville',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2022-01-15',NULL,NULL,'2 years',NULL,'2026-09-28 00:09:07','2026-09-28 00:09:07',NULL),(3,3,'Jane Smith','Robert Smith','555-5678',NULL,NULL,NULL,NULL,NULL,'Master',1,'salaried',NULL,6500.00,0.00,'456 Oak Ave, Townsville',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2021-11-01',NULL,NULL,'3 years',NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(4,4,'David Johnson','William Johnson','555-9012',NULL,NULL,NULL,NULL,NULL,'High School',1,'salaried',NULL,3500.00,0.00,'789 Pine Rd, Villagetown',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2023-02-20',NULL,NULL,'1 year',NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL);
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `visitor_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `salary_id` bigint(20) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `base_currency_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `document_name` varchar(255) DEFAULT NULL,
  `document_mime` varchar(120) DEFAULT NULL,
  `document_size` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `cnv_curr_shrt` varchar(10) NOT NULL,
  `base_curr_shrt` varchar(10) NOT NULL,
  `rate` decimal(20,8) NOT NULL,
  `base_amount` decimal(20,4) NOT NULL,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `pending_transaction_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `expenses_salary_id_unique` (`salary_id`),
  UNIQUE KEY `expenses_pending_transaction_id_unique` (`pending_transaction_id`),
  KEY `expenses_currency_id_foreign` (`currency_id`),
  KEY `expenses_base_currency_id_foreign` (`base_currency_id`),
  KEY `expenses_recorded_by_index` (`recorded_by`),
  KEY `expense_company_idx` (`company_id`),
  KEY `expense_project_idx` (`project_id`),
  KEY `expenses_visitor_id_foreign` (`visitor_id`),
  KEY `expenses_employee_id_foreign` (`employee_id`),
  KEY `expenses_applicant_id_date_index` (`applicant_id`,`date`),
  CONSTRAINT `expenses_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_base_currency_id_foreign` FOREIGN KEY (`base_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `expenses_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_pending_transaction_id_foreign` FOREIGN KEY (`pending_transaction_id`) REFERENCES `pending_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `expenses_salary_id_foreign` FOREIGN KEY (`salary_id`) REFERENCES `salaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_visitor_id_foreign` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incomes`
--

DROP TABLE IF EXISTS `incomes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `incomes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `receipt_number` varchar(255) DEFAULT NULL,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `visitor_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `base_currency_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(20,6) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `cnv_curr_shrt` varchar(10) NOT NULL,
  `base_curr_shrt` varchar(10) NOT NULL,
  `rate` decimal(20,8) NOT NULL,
  `base_amount` decimal(20,6) NOT NULL,
  `invoice_amount` decimal(20,6) DEFAULT NULL,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `pending_transaction_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `incomes_receipt_number_unique` (`receipt_number`),
  UNIQUE KEY `incomes_pending_transaction_id_unique` (`pending_transaction_id`),
  KEY `incomes_currency_id_foreign` (`currency_id`),
  KEY `incomes_invoice_id_index` (`invoice_id`),
  KEY `incomes_base_currency_id_foreign` (`base_currency_id`),
  KEY `incomes_recorded_by_index` (`recorded_by`),
  KEY `income_company_idx` (`company_id`),
  KEY `incomes_visitor_id_foreign` (`visitor_id`),
  KEY `incomes_employee_id_foreign` (`employee_id`),
  KEY `incomes_applicant_id_date_index` (`applicant_id`,`date`),
  CONSTRAINT `incomes_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incomes_base_currency_id_foreign` FOREIGN KEY (`base_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incomes_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incomes_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `incomes_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incomes_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incomes_pending_transaction_id_foreign` FOREIGN KEY (`pending_transaction_id`) REFERENCES `pending_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incomes_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incomes_visitor_id_foreign` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incomes`
--

LOCK TABLES `incomes` WRITE;
/*!40000 ALTER TABLE `incomes` DISABLE KEYS */;
/*!40000 ALTER TABLE `incomes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_service`
--

DROP TABLE IF EXISTS `invoice_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_service` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `marketer_employee_id` bigint(20) unsigned DEFAULT NULL,
  `outsourcing_company_id` bigint(20) unsigned DEFAULT NULL,
  `snapshot_outsourcing_percentage` decimal(5,2) DEFAULT NULL,
  `snapshot_commission_percentage` decimal(5,2) DEFAULT NULL,
  `snapshot_marketer_commission_percentage` decimal(5,2) DEFAULT NULL,
  `snapshot_name` varchar(255) NOT NULL,
  `snapshot_service_fee` decimal(14,2) NOT NULL,
  `snapshot_service_currency_code` varchar(3) NOT NULL,
  `snapshot_service_currency_symbol` varchar(10) NOT NULL,
  `snapshot_service_rate` decimal(20,8) DEFAULT NULL,
  `snapshot_converted_fee` decimal(20,6) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `line_total` decimal(20,6) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `snapshot_cnv_curr_shrt` varchar(10) NOT NULL,
  `snapshot_base_curr_shrt` varchar(10) NOT NULL,
  `snapshot_rate` decimal(20,8) NOT NULL,
  `snapshot_invoice_rate` decimal(20,8) DEFAULT NULL,
  `contract_service_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_service_invoice_id_service_id_unique` (`invoice_id`,`service_id`),
  KEY `invoice_service_service_id_foreign` (`service_id`),
  KEY `invoice_service_employee_id_service_id_index` (`employee_id`,`service_id`),
  KEY `invoice_service_marketer_employee_idx` (`marketer_employee_id`),
  KEY `invoice_service_outsourcing_company_idx` (`outsourcing_company_id`),
  KEY `invoice_service_contract_service_id_foreign` (`contract_service_id`),
  CONSTRAINT `invoice_service_contract_service_id_foreign` FOREIGN KEY (`contract_service_id`) REFERENCES `contract_service` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoice_service_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `invoice_service_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_service_marketer_employee_id_foreign` FOREIGN KEY (`marketer_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoice_service_outsourcing_company_id_foreign` FOREIGN KEY (`outsourcing_company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `invoice_service_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_service`
--

LOCK TABLES `invoice_service` WRITE;
/*!40000 ALTER TABLE `invoice_service` DISABLE KEYS */;
INSERT INTO `invoice_service` VALUES (1,1,1,NULL,NULL,NULL,NULL,NULL,NULL,'Initial Education Consultation',1500.00,'AFN','؋',1.00000000,1500.000000,1,1500.000000,'2026-09-28 00:09:13','2026-09-28 00:09:13','AFN','AFN',1.00000000,1.00000000,1),(2,2,2,NULL,NULL,NULL,NULL,NULL,NULL,'Document Review',5000.00,'AFN','؋',1.00000000,5000.000000,1,5000.000000,'2026-09-28 00:09:13','2026-09-28 00:09:13','AFN','AFN',1.00000000,1.00000000,2);
/*!40000 ALTER TABLE `invoice_service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_service_commission_recipients`
--

DROP TABLE IF EXISTS `invoice_service_commission_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_service_commission_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_service_id` bigint(20) unsigned NOT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_service_commission_recipient_unique` (`invoice_service_id`,`employee_id`),
  KEY `invoice_service_commission_recipients_employee_id_foreign` (`employee_id`),
  CONSTRAINT `invoice_service_commission_recipients_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `invoice_service_commission_recipients_invoice_service_id_foreign` FOREIGN KEY (`invoice_service_id`) REFERENCES `invoice_service` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_service_commission_recipients`
--

LOCK TABLES `invoice_service_commission_recipients` WRITE;
/*!40000 ALTER TABLE `invoice_service_commission_recipients` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_service_commission_recipients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `invoice_number` varchar(255) DEFAULT NULL,
  `applicant_id` bigint(20) unsigned NOT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `base_currency_id` bigint(20) unsigned DEFAULT NULL,
  `deptor` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'Issued',
  `issue_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `tax_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `currency_name` varchar(255) NOT NULL,
  `currency_symbol` varchar(255) NOT NULL,
  `currency_shortname` varchar(255) NOT NULL,
  `subtotal` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `amount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `discount` decimal(20,6) NOT NULL DEFAULT 0.000000,
  `notes` text DEFAULT NULL,
  `customer_company` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_contact` varchar(255) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `customer_tax_number` varchar(255) DEFAULT NULL,
  `payment_detail_id` bigint(20) unsigned DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `payment_instructions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_instructions`)),
  `voided_at` timestamp NULL DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `cnv_curr_shrt` varchar(10) NOT NULL,
  `base_curr_shrt` varchar(10) NOT NULL,
  `rate` decimal(20,8) NOT NULL,
  `base_amount` decimal(20,6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  KEY `invoices_currency_id_foreign` (`currency_id`),
  KEY `invoices_payment_detail_id_foreign` (`payment_detail_id`),
  KEY `invoices_base_currency_id_foreign` (`base_currency_id`),
  KEY `invoices_recorded_by_index` (`recorded_by`),
  KEY `invoices_applicant_id_idx` (`applicant_id`),
  CONSTRAINT `invoices_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`),
  CONSTRAINT `invoices_base_currency_id_foreign` FOREIGN KEY (`base_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `invoices_payment_detail_id_foreign` FOREIGN KEY (`payment_detail_id`) REFERENCES `payment_details` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (1,NULL,'INV-2026-000001',1,1,1,'John Doe','Issued',NULL,NULL,0.00,'Afghan Afghani','؋','AFN',1500.000000,1500.000000,0.000000,'Seeded invoice',NULL,NULL,'555-1234',NULL,NULL,NULL,'Bank Transfer','[]',NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL,'AFN','AFN',1.00000000,1500.000000),(2,NULL,'INV-2026-000002',2,1,1,'Jane Smith','Issued',NULL,NULL,0.00,'Afghan Afghani','؋','AFN',5000.000000,5000.000000,0.000000,'Seeded invoice',NULL,NULL,'555-5678',NULL,NULL,NULL,'Bank Transfer','[]',NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL,'AFN','AFN',1.00000000,5000.000000);
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `languages`
--

DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `languages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(10) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `languages_name_unique` (`name`),
  UNIQUE KEY `languages_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `languages`
--

LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
INSERT INTO `languages` VALUES (1,'English','en',1,'2026-09-28 00:09:14','2026-09-28 00:09:14',NULL),(2,'German','de',1,'2026-09-28 00:09:14','2026-09-28 00:09:14',NULL),(3,'French','fr',1,'2026-09-28 00:09:14','2026-09-28 00:09:14',NULL);
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visitor_id` bigint(20) unsigned DEFAULT NULL,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `contact` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `service_interest` varchar(255) DEFAULT NULL,
  `marketer` varchar(255) DEFAULT NULL,
  `marketer_id` bigint(20) unsigned DEFAULT NULL,
  `stage` enum('new','contacted','qualified','proposal','won','lost') NOT NULL DEFAULT 'new',
  `probability` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `probability_is_manual` tinyint(1) NOT NULL DEFAULT 0,
  `estimated_value` decimal(20,4) DEFAULT NULL,
  `next_follow_up_at` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `owner_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leads_applicant_id_unique` (`applicant_id`),
  KEY `leads_visitor_id_foreign` (`visitor_id`),
  KEY `leads_owner_id_foreign` (`owner_id`),
  KEY `leads_stage_next_follow_up_at_index` (`stage`,`next_follow_up_at`),
  KEY `leads_marketer_id_idx` (`marketer_id`),
  CONSTRAINT `leads_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_marketer_id_foreign` FOREIGN KEY (`marketer_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_visitor_id_foreign` FOREIGN KEY (`visitor_id`) REFERENCES `visitors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
INSERT INTO `leads` VALUES (1,NULL,NULL,'Amina Rahimi','Rahimi Family','+93 701 111 111','amina.rahimi@example.com','Student Visa Application Support',NULL,1,'qualified',65,0,20000.0000,NULL,'Interested in a master\'s programme in Canada.',NULL,1,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(2,NULL,NULL,'Omid Noori','Noori Education Group','+93 702 222 222','omid.noori@example.com','University Application Support',NULL,2,'contacted',35,0,15000.0000,NULL,'Follow up after the next intake information session.',NULL,1,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL);
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leaves`
--

DROP TABLE IF EXISTS `leaves`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leaves` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `duration_days` decimal(3,1) NOT NULL DEFAULT 1.0,
  `reason` varchar(1000) NOT NULL,
  `approval_note` text DEFAULT NULL,
  `is_granted` tinyint(1) NOT NULL DEFAULT 0,
  `is_paid` tinyint(1) DEFAULT NULL,
  `paid_days` decimal(3,1) DEFAULT NULL,
  `paid_override` tinyint(1) NOT NULL DEFAULT 0,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `granted_by` bigint(20) unsigned DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `paid_time_offs_employee_id_date_unique` (`employee_id`,`date`),
  KEY `paid_time_offs_requested_by_foreign` (`requested_by`),
  KEY `paid_time_offs_granted_by_foreign` (`granted_by`),
  KEY `paid_time_offs_employee_id_year_index` (`employee_id`,`year`),
  CONSTRAINT `paid_time_offs_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `paid_time_offs_granted_by_foreign` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `paid_time_offs_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leaves`
--

LOCK TABLES `leaves` WRITE;
/*!40000 ALTER TABLE `leaves` DISABLE KEYS */;
/*!40000 ALTER TABLE `leaves` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `marketers`
--

DROP TABLE IF EXISTS `marketers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `marketers_user_id_unique` (`user_id`),
  UNIQUE KEY `marketers_employee_id_unique` (`employee_id`),
  CONSTRAINT `marketers_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marketers`
--

LOCK TABLES `marketers` WRITE;
/*!40000 ALTER TABLE `marketers` DISABLE KEYS */;
INSERT INTO `marketers` VALUES (1,NULL,'Sarah Johnson','sarah.johnson@example.com','+93 700 000 001','active','Demo CRM marketer','2026-09-28 00:09:13','2026-09-28 00:09:13',NULL,NULL),(2,NULL,'Michael Brown','michael.brown@example.com','+93 700 000 002','active','Demo CRM marketer','2026-09-28 00:09:13','2026-09-28 00:09:13',NULL,NULL),(3,NULL,'David Wilson','david.wilson@example.com','+93 700 000 003','active','Demo CRM marketer','2026-09-28 00:09:13','2026-09-28 00:09:13',NULL,NULL);
/*!40000 ALTER TABLE `marketers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0000_01_01_000008_create_currencies_table',1),(2,'0001_01_01_000000_create_users_table',1),(3,'0001_01_01_000001_create_cache_table',1),(4,'0001_02_01_000002_create_jobs_table',1),(5,'2023_01_01_000000_create_crm_tables',1),(6,'2024_01_01_000001_create_settings_table',1),(7,'2024_01_01_000002_create_rbac_tables',1),(8,'2024_01_01_000003_create_hr_tables',1),(9,'2024_01_01_000005_create_contract_tables',1),(10,'2024_01_01_000006_create_finance_tables',1),(11,'2026_08_04_055543_create_kanban_tables',1),(12,'2026_08_10_000000_create_notification_and_audit_tables',1),(13,'2026_08_10_000001_create_bonus_tables',1),(14,'2026_08_13_000000_create_reporting_tables',1),(15,'2026_08_24_000000_add_mail_delivery_fields_to_settings_table',1),(16,'2026_08_25_000000_add_results_and_attachments_to_tasks',1),(17,'2026_08_25_000001_add_soft_deletes_to_task_attachments_table',1),(18,'2026_08_26_000001_add_contract_letterhead_safe_area_to_settings_table',1),(19,'2026_08_29_000000_make_audit_history_permanent',1),(20,'2026_08_29_000001_replace_tertiary_setting_with_vuexy_theme_palette',1),(21,'2026_08_29_000002_repair_currency_snapshots_and_history',1),(22,'2026_08_29_000003_add_currency_snapshots_to_positions_and_bonuses',1),(23,'2026_08_30_000000_complete_operational_workflows',1),(24,'2026_08_30_000001_align_legacy_theme_palette_defaults',1),(25,'2026_08_30_000001_remove_contract_service_relation',1),(26,'2026_08_31_000000_add_automatic_attendance_setting',1),(27,'2026_08_31_000000_expand_finance_money_precision',1),(28,'2026_08_31_000001_remove_employee_id_and_assurance_files',1),(29,'2026_08_31_000002_add_unpaid_attendance_status',1),(30,'2026_08_31_100000_unify_leave_crm_and_project_customer_domains',1),(31,'2026_09_01_000000_add_soft_deletes_to_crm_interactions',1),(32,'2026_09_01_000001_add_customer_relationship_notes',1),(33,'2026_09_01_000001_rename_stocks_to_assets',1),(34,'2026_09_01_000002_rename_stock_permissions_to_asset_permissions',1),(35,'2026_09_01_000003_add_email_to_visitors',1),(36,'2026_09_01_000004_support_fractional_leave_accounting',1),(37,'2026_09_02_000001_add_user_id_to_marketers_table',1),(38,'2026_09_02_000002_create_commission_ledger',1),(39,'2026_09_02_000003_add_commission_adjustments',1),(40,'2026_09_02_000004_align_commissions_finance_and_marketers',1),(41,'2026_09_02_000005_remove_duplicate_bonus_eligibility_reason',1),(42,'2026_09_03_000000_add_marketer_employee_attribution_to_crm_records',1),(43,'2026_09_03_000001_add_customer_service_assignment_and_attribution',1),(44,'2026_09_03_000002_restore_employee_service_commission_capabilities',1),(45,'2026_09_03_000100_restore_project_service_assignments',1),(46,'2026_09_06_000000_harden_core_data_integrity',1),(47,'2026_09_07_000000_add_bonus_currency_snapshot_source',1),(48,'2026_09_10_000000_create_support_tickets_table',1),(49,'2026_09_10_000001_create_support_ticket_attachments_table',1),(50,'2026_09_12_000000_remove_class_type_from_invoices_table',1),(51,'2026_09_13_000000_add_manual_probability_to_leads_table',1),(52,'2026_09_13_000001_add_marketer_commission_accruals',1),(53,'2026_09_13_000002_add_active_status_to_contract_policies',1),(54,'2026_09_15_000000_disable_audit_scopes_by_default',1),(55,'2026_09_15_000001_support_multiple_customer_service_commission_employees',1),(56,'2026_09_16_000001_create_outsourcing_ledger',1),(57,'2026_09_17_000000_create_educational_visa_case_foundation',1),(58,'2026_09_17_000001_add_versioned_contract_templates',1),(59,'2026_09_17_000002_link_contracts_to_template_versions',1),(60,'2026_09_17_000003_create_educational_services_and_bonus_sources',1),(61,'2026_09_17_000004_create_pending_transactions',1),(62,'2026_09_17_000005_link_official_finance_records_to_pending_transactions',1),(63,'2026_09_18_000000_applicant_stage_exit_and_appeal_paths',1),(64,'2026_09_18_000001_add_soft_deletes_to_educational_bonus_sources',1),(65,'2026_09_18_000002_link_official_finance_to_case_dimensions',1),(66,'2026_09_18_000003_create_applicant_universities',1),(67,'2026_09_18_000004_add_case_dimensions_to_educational_services',1),(68,'2026_09_18_000005_seed_educational_contract_types',1),(69,'2026_09_18_000006_add_applicant_portal_identity',1),(70,'2026_09_20_000000_complete_educational_workflow_dimensions',1),(71,'2026_09_20_000001_unify_packages_services_and_case_wins',1),(72,'2026_09_21_000001_add_salary_payment_ledger',1),(73,'2026_09_21_000002_complete_contract_services_branding_and_attachments',1),(74,'2026_09_21_000003_add_country_and_language_to_contract_policies',1),(75,'2026_09_22_000000_make_service_duration_optional_and_link_portal_leads',1),(76,'2026_09_23_000001_merge_customer_service_into_contracts_additive',1),(77,'2026_09_23_000002_drop_customer_service_merge_into_contracts',1),(78,'2026_09_24_000001_single_service_per_contract',1),(79,'2026_09_26_000001_merge_customers_into_applicants_additive',1),(80,'2026_09_26_000002_merge_customers_into_applicants_cutover',1),(81,'2026_09_26_000003_complete_contract_template_versioning',1),(82,'2026_09_27_000000_add_role_hierarchy_and_departments',1),(83,'2026_09_27_000001_link_contracts_to_service_contracts',1),(84,'2026_09_27_000002_make_office_a_singleton',1),(85,'2026_09_27_000003_add_applicant_attachments_and_remove_company',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `monthly_analytics`
--

DROP TABLE IF EXISTS `monthly_analytics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `monthly_analytics` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `attendance_present` bigint(20) unsigned NOT NULL DEFAULT 0,
  `attendance_absent` bigint(20) unsigned NOT NULL DEFAULT 0,
  `attendance_paid_leave` bigint(20) unsigned NOT NULL DEFAULT 0,
  `attendance_incomplete` bigint(20) unsigned NOT NULL DEFAULT 0,
  `worked_minutes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `overtime_minutes` bigint(20) unsigned NOT NULL DEFAULT 0,
  `income_total` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `expense_total` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `tasks_created` bigint(20) unsigned NOT NULL DEFAULT 0,
  `tasks_completed` bigint(20) unsigned NOT NULL DEFAULT 0,
  `contracts_created` bigint(20) unsigned NOT NULL DEFAULT 0,
  `contracts_cancelled` bigint(20) unsigned NOT NULL DEFAULT 0,
  `employees_onboarded` bigint(20) unsigned NOT NULL DEFAULT 0,
  `bonus_nominations` bigint(20) unsigned NOT NULL DEFAULT 0,
  `bonuses_granted` bigint(20) unsigned NOT NULL DEFAULT 0,
  `bonus_base_total` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monthly_analytics_year_month_unique` (`year`,`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `monthly_analytics`
--

LOCK TABLES `monthly_analytics` WRITE;
/*!40000 ALTER TABLE `monthly_analytics` DISABLE KEYS */;
/*!40000 ALTER TABLE `monthly_analytics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `monthly_attendance_summaries`
--

DROP TABLE IF EXISTS `monthly_attendance_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `monthly_attendance_summaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `present_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `absent_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `paid_leave_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `unpaid_leave_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `incomplete_days` smallint(5) unsigned NOT NULL DEFAULT 0,
  `gross_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `break_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `net_worked_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `required_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `late_arrival_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `early_departure_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `overtime_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `calculated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `monthly_attendance_employee_period_unique` (`employee_id`,`year`,`month`),
  KEY `monthly_attendance_summaries_year_month_index` (`year`,`month`),
  CONSTRAINT `monthly_attendance_summaries_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `monthly_attendance_summaries`
--

LOCK TABLES `monthly_attendance_summaries` WRITE;
/*!40000 ALTER TABLE `monthly_attendance_summaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `monthly_attendance_summaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_user`
--

DROP TABLE IF EXISTS `notification_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_user` (
  `notification_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `is_read` tinyint(1) DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `notification_user_notification_id_user_id_unique` (`notification_id`,`user_id`),
  KEY `notification_user_user_id_deleted_at_is_read_index` (`user_id`,`deleted_at`,`is_read`),
  CONSTRAINT `notification_user_notification_id_foreign` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notification_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_user`
--

LOCK TABLES `notification_user` WRITE;
/*!40000 ALTER TABLE `notification_user` DISABLE KEYS */;
INSERT INTO `notification_user` VALUES (1,1,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(1,2,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(1,3,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(1,4,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(1,5,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(1,6,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(1,7,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(1,8,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(1,9,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(2,1,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(2,2,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(2,3,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(2,4,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(2,5,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(2,6,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(2,7,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(2,8,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(2,9,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(3,1,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(3,2,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(3,3,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(3,4,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(3,5,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(3,6,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(3,7,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(3,8,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(3,9,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(4,1,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(5,2,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13'),(6,3,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13');
/*!40000 ALTER TABLE `notification_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL DEFAULT 'info',
  `notification_key` varchar(100) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `date_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `go_to_url` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_employee_id_date_time_index` (`employee_id`,`date_time`),
  KEY `notifications_notification_key_date_time_index` (`notification_key`,`date_time`),
  CONSTRAINT `notifications_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,'task',NULL,'Et libero minus est ut perspiciatis.','Quo modi autem mollitia asperiores sequi aut dolorem error voluptatibus ad in.',NULL,'2026-09-16 21:43:31',NULL,'/home','2026-09-28 00:09:13','2026-09-28 00:09:13'),(2,'info',NULL,'Non ad ex dolor.','Temporibus qui vel dolor nostrum perferendis quos eaque ut est rerum magnam cumque rem.',NULL,'2026-09-26 09:30:40',NULL,'/home','2026-09-28 00:09:13','2026-09-28 00:09:13'),(3,'info',NULL,'Sit tenetur ut dolores.','Vitae sapiente aut libero occaecati culpa voluptatem enim explicabo aut odit unde facere.',NULL,'2026-09-22 16:54:38',NULL,'/home','2026-09-28 00:09:13','2026-09-28 00:09:13'),(4,'warning',NULL,'Et necessitatibus molestias similique.','Pariatur facilis dolorem suscipit libero numquam aspernatur ipsam odit sint.',NULL,'2026-09-17 03:04:32',NULL,'/home','2026-09-28 00:09:13','2026-09-28 00:09:13'),(5,'success',NULL,'Necessitatibus aliquam cum.','Labore et ut maxime non natus eius quos amet ipsa perferendis ut et iste eum in.',NULL,'2026-09-15 21:36:54',NULL,'/home','2026-09-28 00:09:13','2026-09-28 00:09:13'),(6,'success',NULL,'Reprehenderit laudantium debitis voluptate suscipit.','Ipsam cupiditate similique at fugiat est dolorem cupiditate non assumenda nemo soluta.',NULL,'2026-09-15 03:17:33',NULL,'/home','2026-09-28 00:09:13','2026-09-28 00:09:13');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `offices`
--

DROP TABLE IF EXISTS `offices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `offices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `is_singleton` tinyint(1) NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `code` varchar(30) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `geofence_radius_meters` int(10) unsigned NOT NULL DEFAULT 100,
  `geofence_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `offices_is_singleton_unique` (`is_singleton`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `offices`
--

LOCK TABLES `offices` WRITE;
/*!40000 ALTER TABLE `offices` DISABLE KEYS */;
INSERT INTO `offices` VALUES (1,1,'Head Office','HQ',NULL,NULL,NULL,NULL,NULL,100,0,'active','2026-09-28 00:09:06','2026-09-28 00:09:06',NULL);
/*!40000 ALTER TABLE `offices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outsourcing_accruals`
--

DROP TABLE IF EXISTS `outsourcing_accruals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `outsourcing_accruals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `income_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_service_id` bigint(20) unsigned DEFAULT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned DEFAULT NULL,
  `contract_service_id` bigint(20) unsigned DEFAULT NULL,
  `base_currency_id` bigint(20) unsigned NOT NULL,
  `outsourcing_settlement_id` bigint(20) unsigned DEFAULT NULL,
  `earned_at` date NOT NULL,
  `source_payment_base_amount` decimal(20,6) NOT NULL,
  `eligible_base_amount` decimal(20,6) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `outsourcing_base_amount` decimal(20,6) NOT NULL,
  `entry_type` varchar(255) NOT NULL DEFAULT 'earned',
  `adjustment_reason` text DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outsourcing_accrual_source_unique` (`income_id`,`invoice_service_id`),
  KEY `outsourcing_accruals_invoice_id_foreign` (`invoice_id`),
  KEY `outsourcing_accruals_invoice_service_id_foreign` (`invoice_service_id`),
  KEY `outsourcing_accruals_service_id_foreign` (`service_id`),
  KEY `outsourcing_accruals_base_currency_id_foreign` (`base_currency_id`),
  KEY `outsourcing_accruals_outsourcing_settlement_id_foreign` (`outsourcing_settlement_id`),
  KEY `outsourcing_accruals_recorded_by_foreign` (`recorded_by`),
  KEY `outsourcing_accrual_company_status_idx` (`company_id`,`status`,`earned_at`),
  KEY `outsourcing_accruals_contract_service_id_foreign` (`contract_service_id`),
  CONSTRAINT `outsourcing_accruals_base_currency_id_foreign` FOREIGN KEY (`base_currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `outsourcing_accruals_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `outsourcing_accruals_contract_service_id_foreign` FOREIGN KEY (`contract_service_id`) REFERENCES `contract_service` (`id`) ON DELETE SET NULL,
  CONSTRAINT `outsourcing_accruals_income_id_foreign` FOREIGN KEY (`income_id`) REFERENCES `incomes` (`id`),
  CONSTRAINT `outsourcing_accruals_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`),
  CONSTRAINT `outsourcing_accruals_invoice_service_id_foreign` FOREIGN KEY (`invoice_service_id`) REFERENCES `invoice_service` (`id`),
  CONSTRAINT `outsourcing_accruals_outsourcing_settlement_id_foreign` FOREIGN KEY (`outsourcing_settlement_id`) REFERENCES `outsourcing_settlements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `outsourcing_accruals_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `outsourcing_accruals_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outsourcing_accruals`
--

LOCK TABLES `outsourcing_accruals` WRITE;
/*!40000 ALTER TABLE `outsourcing_accruals` DISABLE KEYS */;
/*!40000 ALTER TABLE `outsourcing_accruals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `outsourcing_settlements`
--

DROP TABLE IF EXISTS `outsourcing_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `outsourcing_settlements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `settlement_number` varchar(255) DEFAULT NULL,
  `company_id` bigint(20) unsigned NOT NULL,
  `base_currency_id` bigint(20) unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `gross_base_amount` decimal(20,6) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_by` bigint(20) unsigned DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `expense_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `outsourcing_settlements_settlement_number_unique` (`settlement_number`),
  UNIQUE KEY `outsourcing_settlements_expense_id_unique` (`expense_id`),
  KEY `outsourcing_settlements_base_currency_id_foreign` (`base_currency_id`),
  KEY `outsourcing_settlements_recorded_by_foreign` (`recorded_by`),
  KEY `outsourcing_settlements_approved_by_foreign` (`approved_by`),
  KEY `outsourcing_settlements_paid_by_foreign` (`paid_by`),
  KEY `outsourcing_settlement_period_idx` (`company_id`,`status`,`period_start`,`period_end`),
  CONSTRAINT `outsourcing_settlements_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `outsourcing_settlements_base_currency_id_foreign` FOREIGN KEY (`base_currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `outsourcing_settlements_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `outsourcing_settlements_expense_id_foreign` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `outsourcing_settlements_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `outsourcing_settlements_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `outsourcing_settlements`
--

LOCK TABLES `outsourcing_settlements` WRITE;
/*!40000 ALTER TABLE `outsourcing_settlements` DISABLE KEYS */;
/*!40000 ALTER TABLE `outsourcing_settlements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `package_service`
--

DROP TABLE IF EXISTS `package_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `package_service` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `allocated_fee` decimal(20,6) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_service_package_id_service_id_unique` (`package_id`,`service_id`),
  KEY `package_service_service_id_foreign` (`service_id`),
  CONSTRAINT `package_service_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `package_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `package_service_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `package_service`
--

LOCK TABLES `package_service` WRITE;
/*!40000 ALTER TABLE `package_service` DISABLE KEYS */;
INSERT INTO `package_service` VALUES (1,1,2,NULL,0,'2026-09-28 00:09:14','2026-09-28 00:09:14'),(2,1,3,NULL,0,'2026-09-28 00:09:14','2026-09-28 00:09:14'),(3,2,2,NULL,0,'2026-09-28 00:09:14','2026-09-28 00:09:14'),(4,2,4,NULL,0,'2026-09-28 00:09:14','2026-09-28 00:09:14'),(5,3,5,NULL,0,'2026-09-28 00:09:14','2026-09-28 00:09:14'),(6,3,8,NULL,0,'2026-09-28 00:09:14','2026-09-28 00:09:14');
/*!40000 ALTER TABLE `package_service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `package_types`
--

DROP TABLE IF EXISTS `package_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `package_types` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `service_type` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `fee` decimal(20,6) DEFAULT NULL,
  `service_manifest` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`service_manifest`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_types_name_unique` (`name`),
  KEY `package_types_service_type_index` (`service_type`),
  KEY `package_types_currency_id_foreign` (`currency_id`),
  CONSTRAINT `package_types_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `package_types`
--

LOCK TABLES `package_types` WRITE;
/*!40000 ALTER TABLE `package_types` DISABLE KEYS */;
INSERT INTO `package_types` VALUES (1,'University Application Package',NULL,NULL,1,'2026-09-28 00:09:14','2026-09-28 00:09:14',NULL,1,NULL,'[2,3]'),(2,'Scholarship Support Package',NULL,NULL,1,'2026-09-28 00:09:14','2026-09-28 00:09:14',NULL,1,NULL,'[2,4]'),(3,'Test Preparation Package',NULL,NULL,1,'2026-09-28 00:09:14','2026-09-28 00:09:14',NULL,1,NULL,'[5,8]');
/*!40000 ALTER TABLE `package_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_details`
--

DROP TABLE IF EXISTS `payment_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_details` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `method` varchar(255) NOT NULL,
  `instruction` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_details_method_index` (`method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_details`
--

LOCK TABLES `payment_details` WRITE;
/*!40000 ALTER TABLE `payment_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pending_transactions`
--

DROP TABLE IF EXISTS `pending_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pending_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_type` varchar(255) NOT NULL,
  `reason` text NOT NULL,
  `amount` decimal(20,6) NOT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned DEFAULT NULL,
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `approver_id` bigint(20) unsigned DEFAULT NULL,
  `approval_status` varchar(255) NOT NULL DEFAULT 'draft',
  `rejection_reason` text DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `posted_record_type` varchar(255) DEFAULT NULL,
  `posted_record_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pending_transactions_currency_id_foreign` (`currency_id`),
  KEY `pending_transactions_applicant_id_foreign` (`applicant_id`),
  KEY `pending_transactions_employee_id_foreign` (`employee_id`),
  KEY `pending_transactions_requested_by_foreign` (`requested_by`),
  KEY `pending_transactions_approver_id_foreign` (`approver_id`),
  KEY `pending_transactions_posted_record_type_posted_record_id_index` (`posted_record_type`,`posted_record_id`),
  KEY `pending_transactions_approval_status_transaction_type_index` (`approval_status`,`transaction_type`),
  CONSTRAINT `pending_transactions_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pending_transactions_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pending_transactions_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `pending_transactions_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pending_transactions_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pending_transactions`
--

LOCK TABLES `pending_transactions` WRITE;
/*!40000 ALTER TABLE `pending_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `pending_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'general',
  `guard_name` varchar(255) NOT NULL DEFAULT 'web',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=243 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'manage_audit_scope','core','api','2026-09-28 00:01:34','2026-09-28 00:09:02',NULL),(2,'view_commissions','human_resources','api','2026-09-28 00:00:50','2026-09-28 00:00:50',NULL),(3,'manage_commission_settlements','human_resources','api','2026-09-28 00:00:50','2026-09-28 00:00:50',NULL),(4,'approve_commissions','human_resources','api','2026-09-28 00:00:50','2026-09-28 00:00:50',NULL),(5,'pay_commissions','human_resources','api','2026-09-28 00:00:50','2026-09-28 00:00:50',NULL),(6,'view_outsourcing','finance','api','2026-09-28 00:01:53','2026-09-28 00:01:53',NULL),(7,'manage_outsourcing_settlements','finance','api','2026-09-28 00:01:53','2026-09-28 00:01:53',NULL),(8,'approve_outsourcing','finance','api','2026-09-28 00:01:53','2026-09-28 00:01:53',NULL),(9,'pay_outsourcing','finance','api','2026-09-28 00:01:53','2026-09-28 00:01:53',NULL),(10,'view_own_applicant_portal','applicant_portal','api','2026-09-28 00:02:03','2026-09-28 00:02:03',NULL),(11,'edit_own_applicant_profile','applicant_portal','api','2026-09-28 00:02:03','2026-09-28 00:02:03',NULL),(12,'invite_applicant_portal','educational_visa','api','2026-09-28 00:02:03','2026-09-28 00:02:03',NULL),(13,'view_packages','educational_visa','api','2026-09-28 00:02:06','2026-09-28 00:09:03',NULL),(14,'manage_packages','educational_visa','api','2026-09-28 00:02:06','2026-09-28 00:09:03',NULL),(15,'issue_packages','educational_visa','api','2026-09-28 00:02:06','2026-09-28 00:09:03',NULL),(16,'view_dashboard','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(17,'view_reports','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(18,'export_reports','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(19,'view_automation_runs','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(20,'view_analytics','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(21,'view_settings','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(22,'edit_settings','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(23,'view_audit_logs','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(24,'view_deleted_records','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(25,'review_audit_logs','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(26,'revert_audit_changes','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(27,'view_subordinate_records','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(28,'manage_subordinate_records','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(29,'view_team_audit_logs','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(30,'view_all_audits','core','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(31,'view_users','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(32,'create_users','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(33,'edit_users','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(34,'delete_users','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(35,'manage_user_status','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(36,'request_account_deactivation','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(37,'cancel_own_deactivation_request','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(38,'view_account_deactivation_requests','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(39,'review_account_deactivation_requests','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(40,'reactivate_user_accounts','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(41,'assign_user_roles','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(42,'view_roles','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(43,'create_roles','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(44,'edit_roles','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(45,'delete_roles','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(46,'manage_role_permissions','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(47,'manage_role_hierarchy','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(48,'view_permissions','identity_access','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(49,'view_employees','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(50,'view_all_employees','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(51,'create_employees','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(52,'edit_employees','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(53,'manage_all_employees','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(54,'delete_employees','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(55,'view_departments','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(56,'manage_departments','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(57,'assign_employee_supervisor','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(58,'view_paid_time_off','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(59,'create_paid_time_off','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(60,'edit_paid_time_off','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(61,'delete_paid_time_off','human_resources','api','2026-09-28 00:09:02','2026-09-28 00:09:02',NULL),(62,'request_own_paid_time_off','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(63,'grant_paid_time_off','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(64,'print_paid_time_off','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(65,'view_attendance','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(66,'record_attendance','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(67,'delete_attendance','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(68,'view_unmarked_attendance_staff','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(69,'manage_own_workday','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(70,'submit_own_daily_report','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(71,'view_salaries','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(72,'create_salaries','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(73,'edit_salaries','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(74,'delete_salaries','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(75,'view_salary_deductions','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(76,'create_salary_deductions','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(77,'edit_salary_deductions','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(78,'delete_salary_deductions','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(79,'view_bonuses','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(80,'nominate_bonus_candidates','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(81,'edit_bonus_nominations','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(82,'grant_bonuses','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(83,'manage_bonus_rules','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(84,'delete_bonus_nominations','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(85,'view_positions','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(86,'manage_positions','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(87,'view_marketers','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(88,'manage_marketers','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(89,'view_offices','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(90,'manage_offices','human_resources','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(91,'view_contracts','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(92,'view_all_contracts','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(93,'create_contracts','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(94,'edit_contracts','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(95,'cancel_contracts','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(96,'delete_contracts','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(97,'download_contracts','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(98,'view_contract_templates','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(99,'manage_contract_templates','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(100,'create_contract_template_versions','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(101,'edit_draft_contract_template_versions','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(102,'activate_contract_template_versions','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(103,'archive_contract_template_versions','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(104,'view_contract_policies','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(105,'manage_contract_policies','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(106,'view_contract_types','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(107,'manage_contract_types','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(108,'view_companies','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(109,'create_companies','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(110,'edit_companies','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(111,'delete_companies','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(112,'view_services','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(113,'manage_services','customer_contracts','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(114,'view_invoices','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(115,'create_invoices','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(116,'edit_invoices','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(117,'void_invoices','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(118,'delete_invoices','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(119,'view_payment_details','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(120,'create_payment_details','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(121,'edit_payment_details','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(122,'delete_payment_details','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(123,'view_incomes','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(124,'create_incomes','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(125,'edit_incomes','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(126,'delete_incomes','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(127,'view_expenses','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(128,'create_expenses','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(129,'edit_expenses','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(130,'delete_expenses','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(131,'view_recurring_events','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(132,'create_recurring_events','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(133,'edit_recurring_events','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(134,'delete_recurring_events','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(135,'manage_recurring_events','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(136,'execute_recurring_events','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(137,'create_recurring_incomes','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(138,'create_recurring_expenses','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(139,'create_recurring_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(140,'view_assets','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(141,'create_assets','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(142,'edit_assets','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(143,'delete_assets','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(144,'view_currencies','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(145,'manage_currencies','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(146,'view_financial_reports','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(147,'export_financial_reports','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(148,'view_projects','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(149,'create_projects','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(150,'edit_projects','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(151,'delete_projects','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(152,'view_assigned_boards','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(153,'view_all_boards','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(154,'create_boards','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(155,'edit_assigned_boards','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(156,'edit_all_boards','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(157,'delete_assigned_boards','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(158,'delete_all_boards','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(159,'reorder_boards','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(160,'assign_board_members','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(161,'view_assigned_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(162,'view_all_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(163,'create_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(164,'edit_assigned_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(165,'edit_all_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(166,'delete_assigned_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(167,'delete_all_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(168,'move_assigned_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(169,'move_all_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(170,'assign_task_members','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(171,'manage_task_dependencies','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(172,'manage_task_tags','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(173,'view_task_tags','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(174,'manage_task_tags_catalog','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(175,'create_subtasks_on_assigned_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(176,'edit_own_subtasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(177,'edit_all_subtasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(178,'delete_own_subtasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(179,'delete_all_subtasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(180,'create_comments_on_assigned_tasks','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(181,'edit_own_comments','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(182,'edit_all_comments','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(183,'delete_own_comments','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(184,'delete_all_comments','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(185,'override_edit_window','task_management','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(186,'view_country','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(187,'create_country','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(188,'edit_country','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(189,'delete_country','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(190,'view_education_level','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(191,'create_education_level','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(192,'edit_education_level','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(193,'delete_education_level','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(194,'view_language','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(195,'create_language','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(196,'edit_language','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(197,'delete_language','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(198,'view_package_type','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(199,'create_package_type','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(200,'edit_package_type','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(201,'delete_package_type','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(202,'view_university','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(203,'create_university','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(204,'edit_university','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(205,'delete_university','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(206,'view_semester','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(207,'create_semester','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(208,'edit_semester','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(209,'delete_semester','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(210,'view_applicants','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(211,'view_all_applicants','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(212,'create_applicants','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(213,'edit_applicants','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(214,'delete_applicants','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(215,'change_applicant_stage','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(216,'view_applicant_stages','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(217,'manage_applicant_stages','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(218,'assign_applicant_employee','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(219,'view_applicant_financials','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(220,'view_applicant_contracts','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(221,'view_applicant_services','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(222,'view_educational_services','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(223,'manage_educational_services','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(224,'manage_educational_bonuses','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(225,'view_visa_reports','educational_visa','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(226,'view_pending_transactions','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(227,'create_pending_transactions','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(228,'approve_pending_transactions','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(229,'post_pending_transactions','finance','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(230,'view_visitors','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(231,'create_visitors','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(232,'edit_visitors','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(233,'delete_visitors','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(234,'view_own_notifications','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(235,'update_own_notifications','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(236,'delete_own_notifications','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(237,'send_notifications','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(238,'send_all_notifications','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(239,'upload_files','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(240,'view_files','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(241,'download_files','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL),(242,'delete_files','operations','api','2026-09-28 00:09:03','2026-09-28 00:09:03',NULL);
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `position_user`
--

DROP TABLE IF EXISTS `position_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `position_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `position_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `assigned_at` date DEFAULT NULL,
  `ended_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `position_user_position_id_user_id_unique` (`position_id`,`user_id`),
  KEY `position_user_user_id_foreign` (`user_id`),
  CONSTRAINT `position_user_position_id_foreign` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `position_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `position_user`
--

LOCK TABLES `position_user` WRITE;
/*!40000 ALTER TABLE `position_user` DISABLE KEYS */;
INSERT INTO `position_user` VALUES (1,6,1,'2026-09-28',NULL,'2026-09-28 00:09:07','2026-09-28 00:09:07');
/*!40000 ALTER TABLE `position_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `positions`
--

DROP TABLE IF EXISTS `positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `positions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `salary` decimal(20,4) NOT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `currency_code` varchar(10) DEFAULT NULL,
  `currency_symbol` varchar(10) DEFAULT NULL,
  `currency_decimal_places` tinyint(3) unsigned DEFAULT NULL,
  `rate` decimal(20,8) DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `system_key` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `positions_system_key_unique` (`system_key`),
  KEY `positions_currency_id_foreign` (`currency_id`),
  CONSTRAINT `positions_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `positions`
--

LOCK TABLES `positions` WRITE;
/*!40000 ALTER TABLE `positions` DISABLE KEYS */;
INSERT INTO `positions` VALUES (1,'Marketer','System position for employees who own marketing and referral relationships.',0.0000,NULL,NULL,NULL,NULL,NULL,'active','2026-09-28 00:01:48','2026-09-28 00:01:48',NULL,'marketer'),(2,'Junior Engineer','Entry-level engineering position',50000.0000,NULL,NULL,NULL,NULL,NULL,'active','2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,NULL),(3,'Senior Engineer','Experienced engineering position',80000.0000,NULL,NULL,NULL,NULL,NULL,'active','2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,NULL),(4,'Lead Consultant','Technical leadership position',100000.0000,NULL,NULL,NULL,NULL,NULL,'active','2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,NULL),(5,'Manager','Management position',120000.0000,NULL,NULL,NULL,NULL,NULL,'active','2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,NULL),(6,'Director','Executive leadership position',150000.0000,NULL,NULL,NULL,NULL,NULL,'active','2026-09-28 00:09:06','2026-09-28 00:09:06',NULL,NULL);
/*!40000 ALTER TABLE `positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_service`
--

DROP TABLE IF EXISTS `project_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_service` (
  `project_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`project_id`,`service_id`),
  KEY `project_service_service_id_foreign` (`service_id`),
  CONSTRAINT `project_service_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_service_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_service`
--

LOCK TABLES `project_service` WRITE;
/*!40000 ALTER TABLE `project_service` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `projects_created_by_foreign` (`created_by`),
  KEY `projects_customer_id_status_index` (`status`),
  KEY `projects_applicant_id_idx` (`applicant_id`),
  CONSTRAINT `projects_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `projects_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recurring_event_occurrences`
--

DROP TABLE IF EXISTS `recurring_event_occurrences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_event_occurrences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recurring_event_id` bigint(20) unsigned NOT NULL,
  `occurrence_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `notification_id` bigint(20) unsigned DEFAULT NULL,
  `result_type` varchar(255) DEFAULT NULL,
  `result_id` bigint(20) unsigned DEFAULT NULL,
  `notified_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `skipped_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rec_event_occurrence_date_unique` (`recurring_event_id`,`occurrence_date`),
  KEY `recurring_event_occurrences_notification_id_foreign` (`notification_id`),
  KEY `recurring_event_occurrences_result_type_result_id_index` (`result_type`,`result_id`),
  KEY `recurring_event_occurrences_status_occurrence_date_index` (`status`,`occurrence_date`),
  CONSTRAINT `recurring_event_occurrences_notification_id_foreign` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recurring_event_occurrences_recurring_event_id_foreign` FOREIGN KEY (`recurring_event_id`) REFERENCES `recurring_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recurring_event_occurrences`
--

LOCK TABLES `recurring_event_occurrences` WRITE;
/*!40000 ALTER TABLE `recurring_event_occurrences` DISABLE KEYS */;
/*!40000 ALTER TABLE `recurring_event_occurrences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recurring_events`
--

DROP TABLE IF EXISTS `recurring_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recurring_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_type` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `frequency` varchar(20) NOT NULL,
  `interval` smallint(5) unsigned NOT NULL DEFAULT 1,
  `start_date` date NOT NULL,
  `next_occurrence_date` date NOT NULL,
  `last_occurrence_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `recurring_events_owner_id_foreign` (`owner_id`),
  KEY `recurring_events_status_next_occurrence_date_index` (`status`,`next_occurrence_date`),
  CONSTRAINT `recurring_events_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recurring_events`
--

LOCK TABLES `recurring_events` WRITE;
/*!40000 ALTER TABLE `recurring_events` DISABLE KEYS */;
/*!40000 ALTER TABLE `recurring_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permissions_role_id_permission_id_unique` (`role_id`,`permission_id`),
  KEY `role_permissions_permission_id_foreign` (`permission_id`),
  KEY `role_permissions_role_id_index` (`role_id`),
  CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=799 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1,10,'2026-09-28 00:02:03','2026-09-28 00:02:03'),(2,1,11,'2026-09-28 00:02:03','2026-09-28 00:02:03'),(3,3,16,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(4,3,17,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(5,3,18,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(6,3,19,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(7,3,20,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(8,3,21,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(9,3,22,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(10,3,27,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(11,3,28,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(12,3,29,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(13,3,30,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(14,3,36,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(15,3,37,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(16,3,38,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(17,3,39,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(18,3,40,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(19,3,43,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(20,3,44,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(21,3,45,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(22,3,49,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(23,3,50,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(24,3,51,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(25,3,52,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(26,3,53,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(27,3,54,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(28,3,55,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(29,3,56,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(30,3,57,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(31,3,58,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(32,3,59,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(33,3,60,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(34,3,61,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(35,3,62,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(36,3,63,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(37,3,64,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(38,3,65,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(39,3,66,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(40,3,67,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(41,3,68,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(42,3,69,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(43,3,70,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(44,3,71,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(45,3,72,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(46,3,73,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(47,3,74,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(48,3,75,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(49,3,76,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(50,3,77,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(51,3,78,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(52,3,2,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(53,3,3,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(54,3,4,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(55,3,5,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(56,3,79,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(57,3,80,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(58,3,81,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(59,3,82,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(60,3,83,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(61,3,84,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(62,3,85,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(63,3,86,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(64,3,87,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(65,3,88,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(66,3,89,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(67,3,90,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(68,3,91,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(69,3,92,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(70,3,93,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(71,3,94,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(72,3,95,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(73,3,96,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(74,3,97,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(75,3,98,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(76,3,99,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(77,3,100,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(78,3,101,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(79,3,102,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(80,3,103,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(81,3,104,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(82,3,105,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(83,3,106,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(84,3,107,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(85,3,108,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(86,3,109,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(87,3,110,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(88,3,111,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(89,3,112,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(90,3,113,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(91,3,114,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(92,3,115,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(93,3,116,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(94,3,117,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(95,3,118,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(96,3,119,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(97,3,120,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(98,3,121,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(99,3,122,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(100,3,123,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(101,3,124,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(102,3,125,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(103,3,126,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(104,3,127,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(105,3,128,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(106,3,129,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(107,3,130,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(108,3,131,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(109,3,132,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(110,3,133,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(111,3,134,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(112,3,135,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(113,3,136,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(114,3,137,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(115,3,138,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(116,3,139,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(117,3,140,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(118,3,141,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(119,3,142,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(120,3,143,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(121,3,144,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(122,3,145,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(123,3,146,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(124,3,147,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(125,3,148,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(126,3,149,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(127,3,150,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(128,3,151,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(129,3,152,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(130,3,153,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(131,3,154,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(132,3,155,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(133,3,156,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(134,3,157,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(135,3,158,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(136,3,159,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(137,3,160,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(138,3,161,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(139,3,162,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(140,3,163,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(141,3,164,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(142,3,165,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(143,3,166,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(144,3,167,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(145,3,168,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(146,3,169,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(147,3,170,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(148,3,171,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(149,3,172,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(150,3,173,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(151,3,174,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(152,3,175,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(153,3,176,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(154,3,177,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(155,3,178,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(156,3,179,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(157,3,180,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(158,3,181,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(159,3,182,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(160,3,183,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(161,3,184,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(162,3,185,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(163,3,186,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(164,3,187,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(165,3,188,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(166,3,189,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(167,3,190,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(168,3,191,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(169,3,192,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(170,3,193,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(171,3,194,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(172,3,195,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(173,3,196,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(174,3,197,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(175,3,198,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(176,3,199,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(177,3,200,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(178,3,201,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(179,3,13,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(180,3,14,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(181,3,15,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(182,3,202,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(183,3,203,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(184,3,204,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(185,3,205,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(186,3,206,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(187,3,207,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(188,3,208,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(189,3,209,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(190,3,210,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(191,3,211,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(192,3,212,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(193,3,213,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(194,3,214,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(195,3,215,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(196,3,216,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(197,3,217,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(198,3,218,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(199,3,219,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(200,3,220,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(201,3,221,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(202,3,12,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(203,3,222,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(204,3,223,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(205,3,224,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(206,3,225,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(207,3,10,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(208,3,11,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(209,3,226,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(210,3,227,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(211,3,228,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(212,3,229,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(213,3,230,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(214,3,231,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(215,3,232,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(216,3,233,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(217,3,234,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(218,3,235,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(219,3,236,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(220,3,237,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(221,3,238,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(222,3,239,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(223,3,240,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(224,3,241,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(225,3,242,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(226,4,16,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(227,4,17,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(228,4,18,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(229,4,19,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(230,4,20,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(231,4,69,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(232,4,70,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(233,4,62,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(234,4,36,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(235,4,37,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(236,4,38,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(237,4,39,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(238,4,40,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(239,4,49,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(240,4,58,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(241,4,59,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(242,4,60,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(243,4,61,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(244,4,63,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(245,4,64,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(246,4,65,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(247,4,66,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(248,4,68,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(249,4,79,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(250,4,80,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(251,4,81,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(252,4,2,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(253,4,3,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(254,4,4,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(255,4,85,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(256,4,87,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(257,4,89,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(258,4,91,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(259,4,93,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(260,4,94,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(261,4,95,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(262,4,97,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(263,4,98,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(264,4,99,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(265,4,100,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(266,4,101,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(267,4,102,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(268,4,103,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(269,4,104,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(270,4,112,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(271,4,106,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(272,4,108,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(273,4,114,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(274,4,115,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(275,4,116,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(276,4,119,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(277,4,120,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(278,4,121,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(279,4,122,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(280,4,123,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(281,4,124,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(282,4,125,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(283,4,127,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(284,4,128,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(285,4,129,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(286,4,226,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(287,4,227,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(288,4,228,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(289,4,229,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(290,4,131,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(291,4,132,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(292,4,133,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(293,4,134,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(294,4,135,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(295,4,136,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(296,4,137,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(297,4,138,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(298,4,139,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(299,4,140,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(300,4,141,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(301,4,142,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(302,4,144,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(303,4,146,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(304,4,153,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(305,4,152,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(306,4,154,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(307,4,156,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(308,4,155,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(309,4,159,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(310,4,160,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(311,4,148,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(312,4,149,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(313,4,150,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(314,4,151,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(315,4,162,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(316,4,161,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(317,4,163,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(318,4,165,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(319,4,164,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(320,4,169,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(321,4,168,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(322,4,170,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(323,4,171,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(324,4,172,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(325,4,173,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(326,4,175,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(327,4,177,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(328,4,176,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(329,4,179,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(330,4,178,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(331,4,180,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(332,4,182,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(333,4,181,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(334,4,184,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(335,4,183,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(336,4,230,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(337,4,231,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(338,4,232,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(339,4,234,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(340,4,235,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(341,4,236,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(342,4,237,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(343,4,238,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(344,4,239,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(345,4,240,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(346,4,241,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(347,4,186,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(348,4,187,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(349,4,188,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(350,4,190,'2026-09-28 00:09:04','2026-09-28 00:09:04'),(351,4,191,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(352,4,192,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(353,4,194,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(354,4,195,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(355,4,196,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(356,4,198,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(357,4,199,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(358,4,200,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(359,4,13,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(360,4,14,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(361,4,15,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(362,4,202,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(363,4,203,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(364,4,204,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(365,4,206,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(366,4,207,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(367,4,208,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(368,4,210,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(369,4,211,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(370,4,212,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(371,4,213,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(372,4,214,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(373,4,215,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(374,4,216,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(375,4,217,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(376,4,218,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(377,4,219,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(378,4,220,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(379,4,221,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(380,4,12,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(381,4,222,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(382,4,223,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(383,4,224,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(384,4,225,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(385,4,27,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(386,4,28,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(387,4,55,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(388,4,56,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(389,4,57,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(390,4,29,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(391,4,92,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(392,10,16,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(393,10,36,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(394,10,37,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(395,10,69,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(396,10,70,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(397,10,62,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(398,10,152,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(399,10,161,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(400,10,173,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(401,10,175,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(402,10,176,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(403,10,178,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(404,10,180,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(405,10,181,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(406,10,183,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(407,10,234,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(408,10,235,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(409,10,236,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(410,10,91,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(411,10,93,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(412,10,98,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(413,10,112,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(414,10,186,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(415,10,190,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(416,10,194,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(417,10,198,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(418,10,13,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(419,10,15,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(420,10,202,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(421,10,206,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(422,10,210,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(423,10,212,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(424,10,213,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(425,10,215,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(426,10,216,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(427,10,219,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(428,10,220,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(429,10,221,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(430,10,12,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(431,10,222,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(432,10,223,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(433,10,226,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(434,10,227,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(435,12,16,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(436,12,36,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(437,12,37,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(438,12,69,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(439,12,70,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(440,12,62,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(441,12,152,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(442,12,161,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(443,12,173,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(444,12,234,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(445,12,235,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(446,12,236,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(447,1,234,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(448,1,235,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(449,1,236,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(450,5,16,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(451,5,17,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(452,5,18,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(453,5,19,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(454,5,20,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(455,5,69,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(456,5,70,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(457,5,62,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(458,5,36,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(459,5,37,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(460,5,38,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(461,5,39,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(462,5,40,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(463,5,49,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(464,5,58,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(465,5,59,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(466,5,60,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(467,5,61,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(468,5,63,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(469,5,64,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(470,5,65,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(471,5,66,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(472,5,68,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(473,5,79,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(474,5,80,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(475,5,81,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(476,5,2,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(477,5,3,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(478,5,4,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(479,5,85,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(480,5,87,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(481,5,89,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(482,5,91,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(483,5,93,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(484,5,94,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(485,5,95,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(486,5,97,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(487,5,98,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(488,5,99,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(489,5,100,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(490,5,101,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(491,5,102,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(492,5,103,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(493,5,104,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(494,5,112,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(495,5,106,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(496,5,108,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(497,5,114,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(498,5,115,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(499,5,116,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(500,5,119,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(501,5,120,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(502,5,121,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(503,5,122,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(504,5,123,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(505,5,124,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(506,5,125,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(507,5,127,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(508,5,128,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(509,5,129,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(510,5,226,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(511,5,227,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(512,5,228,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(513,5,229,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(514,5,131,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(515,5,132,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(516,5,133,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(517,5,134,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(518,5,135,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(519,5,136,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(520,5,137,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(521,5,138,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(522,5,139,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(523,5,140,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(524,5,141,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(525,5,142,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(526,5,144,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(527,5,146,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(528,5,153,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(529,5,152,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(530,5,154,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(531,5,156,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(532,5,155,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(533,5,159,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(534,5,160,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(535,5,148,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(536,5,149,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(537,5,150,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(538,5,151,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(539,5,162,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(540,5,161,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(541,5,163,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(542,5,165,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(543,5,164,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(544,5,169,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(545,5,168,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(546,5,170,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(547,5,171,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(548,5,172,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(549,5,173,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(550,5,175,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(551,5,177,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(552,5,176,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(553,5,179,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(554,5,178,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(555,5,180,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(556,5,182,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(557,5,181,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(558,5,184,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(559,5,183,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(560,5,230,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(561,5,231,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(562,5,232,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(563,5,234,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(564,5,235,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(565,5,236,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(566,5,237,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(567,5,238,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(568,5,239,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(569,5,240,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(570,5,241,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(571,5,186,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(572,5,187,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(573,5,188,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(574,5,190,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(575,5,191,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(576,5,192,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(577,5,194,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(578,5,195,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(579,5,196,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(580,5,198,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(581,5,199,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(582,5,200,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(583,5,13,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(584,5,14,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(585,5,15,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(586,5,202,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(587,5,203,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(588,5,204,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(589,5,206,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(590,5,207,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(591,5,208,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(592,5,210,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(593,5,211,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(594,5,212,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(595,5,213,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(596,5,214,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(597,5,215,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(598,5,216,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(599,5,217,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(600,5,218,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(601,5,219,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(602,5,220,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(603,5,221,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(604,5,12,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(605,5,222,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(606,5,223,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(607,5,224,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(608,5,225,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(609,5,27,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(610,5,28,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(611,5,55,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(612,5,56,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(613,5,57,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(614,5,29,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(615,5,92,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(616,6,16,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(617,6,36,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(618,6,37,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(619,6,69,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(620,6,70,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(621,6,62,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(622,6,152,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(623,6,161,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(624,6,173,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(625,6,175,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(626,6,176,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(627,6,178,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(628,6,180,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(629,6,181,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(630,6,183,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(631,6,234,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(632,6,235,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(633,6,236,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(634,6,91,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(635,6,93,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(636,6,98,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(637,6,112,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(638,6,186,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(639,6,190,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(640,6,194,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(641,6,198,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(642,6,13,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(643,6,15,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(644,6,202,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(645,6,206,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(646,6,210,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(647,6,212,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(648,6,213,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(649,6,215,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(650,6,216,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(651,6,219,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(652,6,220,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(653,6,221,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(654,6,12,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(655,6,222,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(656,6,223,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(657,6,226,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(658,6,227,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(659,6,27,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(660,6,55,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(661,8,16,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(662,8,36,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(663,8,37,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(664,8,69,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(665,8,70,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(666,8,62,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(667,8,152,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(668,8,161,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(669,8,173,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(670,8,175,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(671,8,176,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(672,8,178,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(673,8,180,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(674,8,181,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(675,8,183,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(676,8,234,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(677,8,235,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(678,8,236,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(679,8,91,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(680,8,93,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(681,8,98,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(682,8,112,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(683,8,186,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(684,8,190,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(685,8,194,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(686,8,198,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(687,8,13,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(688,8,15,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(689,8,202,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(690,8,206,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(691,8,210,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(692,8,212,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(693,8,213,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(694,8,215,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(695,8,216,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(696,8,219,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(697,8,220,'2026-09-28 00:09:05','2026-09-28 00:09:05'),(698,8,221,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(699,8,12,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(700,8,222,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(701,8,223,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(702,8,226,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(703,8,227,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(704,9,16,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(705,9,36,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(706,9,37,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(707,9,69,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(708,9,70,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(709,9,62,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(710,9,152,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(711,9,161,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(712,9,173,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(713,9,175,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(714,9,176,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(715,9,178,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(716,9,180,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(717,9,181,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(718,9,183,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(719,9,234,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(720,9,235,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(721,9,236,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(722,9,91,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(723,9,93,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(724,9,98,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(725,9,112,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(726,9,186,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(727,9,190,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(728,9,194,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(729,9,198,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(730,9,13,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(731,9,15,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(732,9,202,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(733,9,206,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(734,9,210,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(735,9,212,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(736,9,213,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(737,9,215,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(738,9,216,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(739,9,219,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(740,9,220,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(741,9,221,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(742,9,12,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(743,9,222,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(744,9,223,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(745,9,226,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(746,9,227,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(747,11,16,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(748,11,69,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(749,11,70,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(750,11,62,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(751,11,36,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(752,11,37,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(753,11,234,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(754,11,235,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(755,11,236,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(756,11,210,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(757,11,216,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(758,11,220,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(759,11,221,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(760,11,91,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(761,11,98,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(762,11,97,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(763,11,240,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(764,11,239,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(765,11,241,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(766,7,16,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(767,7,17,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(768,7,20,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(769,7,69,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(770,7,70,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(771,7,62,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(772,7,36,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(773,7,37,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(774,7,234,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(775,7,235,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(776,7,236,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(777,7,114,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(778,7,115,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(779,7,116,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(780,7,119,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(781,7,120,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(782,7,121,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(783,7,122,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(784,7,123,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(785,7,124,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(786,7,125,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(787,7,127,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(788,7,128,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(789,7,129,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(790,7,226,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(791,7,227,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(792,7,228,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(793,7,229,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(794,7,144,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(795,7,146,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(796,7,91,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(797,7,112,'2026-09-28 00:09:06','2026-09-28 00:09:06'),(798,7,241,'2026-09-28 00:09:06','2026-09-28 00:09:06');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `level` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`),
  KEY `roles_level_index` (`level`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'applicant',0,'2026-09-28 00:02:03','2026-09-28 00:02:03'),(2,'super_admin',500,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(3,'admin',400,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(4,'manager',300,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(5,'team_lead',260,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(6,'senior_counselor',240,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(7,'finance_officer',220,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(8,'counselor',200,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(9,'case_officer',160,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(10,'staff',150,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(11,'document_officer',100,'2026-09-28 00:09:03','2026-09-28 00:09:03'),(12,'viewer',50,'2026-09-28 00:09:03','2026-09-28 00:09:03');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salaries`
--

DROP TABLE IF EXISTS `salaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `payslip_number` varchar(255) DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `base_currency_id` bigint(20) unsigned DEFAULT NULL,
  `salary_month` varchar(7) NOT NULL,
  `amount_paid` decimal(20,8) NOT NULL,
  `worked_days` decimal(5,1) NOT NULL DEFAULT 30.0,
  `total_days` int(11) NOT NULL DEFAULT 30,
  `paid_leave_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `absent_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `unpaid_leave_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `bonus` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `total_amount` decimal(20,8) NOT NULL,
  `amount_due` decimal(20,8) DEFAULT NULL,
  `amount_paid_total` decimal(20,8) NOT NULL DEFAULT 0.00000000,
  `payment_status` varchar(20) NOT NULL DEFAULT 'open',
  `remarks` varchar(255) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `currency_code` varchar(10) DEFAULT NULL,
  `currency_name` varchar(255) DEFAULT NULL,
  `currency_symbol` varchar(20) DEFAULT NULL,
  `currency_decimal_places` tinyint(3) unsigned DEFAULT NULL,
  `currency_snapshot_source` varchar(40) NOT NULL DEFAULT 'recorded_at_creation',
  `currency_snapshot_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `cnv_curr_shrt` varchar(10) NOT NULL,
  `base_curr_shrt` varchar(10) NOT NULL,
  `rate` decimal(20,8) NOT NULL,
  `base_amount` decimal(20,4) NOT NULL,
  `pending_transaction_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salaries_employee_month_unique` (`employee_id`,`salary_month`),
  UNIQUE KEY `salaries_payslip_number_unique` (`payslip_number`),
  UNIQUE KEY `salaries_pending_transaction_id_unique` (`pending_transaction_id`),
  KEY `salaries_currency_id_foreign` (`currency_id`),
  KEY `salaries_base_currency_id_foreign` (`base_currency_id`),
  KEY `salaries_recorded_by_index` (`recorded_by`),
  CONSTRAINT `salaries_base_currency_id_foreign` FOREIGN KEY (`base_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salaries_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `salaries_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salaries_pending_transaction_id_foreign` FOREIGN KEY (`pending_transaction_id`) REFERENCES `pending_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salaries_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salaries`
--

LOCK TABLES `salaries` WRITE;
/*!40000 ALTER TABLE `salaries` DISABLE KEYS */;
INSERT INTO `salaries` VALUES (1,NULL,'PAY-2026-000001',1,1,NULL,'2026-06',60000.00000000,30.0,30,0.0,0.0,0.0,0.00000000,60000.00000000,NULL,0.00000000,'open','Partial salary payment','2026-06-30','AFN','Afghan Afghani','؋',2,'recorded_at_creation',NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL,'AFN','AFN',1.00000000,60000.0000,NULL),(2,NULL,'PAY-2026-000002',2,1,NULL,'2026-06',75000.00000000,28.0,30,0.0,0.0,0.0,0.00000000,70000.00000000,NULL,0.00000000,'open','2 days unpaid leave deduction applied','2026-06-30','AFN','Afghan Afghani','؋',2,'recorded_at_creation',NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL,'AFN','AFN',1.00000000,70000.0000,NULL);
/*!40000 ALTER TABLE `salaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_deductions`
--

DROP TABLE IF EXISTS `salary_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_deductions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `salary_id` bigint(20) unsigned NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(20,8) NOT NULL,
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_deductions_salary_id_foreign` (`salary_id`),
  CONSTRAINT `salary_deductions_salary_id_foreign` FOREIGN KEY (`salary_id`) REFERENCES `salaries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_deductions`
--

LOCK TABLES `salary_deductions` WRITE;
/*!40000 ALTER TABLE `salary_deductions` DISABLE KEYS */;
/*!40000 ALTER TABLE `salary_deductions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_payments`
--

DROP TABLE IF EXISTS `salary_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(255) DEFAULT NULL,
  `salary_id` bigint(20) unsigned NOT NULL,
  `expense_id` bigint(20) unsigned DEFAULT NULL,
  `pending_transaction_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(20,8) NOT NULL,
  `payment_date` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `salary_payments_payment_number_unique` (`payment_number`),
  UNIQUE KEY `salary_payments_expense_id_unique` (`expense_id`),
  UNIQUE KEY `salary_payments_pending_transaction_id_unique` (`pending_transaction_id`),
  KEY `salary_payments_recorded_by_foreign` (`recorded_by`),
  KEY `salary_payments_salary_id_payment_date_index` (`salary_id`,`payment_date`),
  CONSTRAINT `salary_payments_expense_id_foreign` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_payments_pending_transaction_id_foreign` FOREIGN KEY (`pending_transaction_id`) REFERENCES `pending_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_payments_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salary_payments_salary_id_foreign` FOREIGN KEY (`salary_id`) REFERENCES `salaries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_payments`
--

LOCK TABLES `salary_payments` WRITE;
/*!40000 ALTER TABLE `salary_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `salary_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_worthy_employee`
--

DROP TABLE IF EXISTS `salary_worthy_employee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_worthy_employee` (
  `salary_id` bigint(20) unsigned NOT NULL,
  `worthy_employee_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `source_amount` decimal(20,4) DEFAULT NULL,
  `source_currency_code` varchar(10) DEFAULT NULL,
  `source_rate` decimal(20,8) DEFAULT NULL,
  `salary_currency_code` varchar(10) DEFAULT NULL,
  `salary_rate` decimal(20,8) DEFAULT NULL,
  `converted_amount` decimal(20,4) DEFAULT NULL,
  `conversion_formula` varchar(255) DEFAULT NULL,
  `conversion_snapshot_source` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`salary_id`,`worthy_employee_id`),
  UNIQUE KEY `salary_worthy_employee_worthy_employee_id_unique` (`worthy_employee_id`),
  CONSTRAINT `salary_worthy_employee_salary_id_foreign` FOREIGN KEY (`salary_id`) REFERENCES `salaries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_worthy_employee_worthy_employee_id_foreign` FOREIGN KEY (`worthy_employee_id`) REFERENCES `worthy_employees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_worthy_employee`
--

LOCK TABLES `salary_worthy_employee` WRITE;
/*!40000 ALTER TABLE `salary_worthy_employee` DISABLE KEYS */;
/*!40000 ALTER TABLE `salary_worthy_employee` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scholarships`
--

DROP TABLE IF EXISTS `scholarships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `scholarships` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `education_level_id` bigint(20) unsigned DEFAULT NULL,
  `language_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `package_type_id` bigint(20) unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `fee` decimal(20,6) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `scholarships_applicant_id_foreign` (`applicant_id`),
  KEY `scholarships_currency_id_foreign` (`currency_id`),
  KEY `scholarships_package_type_id_foreign` (`package_type_id`),
  KEY `scholarships_employee_id_status_date_index` (`employee_id`,`status`,`date`),
  KEY `scholarships_education_level_id_foreign` (`education_level_id`),
  KEY `scholarships_language_id_foreign` (`language_id`),
  KEY `scholarships_country_id_education_level_id_date_index` (`country_id`,`education_level_id`,`date`),
  CONSTRAINT `scholarships_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `scholarships_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `scholarships_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `scholarships_education_level_id_foreign` FOREIGN KEY (`education_level_id`) REFERENCES `education_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `scholarships_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `scholarships_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `scholarships_package_type_id_foreign` FOREIGN KEY (`package_type_id`) REFERENCES `package_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scholarships`
--

LOCK TABLES `scholarships` WRITE;
/*!40000 ALTER TABLE `scholarships` DISABLE KEYS */;
/*!40000 ALTER TABLE `scholarships` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `semesters`
--

DROP TABLE IF EXISTS `semesters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `semesters` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(30) NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `semesters_name_unique` (`name`),
  UNIQUE KEY `semesters_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `semesters`
--

LOCK TABLES `semesters` WRITE;
/*!40000 ALTER TABLE `semesters` DISABLE KEYS */;
INSERT INTO `semesters` VALUES (1,'Fall 2027','FALL-2027',10,1,'2026-09-28 00:09:14','2026-09-28 00:09:14',NULL);
/*!40000 ALTER TABLE `semesters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL,
  `category` varchar(40) NOT NULL DEFAULT 'general',
  `description` text DEFAULT NULL,
  `fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `services_currency_id_foreign` (`currency_id`),
  KEY `services_category_index` (`category`),
  CONSTRAINT `services_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,'Initial Education Consultation','general',NULL,1500.00,'active',1,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(2,'Document Review','general',NULL,5000.00,'active',1,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(3,'University Application Support','general',NULL,15000.00,'active',1,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(4,'Scholarship Application Support','scholarship',NULL,12000.00,'active',1,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(5,'English Language Test Offer','test_offer',NULL,5000.00,'active',1,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(6,'Student Visa Application Support','general',NULL,20000.00,'active',1,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(7,'Statement of Purpose and CV Review','general',NULL,8000.00,'active',1,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(8,'Visa Interview Preparation','general',NULL,5000.00,'active',1,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(9,'Translation Coordination','general',NULL,3000.00,'active',1,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL);
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `navigation_clr` varchar(255) DEFAULT NULL,
  `cards_clr` varchar(255) DEFAULT NULL,
  `footer_clr` varchar(255) DEFAULT NULL,
  `paid_leave_days_per_year` smallint(5) unsigned NOT NULL DEFAULT 0,
  `website_title` varchar(255) NOT NULL,
  `website_logo` varchar(255) DEFAULT NULL,
  `company_signature` varchar(255) DEFAULT NULL,
  `contract_letterhead` varchar(255) DEFAULT NULL,
  `contract_cover_design` varchar(255) DEFAULT NULL,
  `company_email` varchar(255) DEFAULT NULL,
  `mail_host` varchar(255) DEFAULT NULL,
  `mail_port` smallint(5) unsigned DEFAULT NULL,
  `mail_encryption` varchar(10) DEFAULT NULL,
  `mail_username` varchar(255) DEFAULT NULL,
  `mail_password` text DEFAULT NULL,
  `mail_from_name` varchar(255) DEFAULT NULL,
  `mail_reply_to` varchar(255) DEFAULT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Kabul',
  `website_address` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `physical_address` text NOT NULL,
  `short_company_name` varchar(255) NOT NULL,
  `original_company_name` varchar(255) NOT NULL,
  `business_licence` varchar(255) DEFAULT NULL,
  `office_open_time` time DEFAULT NULL,
  `office_close_time` time DEFAULT NULL,
  `default_break_minutes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `automatic_attendance_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `primary_color` varchar(255) DEFAULT NULL,
  `secondary_color` varchar(255) DEFAULT NULL,
  `success_color` varchar(255) NOT NULL DEFAULT '#15803D',
  `info_color` varchar(255) NOT NULL DEFAULT '#0284C7',
  `warning_color` varchar(255) NOT NULL DEFAULT '#D97706',
  `error_color` varchar(255) NOT NULL DEFAULT '#DC2626',
  `light_background_color` varchar(255) NOT NULL DEFAULT '#F5F7F4',
  `light_surface_color` varchar(255) NOT NULL DEFAULT '#FFFEFA',
  `dark_background_color` varchar(255) NOT NULL DEFAULT '#102321',
  `dark_surface_color` varchar(255) NOT NULL DEFAULT '#173330',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `contract_letterhead_safe_x` decimal(5,2) NOT NULL DEFAULT 8.50,
  `contract_letterhead_safe_y` decimal(5,2) NOT NULL DEFAULT 15.00,
  `contract_letterhead_safe_width` decimal(5,2) NOT NULL DEFAULT 83.00,
  `contract_letterhead_safe_height` decimal(5,2) NOT NULL DEFAULT 70.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subtasks`
--

DROP TABLE IF EXISTS `subtasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subtasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(255) NOT NULL DEFAULT 'General',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `is_completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subtasks_task_id_foreign` (`task_id`),
  KEY `subtasks_user_id_foreign` (`user_id`),
  CONSTRAINT `subtasks_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subtasks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subtasks`
--

LOCK TABLES `subtasks` WRITE;
/*!40000 ALTER TABLE `subtasks` DISABLE KEYS */;
INSERT INTO `subtasks` VALUES (1,1,3,'Sint necessitatibus fugiat.','General','urgent',1,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(2,1,3,'Est sed cupiditate numquam.','General','urgent',1,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(3,1,3,'Sit et aut.','General','urgent',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(4,3,1,'Quo veniam sit necessitatibus.','General','medium',1,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(5,3,1,'In voluptate eius nisi commodi.','General','medium',1,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(6,3,1,'Impedit sed mollitia.','General','medium',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(7,4,4,'Quia ut id dolor.','General','medium',1,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(8,5,2,'Possimus alias ullam inventore.','General','low',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(9,9,4,'Eum soluta.','General','low',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(10,9,4,'Amet autem veniam.','General','low',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(11,9,4,'Veritatis sapiente.','General','low',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(12,11,4,'Ex magnam consequatur id.','General','urgent',1,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(13,11,4,'Tempora dolorem voluptatibus eos libero.','General','urgent',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(14,11,4,'Ea sed dignissimos.','General','urgent',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(15,11,4,'Voluptatum saepe impedit.','General','urgent',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(16,14,1,'Magnam ea rerum.','General','urgent',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(17,14,1,'Officiis natus quaerat.','General','urgent',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(18,14,1,'Accusamus voluptatum quidem omnis.','General','urgent',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(19,15,4,'Tempora ipsam tenetur.','General','urgent',1,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(20,15,4,'Doloremque hic at architecto.','General','urgent',1,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(21,17,2,'Iure neque tempore quas.','General','low',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(22,17,2,'Maiores aperiam optio.','General','low',1,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(23,17,2,'Molestiae ipsam ab.','General','low',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(24,18,3,'Quas provident dolore.','General','medium',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(25,18,3,'Eum soluta quo eveniet.','General','medium',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(26,23,1,'Est qui assumenda.','General','medium',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(27,23,1,'Dolorum sed ut est.','General','medium',0,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(28,23,1,'Assumenda quo sed ut.','General','medium',1,'2026-09-28 00:09:11','2026-09-28 00:09:11',NULL),(29,27,5,'Deleniti mollitia assumenda.','Planning','high',0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(30,27,5,'Minus ut eius sed.','Execution','urgent',0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(31,28,5,'Animi voluptatem voluptatem rerum.','General','urgent',0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(32,28,5,'Doloribus dolorem.','Execution','medium',1,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(33,31,6,'Voluptatem blanditiis nemo quod.','Execution','high',1,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(34,32,8,'Nesciunt molestias ut.','Execution','urgent',0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(35,34,9,'Sit ut nihil sit.','Planning','low',0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(36,36,9,'Voluptatem architecto voluptatum temporibus.','Execution','urgent',1,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(37,36,9,'Iusto cumque veritatis.','Execution','medium',0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(38,38,8,'Voluptatem vel nesciunt.','Execution','urgent',0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL);
/*!40000 ALTER TABLE `subtasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_ticket_attachments`
--

DROP TABLE IF EXISTS `support_ticket_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_ticket_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint(20) unsigned NOT NULL,
  `disk` varchar(40) NOT NULL DEFAULT 'local',
  `path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `size` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_ticket_attachments_support_ticket_id_index` (`support_ticket_id`),
  CONSTRAINT `support_ticket_attachments_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_ticket_attachments`
--

LOCK TABLES `support_ticket_attachments` WRITE;
/*!40000 ALTER TABLE `support_ticket_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_ticket_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support_tickets`
--

DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `ticket_number` varchar(255) NOT NULL,
  `category` varchar(40) NOT NULL,
  `severity` varchar(20) NOT NULL DEFAULT 'normal',
  `subject` varchar(160) NOT NULL,
  `description` text NOT NULL,
  `page_url` varchar(2048) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `recipient_email` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_tickets_ticket_number_unique` (`ticket_number`),
  KEY `support_tickets_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `support_tickets_status_severity_index` (`status`,`severity`),
  CONSTRAINT `support_tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support_tickets`
--

LOCK TABLES `support_tickets` WRITE;
/*!40000 ALTER TABLE `support_tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `support_tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `color` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
INSERT INTO `tags` VALUES (1,'Bug','error','2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(2,'Feature','primary','2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(3,'Enhancement','info','2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(4,'Documentation','warning','2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(5,'Research','secondary','2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(6,'Testing','success','2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(7,'UI/UX','primary','2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(8,'Backend','error','2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(9,'Kanban Tag 1','warning','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(10,'Kanban Tag 2','error','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(11,'Kanban Tag 3','warning','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(12,'Kanban Tag 4','primary','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(13,'Kanban Tag 5','success','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(14,'Kanban Tag 6','primary','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(15,'Kanban Tag 7','info','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(16,'Kanban Tag 8','primary','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL);
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_attachments`
--

DROP TABLE IF EXISTS `task_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `section` enum('input','results') NOT NULL DEFAULT 'results',
  `disk` varchar(32) NOT NULL DEFAULT 'public',
  `path` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `size` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_attachments_task_id_foreign` (`task_id`),
  KEY `task_attachments_user_id_foreign` (`user_id`),
  CONSTRAINT `task_attachments_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_attachments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_attachments`
--

LOCK TABLES `task_attachments` WRITE;
/*!40000 ALTER TABLE `task_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_dependencies`
--

DROP TABLE IF EXISTS `task_dependencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_dependencies` (
  `task_id` bigint(20) unsigned NOT NULL,
  `depends_on_task_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`task_id`,`depends_on_task_id`),
  KEY `task_dependencies_depends_on_task_id_foreign` (`depends_on_task_id`),
  CONSTRAINT `task_dependencies_depends_on_task_id_foreign` FOREIGN KEY (`depends_on_task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_dependencies_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_dependencies`
--

LOCK TABLES `task_dependencies` WRITE;
/*!40000 ALTER TABLE `task_dependencies` DISABLE KEYS */;
INSERT INTO `task_dependencies` VALUES (2,7),(4,17),(7,16),(7,22),(8,7),(8,20),(9,19),(10,12),(10,18),(12,13),(13,7),(13,21),(16,20),(18,12),(18,23),(19,12),(19,20),(21,16),(22,19),(22,23);
/*!40000 ALTER TABLE `task_dependencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_service`
--

DROP TABLE IF EXISTS `task_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_service` (
  `task_id` bigint(20) unsigned NOT NULL,
  `service_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`task_id`,`service_id`),
  KEY `task_service_service_id_foreign` (`service_id`),
  CONSTRAINT `task_service_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `task_service_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_service`
--

LOCK TABLES `task_service` WRITE;
/*!40000 ALTER TABLE `task_service` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_tag`
--

DROP TABLE IF EXISTS `task_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_tag` (
  `task_id` bigint(20) unsigned NOT NULL,
  `tag_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`task_id`,`tag_id`),
  KEY `task_tag_tag_id_foreign` (`tag_id`),
  CONSTRAINT `task_tag_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_tag_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_tag`
--

LOCK TABLES `task_tag` WRITE;
/*!40000 ALTER TABLE `task_tag` DISABLE KEYS */;
INSERT INTO `task_tag` VALUES (1,4),(1,8),(2,8),(3,5),(3,6),(3,7),(4,3),(4,8),(5,1),(5,3),(5,4),(6,5),(6,8),(7,1),(8,7),(8,8),(9,5),(9,6),(9,8),(10,1),(10,6),(11,6),(11,7),(11,8),(14,1),(14,3),(14,6),(15,8),(16,3),(17,3),(17,4),(17,5),(19,7),(20,8),(21,6),(22,2),(22,4),(22,6),(23,3),(23,6),(24,9),(24,11),(25,12),(26,12),(26,13),(26,16),(27,13),(28,9),(28,11),(28,12),(29,10),(29,11),(29,16),(30,11),(30,16),(31,9),(31,15),(32,11),(32,12),(33,10),(33,11),(33,15),(34,9),(34,13),(35,10),(35,12),(35,16),(36,9),(37,11),(37,12),(37,13),(38,13),(39,14),(40,13),(40,14);
/*!40000 ALTER TABLE `task_tag` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_user`
--

DROP TABLE IF EXISTS `task_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_user` (
  `task_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `contribution_percent` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`task_id`,`user_id`),
  KEY `task_user_user_id_foreign` (`user_id`),
  CONSTRAINT `task_user_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_user`
--

LOCK TABLES `task_user` WRITE;
/*!40000 ALTER TABLE `task_user` DISABLE KEYS */;
INSERT INTO `task_user` VALUES (1,1,NULL),(1,2,NULL),(1,3,NULL),(2,3,NULL),(3,1,NULL),(3,3,NULL),(4,1,NULL),(4,4,NULL),(5,1,NULL),(5,2,NULL),(5,4,NULL),(6,3,NULL),(7,3,NULL),(7,4,NULL),(8,2,NULL),(8,4,NULL),(9,4,NULL),(10,1,NULL),(10,2,NULL),(10,4,NULL),(11,3,NULL),(11,4,NULL),(12,3,NULL),(13,2,NULL),(14,1,NULL),(14,2,NULL),(14,3,NULL),(15,1,NULL),(15,3,NULL),(15,4,NULL),(16,1,NULL),(16,2,NULL),(16,3,NULL),(17,2,NULL),(18,3,NULL),(19,1,NULL),(19,4,NULL),(20,1,NULL),(20,3,NULL),(20,4,NULL),(21,2,NULL),(21,4,NULL),(22,2,NULL),(22,4,NULL),(23,1,NULL),(23,3,NULL),(23,4,NULL),(24,5,NULL),(24,7,NULL),(24,8,NULL),(25,7,NULL),(25,8,NULL),(26,7,NULL),(26,8,NULL),(26,9,NULL),(27,5,NULL),(27,9,NULL),(28,5,NULL),(28,6,NULL),(28,8,NULL),(29,6,NULL),(29,8,NULL),(30,5,NULL),(30,8,NULL),(30,9,NULL),(31,6,NULL),(32,6,NULL),(32,8,NULL),(33,6,NULL),(33,7,NULL),(34,5,NULL),(34,6,NULL),(34,9,NULL),(35,6,NULL),(36,6,NULL),(36,9,NULL),(37,6,NULL),(38,5,NULL),(38,6,NULL),(38,8,NULL),(39,5,NULL),(39,7,NULL),(39,8,NULL),(40,5,NULL),(40,9,NULL);
/*!40000 ALTER TABLE `task_user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `board_id` bigint(20) unsigned NOT NULL,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `results` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `progress` int(11) NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reopened_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `rejected_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  `manager_quality_score` decimal(3,2) DEFAULT NULL,
  `quality_reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `quality_reviewed_at` timestamp NULL DEFAULT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_board_id_foreign` (`board_id`),
  KEY `tasks_quality_reviewed_by_foreign` (`quality_reviewed_by`),
  KEY `tasks_completed_at_progress_index` (`completed_at`,`progress`),
  KEY `tasks_applicant_id_index` (`applicant_id`),
  CONSTRAINT `tasks_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_board_id_foreign` FOREIGN KEY (`board_id`) REFERENCES `boards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_quality_reviewed_by_foreign` FOREIGN KEY (`quality_reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
INSERT INTO `tasks` VALUES (1,1,NULL,'Eius tempore eum numquam ut blanditiis.','Maiores sapiente beatae rerum minus consectetur nulla. Illum voluptatibus assumenda doloribus soluta sit recusandae quam. Et fuga distinctio nulla fugiat.',NULL,'urgent',34,'2026-09-24','2026-09-28',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(2,1,NULL,'Et nostrum soluta praesentium fuga eum.','Eveniet voluptatem repellendus facere nostrum non tempora. Sit mollitia enim nihil delectus.',NULL,'urgent',8,'2026-09-26','2026-09-30',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(3,1,NULL,'Nam nam repellat natus.','Atque eos et qui fugiat sunt. Fugiat praesentium et ea dolor laboriosam nostrum voluptatibus. Cupiditate quia autem qui. Sit possimus id facere veniam atque sit.',NULL,'medium',100,'2026-09-23','2026-10-07','2026-09-28 00:09:10',0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(4,1,NULL,'Quam et voluptas inventore ullam.','Labore et consequatur ut dolores blanditiis. Illum voluptates rerum placeat modi rerum quia. Eaque itaque ut odio placeat. Ut molestiae dolor aperiam sed quo illo fugiat.',NULL,'medium',8,'2026-09-28','2026-10-01',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(5,1,NULL,'Iusto iusto dolorum velit.','Facilis ratione quaerat quos vel rerum dolore rerum. Perferendis nulla provident suscipit excepturi dolorem. Modi dolorem omnis rem id quis. Sint voluptatem ipsum est natus vero et nihil in. Esse ex qui et excepturi.',NULL,'low',36,'2026-09-24','2026-10-10',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(6,1,NULL,'Rerum veritatis enim dignissimos autem.','Quasi dicta fugiat velit iste error dolores accusantium. Sint incidunt eaque similique accusamus. Porro animi ut repudiandae veniam quia omnis quaerat. Fugit qui molestiae nobis consequuntur ex.',NULL,'urgent',53,'2026-09-25','2026-10-07',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(7,1,NULL,'Iure nemo aliquam soluta aut.','Eius est numquam eius est veritatis facilis enim. Error et vel quis sit enim. Excepturi illum eius fuga ducimus aperiam.',NULL,'low',43,'2026-09-22','2026-09-28',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(8,1,NULL,'Dicta dolorum deserunt hic.','Eveniet numquam inventore quia repudiandae omnis. Harum excepturi dignissimos incidunt non est eum. Tempore expedita est recusandae eos tempore.',NULL,'urgent',0,'2026-09-24','2026-10-03',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(9,2,NULL,'Quae dolores ipsa placeat fuga.','Voluptatem esse ex voluptas eum corrupti et quam. Explicabo numquam vel distinctio. Reiciendis qui et officia voluptatem eveniet. Tempore ipsum voluptatem perferendis quae dolor ea earum nihil.',NULL,'low',81,'2026-09-25','2026-10-08',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(10,2,NULL,'Quod architecto et odit.','Porro similique id dolor expedita harum accusamus et eum. Tenetur consequatur nobis quia iure ipsam a. Aliquid laborum quos non aliquid corrupti. Est qui soluta minima aut et aliquam est.',NULL,'high',97,'2026-09-24','2026-10-10',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(11,2,NULL,'Deserunt iusto rem maxime.','Consequuntur labore qui a rerum soluta beatae. Ut vitae deserunt id quaerat ad ratione. Non aliquam delectus velit eos.',NULL,'urgent',15,'2026-09-25','2026-10-06',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(12,3,NULL,'Et odit quaerat consectetur optio.','Quisquam ut reprehenderit deserunt aliquam sapiente. Qui aspernatur iste nihil facere. Tempora error nostrum saepe voluptatem consequatur nisi.',NULL,'urgent',58,'2026-09-25','2026-09-29',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(13,3,NULL,'Deserunt necessitatibus molestiae ut.','Ex sunt cum ut doloribus consequatur qui. Sit natus pariatur magnam voluptas ducimus id. Explicabo sunt odit eligendi.',NULL,'urgent',20,'2026-09-28','2026-10-03',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(14,3,NULL,'Cupiditate recusandae aut placeat.','Pariatur ab fuga maxime voluptatem ratione sed ad. Aut est aliquid voluptas inventore assumenda quia id repellendus. Laudantium laborum quia ea non cupiditate pariatur. Ducimus nisi aut dignissimos quas eveniet deserunt dolorem. Ut sit illum sapiente nulla ipsum qui vel ullam.',NULL,'urgent',22,'2026-09-23','2026-09-29',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(15,4,NULL,'Dignissimos omnis molestiae esse assumenda.','Incidunt vel et amet et sed. Nam quia rerum accusantium enim. Et omnis unde quisquam asperiores magni. Itaque earum quae quae labore rerum iusto eaque.',NULL,'urgent',86,'2026-09-25','2026-09-29',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(16,4,NULL,'Autem sequi accusantium ut voluptates.','Magnam ea sequi possimus ut qui. Vel non expedita eum sapiente odit. Sit harum optio excepturi ea rem sit quia.',NULL,'low',17,'2026-09-28','2026-10-03',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(17,4,NULL,'Incidunt provident molestiae et dolores.','Aut dolorum itaque sequi eligendi. Placeat vero facere nostrum culpa possimus ipsa quasi non. Odio qui accusamus at.',NULL,'low',70,'2026-09-24','2026-10-04',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(18,4,NULL,'Non enim ut blanditiis.','Et et quia cumque velit labore fuga. Accusantium dolorem sit est quos officiis voluptatem esse.',NULL,'medium',68,'2026-09-23','2026-10-06',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(19,4,NULL,'Amet sunt quas.','Consequuntur dolorum repellat consequatur. Et natus optio quo. Dicta excepturi id atque vel eum sit.',NULL,'high',89,'2026-09-21','2026-10-03',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(20,5,NULL,'Eius dignissimos accusantium ut laboriosam et.','Et enim facilis aliquid dicta. Adipisci ipsum aut dicta neque magni illum. Est error vero amet.',NULL,'low',96,'2026-09-27','2026-09-30',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(21,5,NULL,'Quis temporibus et odio.','Quia debitis est illo qui ipsam adipisci molestiae et. Voluptas dignissimos laborum quisquam tenetur ut dolorem. Eos eligendi mollitia voluptates quae dolorem. Dolores enim molestiae voluptatem earum provident perferendis.',NULL,'medium',60,'2026-09-21','2026-10-03',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(22,5,NULL,'Fugiat voluptatem consequuntur.','Id voluptatem molestias facere. Ut quod nemo odit provident. Consequatur quam at aliquam cumque assumenda ipsa officiis. Vitae consequatur mollitia molestiae ut.',NULL,'high',44,'2026-09-22','2026-10-10',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(23,5,NULL,'Omnis fuga et natus ex.','Qui reiciendis quia aliquam a asperiores. Hic nostrum quibusdam deleniti eum ad doloribus harum reprehenderit. Et eos numquam tenetur nemo dicta. Aut rerum repellat nobis necessitatibus porro eos autem.',NULL,'medium',29,'2026-09-25','2026-10-08',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:10','2026-09-28 00:09:10',NULL),(24,6,NULL,'Error voluptas in voluptate.','Corrupti dolores laborum voluptatibus labore dolorem molestias similique. Sapiente minus ipsa corporis facilis. Placeat ipsam maiores sed ut tenetur.',NULL,'urgent',19,'2026-09-23','2026-10-04',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(25,6,NULL,'Cupiditate doloremque velit voluptatem dolores.','Eligendi qui dolorem voluptatibus laudantium. Non nam voluptas voluptates recusandae. Id maxime sit aut sit autem.',NULL,'high',10,'2026-09-22','2026-10-06',NULL,0,0,NULL,NULL,NULL,1,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(26,6,NULL,'Est doloremque ullam quibusdam eum.','Illum laboriosam et nulla veniam. Voluptatibus assumenda enim eius omnis dignissimos dicta ipsum. Sit minus voluptatem dicta eum ipsa autem.',NULL,'low',42,'2026-09-26','2026-10-02',NULL,0,0,NULL,NULL,NULL,2,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(27,6,NULL,'Sapiente est reprehenderit nihil.','Dolor non aut sed similique repudiandae voluptate. Omnis doloribus exercitationem recusandae voluptas tempora atque. Voluptatibus architecto impedit in debitis sint dolor. Tempora qui blanditiis qui atque quas. Ut quia doloribus cumque dolores veniam.',NULL,'urgent',94,'2026-09-27','2026-10-01',NULL,0,0,NULL,NULL,NULL,3,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(28,7,NULL,'Quidem non est.','Iusto sapiente molestias et officia. Voluptate quia et est numquam quia nisi inventore. Eius ea id aut vitae nam vitae. Exercitationem inventore voluptatem itaque saepe beatae quo.',NULL,'medium',75,'2026-09-25','2026-10-08',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(29,7,NULL,'Eaque adipisci consequuntur qui nesciunt.','Ducimus animi repellat sit eius exercitationem tempore aut. Esse et voluptatem reprehenderit minima. Rem architecto ipsa quam non. Vel non ut expedita et expedita. Earum saepe molestias occaecati.',NULL,'medium',58,'2026-09-24','2026-10-08',NULL,0,0,NULL,NULL,NULL,1,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(30,7,NULL,'Incidunt tenetur aut.','Sint porro perferendis blanditiis molestiae corporis consequatur. Expedita enim earum modi ut voluptatem eius eligendi porro. Dolorem natus animi sequi blanditiis excepturi tempore illum.',NULL,'urgent',72,'2026-09-26','2026-10-09',NULL,0,0,NULL,NULL,NULL,2,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(31,7,NULL,'Voluptatem non ducimus impedit aut repellendus.','Expedita quis provident aliquam et qui minus laboriosam. Quos inventore ea eligendi aspernatur voluptas sit adipisci. Voluptate id quia magni nihil dolorem esse. Nihil vel ea blanditiis distinctio incidunt.',NULL,'urgent',49,'2026-09-21','2026-10-12',NULL,0,0,NULL,NULL,NULL,3,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(32,7,NULL,'Illo accusantium possimus repellat possimus.','Enim et eveniet dolor eum dolorem. Voluptas labore aut quo vitae excepturi velit ut. Occaecati placeat nihil accusantium est nisi velit expedita. Dignissimos aut sint et ea non.',NULL,'medium',82,'2026-09-24','2026-10-06',NULL,0,0,NULL,NULL,NULL,4,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(33,8,NULL,'Cupiditate sit voluptatum ratione est.','Ipsam alias voluptas quibusdam non modi saepe. Quibusdam et et et laudantium. Fugit accusantium eum tempore amet.',NULL,'high',49,'2026-09-24','2026-10-03',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(34,8,NULL,'Velit error ut doloribus.','Sapiente molestiae quasi placeat omnis facere ea. Hic sit voluptates deleniti vel magnam dolorum nobis. Ut ex facilis blanditiis sed autem. Assumenda vitae vero sed et.',NULL,'low',71,'2026-09-23','2026-10-11',NULL,0,0,NULL,NULL,NULL,1,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(35,8,NULL,'Neque doloribus ut.','Rerum et optio iusto sunt omnis quasi praesentium. Repellendus inventore dicta eaque eveniet. Unde laudantium aliquid incidunt corrupti aut. Et at sapiente explicabo omnis et quidem numquam. Quis est ad minima dolorem.',NULL,'medium',37,'2026-09-23','2026-10-08',NULL,0,0,NULL,NULL,NULL,2,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(36,9,NULL,'Dolores qui dolor omnis.','Consequuntur et sed maxime vero natus sint et. Distinctio rem veniam qui possimus ex omnis. Rerum ea voluptatibus dignissimos ex dolorem velit porro saepe.',NULL,'high',67,'2026-09-26','2026-10-04',NULL,0,0,NULL,NULL,NULL,0,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(37,9,NULL,'Asperiores earum adipisci.','Mollitia sed natus quis quia dolores. Libero harum explicabo aliquid consequatur hic.',NULL,'high',73,'2026-09-22','2026-09-28',NULL,0,0,NULL,NULL,NULL,1,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(38,9,NULL,'Natus expedita facilis est quis.','Ut mollitia rerum sit quisquam voluptas. Fugiat earum dolores minima hic labore. Inventore blanditiis est nesciunt sit ipsum voluptate libero qui.',NULL,'low',41,'2026-09-28','2026-10-07',NULL,0,0,NULL,NULL,NULL,2,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(39,9,NULL,'Minima commodi quo facilis qui ea.','Iure accusantium voluptatibus voluptas. Quia sed fugit illum voluptas voluptas ut atque molestias. Eius architecto molestias omnis et asperiores maiores dolorem.',NULL,'low',18,'2026-09-26','2026-10-05',NULL,0,0,NULL,NULL,NULL,3,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(40,9,NULL,'Alias ut ullam pariatur ea.','Esse nesciunt atque quibusdam at quo qui. Consectetur optio repellat quam sint quis modi. Nobis vel sint aliquid non omnis similique.',NULL,'medium',42,'2026-09-26','2026-10-02',NULL,0,0,NULL,NULL,NULL,4,'2026-09-28 00:09:12','2026-09-28 00:09:12',NULL);
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `test_offers`
--

DROP TABLE IF EXISTS `test_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `test_offers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `education_level_id` bigint(20) unsigned DEFAULT NULL,
  `language_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` bigint(20) unsigned NOT NULL,
  `currency_id` bigint(20) unsigned NOT NULL,
  `package_type_id` bigint(20) unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `fee` decimal(20,6) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `test_offers_applicant_id_foreign` (`applicant_id`),
  KEY `test_offers_currency_id_foreign` (`currency_id`),
  KEY `test_offers_package_type_id_foreign` (`package_type_id`),
  KEY `test_offers_employee_id_status_date_index` (`employee_id`,`status`,`date`),
  KEY `test_offers_education_level_id_foreign` (`education_level_id`),
  KEY `test_offers_language_id_foreign` (`language_id`),
  KEY `test_offers_country_id_education_level_id_date_index` (`country_id`,`education_level_id`,`date`),
  CONSTRAINT `test_offers_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `test_offers_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `test_offers_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `test_offers_education_level_id_foreign` FOREIGN KEY (`education_level_id`) REFERENCES `education_levels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `test_offers_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `test_offers_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `test_offers_package_type_id_foreign` FOREIGN KEY (`package_type_id`) REFERENCES `package_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `test_offers`
--

LOCK TABLES `test_offers` WRITE;
/*!40000 ALTER TABLE `test_offers` DISABLE KEYS */;
/*!40000 ALTER TABLE `test_offers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `universities`
--

DROP TABLE IF EXISTS `universities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `universities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `country_id` bigint(20) unsigned DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `universities_name_country_id_unique` (`name`,`country_id`),
  KEY `universities_country_id_foreign` (`country_id`),
  CONSTRAINT `universities_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `universities`
--

LOCK TABLES `universities` WRITE;
/*!40000 ALTER TABLE `universities` DISABLE KEYS */;
INSERT INTO `universities` VALUES (1,'University of Toronto',1,'Toronto',NULL,NULL,1,'2026-09-28 00:09:14','2026-09-28 00:09:14',NULL),(2,'Technical University of Munich',2,'Munich',NULL,NULL,1,'2026-09-28 00:09:14','2026-09-28 00:09:14',NULL),(3,'University of Manchester',3,'Manchester',NULL,NULL,1,'2026-09-28 00:09:14','2026-09-28 00:09:14',NULL);
/*!40000 ALTER TABLE `universities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_roles_user_id_role_id_unique` (`user_id`,`role_id`),
  KEY `user_roles_role_id_foreign` (`role_id`),
  KEY `user_roles_user_id_index` (`user_id`),
  CONSTRAINT `user_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_roles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_roles`
--

LOCK TABLES `user_roles` WRITE;
/*!40000 ALTER TABLE `user_roles` DISABLE KEYS */;
INSERT INTO `user_roles` VALUES (1,1,2,'2026-09-28 00:09:07','2026-09-28 00:09:07');
/*!40000 ALTER TABLE `user_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `acc_status` varchar(255) NOT NULL DEFAULT 'active',
  `account_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`account_settings`)),
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `must_set_password` tinyint(1) NOT NULL DEFAULT 0,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Demo Super Admin','admin@example.test','0700000000','Demo Office, Kabul','active',NULL,NULL,NULL,'2026-09-28 00:09:07',0,'$2y$12$sBF/cWKajcPVlRlAZecmpe7KID3LKioRbkjqfrMIr567ekJMpCSN.',NULL,'2026-09-28 00:09:07','2026-09-28 00:09:07',NULL),(2,'John Doe','john.doe@example.com','555-1234','123 Main St, Cityville','active',NULL,NULL,NULL,NULL,0,'$2y$12$uVN.g/D03hdmkcnlpvfb2uBta5MJm1kEIidMwhjDV4x/N3fHQ5.bm',NULL,'2026-09-28 00:09:07','2026-09-28 00:09:07',NULL),(3,'Jane Smith','jane.smith@example.com','555-5678','456 Oak Ave, Townsville','active',NULL,NULL,NULL,NULL,0,'$2y$12$DMnnlMu3.fiMd.9kpgBQ6.aaucsKjxCx6lX2gKkCn4TI2DZbfSF/e',NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(4,'David Johnson','david.johnson@example.com','555-9012','789 Pine Rd, Villagetown','active',NULL,NULL,NULL,NULL,0,'$2y$12$yY/5yRxFMg.bWOrlhSURIu5M69VyghEZ3ysG/Bcx6ISKodJfV.UyK',NULL,'2026-09-28 00:09:08','2026-09-28 00:09:08',NULL),(5,'Ilene Pollich','crooks.amiya@example.org',NULL,NULL,'active',NULL,NULL,NULL,'2026-09-28 00:09:11',0,'$2y$12$.MwysW9fyaWRC6r3zdXe.OBXloIiT81ryKbmt99k.IgCTr4HcHiIa','U4kWpgMSKX','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(6,'Christ Klein','trinity.schuster@example.com',NULL,NULL,'active',NULL,NULL,NULL,'2026-09-28 00:09:12',0,'$2y$12$.MwysW9fyaWRC6r3zdXe.OBXloIiT81ryKbmt99k.IgCTr4HcHiIa','uRjWuw0T0E','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(7,'Holly Smitham','pouros.bennett@example.org',NULL,NULL,'active',NULL,NULL,NULL,'2026-09-28 00:09:12',0,'$2y$12$.MwysW9fyaWRC6r3zdXe.OBXloIiT81ryKbmt99k.IgCTr4HcHiIa','5bQy2fVhYi','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(8,'Elfrieda Maggio IV','rleuschke@example.com',NULL,NULL,'active',NULL,NULL,NULL,'2026-09-28 00:09:12',0,'$2y$12$.MwysW9fyaWRC6r3zdXe.OBXloIiT81ryKbmt99k.IgCTr4HcHiIa','sS0g1LNDni','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL),(9,'Horace Jakubowski','camilla.goldner@example.com',NULL,NULL,'active',NULL,NULL,NULL,'2026-09-28 00:09:12',0,'$2y$12$.MwysW9fyaWRC6r3zdXe.OBXloIiT81ryKbmt99k.IgCTr4HcHiIa','ZCKIRCTG55','2026-09-28 00:09:12','2026-09-28 00:09:12',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `visitors`
--

DROP TABLE IF EXISTS `visitors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `visitors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `company` varchar(255) NOT NULL,
  `contact` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `service` varchar(255) NOT NULL,
  `marketer` varchar(255) NOT NULL,
  `marketer_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `visitors_marketer_id_idx` (`marketer_id`),
  CONSTRAINT `visitors_marketer_id_foreign` FOREIGN KEY (`marketer_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visitors`
--

LOCK TABLES `visitors` WRITE;
/*!40000 ALTER TABLE `visitors` DISABLE KEYS */;
INSERT INTO `visitors` VALUES (1,'John Smith','Tech Solutions Inc.','+1 (555) 123-4567',NULL,'Web Development','Sarah Johnson',NULL,NULL,'2026-09-23','2026-09-23 00:09:13','2026-09-23 00:09:13',NULL,NULL),(2,'Emily Davis','Global Marketing Ltd.','+1 (555) 234-5678',NULL,'Digital Marketing','Michael Brown',NULL,NULL,'2026-09-25','2026-09-25 00:09:13','2026-09-25 00:09:13',NULL,NULL),(3,'Robert Wilson','Data Analytics Co.','+1 (555) 345-6789',NULL,'Data Analysis','Sarah Johnson',NULL,NULL,'2026-09-27','2026-09-27 00:09:13','2026-09-27 00:09:13',NULL,NULL),(4,'Lisa Taylor','Creative Designs LLC','+1 (555) 456-7890',NULL,'Graphic Design','David Wilson',NULL,NULL,'2026-09-28','2026-09-28 00:09:13','2026-09-28 00:09:13',NULL,NULL),(5,'James Anderson','Software Innovations','+1 (555) 567-8901',NULL,'Mobile App Development','Michael Brown',NULL,NULL,'2026-09-21','2026-09-21 00:09:13','2026-09-21 00:09:13',NULL,NULL),(6,'Patricia Martinez','E-commerce Solutions','+1 (555) 678-9012',NULL,'E-commerce Platform','Sarah Johnson',NULL,NULL,'2026-09-26','2026-09-26 00:09:13','2026-09-26 00:09:13',NULL,NULL),(7,'Thomas Robinson','Cloud Services Ltd.','+1 (555) 789-0123',NULL,'Cloud Hosting','David Wilson',NULL,NULL,'2026-09-24','2026-09-24 00:09:13','2026-09-24 00:09:13',NULL,NULL),(8,'Jennifer Clark','Social Media Experts','+1 (555) 890-1234',NULL,'Social Media Management','Michael Brown',NULL,NULL,'2026-09-22','2026-09-22 00:09:13','2026-09-22 00:09:13',NULL,NULL);
/*!40000 ALTER TABLE `visitors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `worthy_employees`
--

DROP TABLE IF EXISTS `worthy_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `worthy_employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(20) unsigned NOT NULL,
  `currency_id` bigint(20) unsigned DEFAULT NULL,
  `base_currency_id` bigint(20) unsigned DEFAULT NULL,
  `currency_code` varchar(10) DEFAULT NULL,
  `currency_name` varchar(255) DEFAULT NULL,
  `currency_symbol` varchar(10) DEFAULT NULL,
  `currency_decimal_places` tinyint(3) unsigned DEFAULT NULL,
  `base_currency_code` varchar(10) DEFAULT NULL,
  `rate` decimal(20,8) DEFAULT NULL,
  `base_amount` decimal(20,4) DEFAULT NULL,
  `currency_snapshot_source` varchar(40) NOT NULL DEFAULT 'recorded_at_creation',
  `source_task_id` bigint(20) unsigned DEFAULT NULL,
  `is_automatic` tinyint(1) NOT NULL DEFAULT 0,
  `performance_score` decimal(3,1) NOT NULL DEFAULT 0.0,
  `achievement_details` text NOT NULL,
  `suggested_amount` decimal(20,8) NOT NULL,
  `selected_amount` decimal(20,8) NOT NULL,
  `selected_type` varchar(255) NOT NULL,
  `granted_amount` decimal(20,4) DEFAULT NULL,
  `is_granted` tinyint(1) NOT NULL DEFAULT 0,
  `bonus_month` date NOT NULL,
  `calculation_key` varchar(80) DEFAULT NULL,
  `calculation_score` decimal(12,4) DEFAULT NULL,
  `calculation_inputs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`calculation_inputs`)),
  `rule_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rule_snapshot`)),
  `calculated_at` timestamp NULL DEFAULT NULL,
  `granted_at` timestamp NULL DEFAULT NULL,
  `granted_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worthy_employee_task_unique` (`employee_id`,`source_task_id`),
  UNIQUE KEY `worthy_employees_calculation_key_unique` (`calculation_key`),
  KEY `worthy_employees_source_task_id_foreign` (`source_task_id`),
  KEY `worthy_employees_granted_by_foreign` (`granted_by`),
  KEY `worthy_employees_currency_id_foreign` (`currency_id`),
  KEY `worthy_employees_base_currency_id_foreign` (`base_currency_id`),
  CONSTRAINT `worthy_employees_base_currency_id_foreign` FOREIGN KEY (`base_currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `worthy_employees_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `worthy_employees_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `worthy_employees_granted_by_foreign` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `worthy_employees_source_task_id_foreign` FOREIGN KEY (`source_task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `worthy_employees`
--

LOCK TABLES `worthy_employees` WRITE;
/*!40000 ALTER TABLE `worthy_employees` DISABLE KEYS */;
INSERT INTO `worthy_employees` VALUES (1,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'recorded_at_creation',NULL,0,4.9,'Consistently exceeded Q2 velocity targets by 35% without bug regressions.',4000.00000000,4000.00000000,'Performance',NULL,0,'2026-09-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(2,2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'recorded_at_creation',NULL,0,4.8,'Designed and delivered the complete mobile design system 2 weeks early.',2500.00000000,2500.00000000,'Task Completion',NULL,1,'2026-08-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(3,3,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'recorded_at_creation',NULL,0,4.6,'Received outstanding feedback and secured a 3-year contract extension.',1500.00000000,2000.00000000,'Manager Recognition',NULL,1,'2026-07-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL),(4,4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'recorded_at_creation',NULL,0,4.7,'Reached 5-year tenure milestone with zero critical downtime incidents.',3000.00000000,3000.00000000,'Work Anniversary',NULL,0,'2026-09-01',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-28 00:09:13','2026-09-28 00:09:13',NULL);
/*!40000 ALTER TABLE `worthy_employees` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
