/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activities` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `additional` json DEFAULT NULL,
  `schedule_from` datetime DEFAULT NULL,
  `schedule_to` datetime DEFAULT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT '0',
  `publish_to_portal` tinyint(1) NOT NULL DEFAULT '0',
  `user_id` int unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_id` int unsigned DEFAULT NULL,
  `lead_id` int unsigned DEFAULT NULL,
  `sales_lead_id` bigint unsigned DEFAULT NULL,
  `order_id` bigint unsigned DEFAULT NULL,
  `clinic_id` bigint unsigned DEFAULT NULL,
  `person_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activities_user_id_foreign` (`user_id`),
  KEY `activities_group_id_foreign` (`group_id`),
  KEY `activities_lead_id_foreign` (`lead_id`),
  KEY `activities_external_id_index` (`external_id`),
  KEY `activities_clinic_id_foreign` (`clinic_id`),
  CONSTRAINT `activities_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activities_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activities_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activity_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_files_activity_id_foreign` (`activity_id`),
  CONSTRAINT `activity_files_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `street` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `house_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `postal_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `house_number_suffix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `addresses_created_by_foreign` (`created_by`),
  KEY `addresses_updated_by_foreign` (`updated_by`),
  CONSTRAINT `addresses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `addresses_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `anamnesis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `anamnesis` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deleted` tinyint NOT NULL DEFAULT '0',
  `team_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `team_set_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment_clinic` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `height` int DEFAULT NULL,
  `weight` int DEFAULT NULL,
  `metals` tinyint DEFAULT NULL,
  `metals_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `medications` tinyint DEFAULT NULL,
  `medications_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `glaucoma` tinyint DEFAULT NULL,
  `glaucoma_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `claustrophobia` tinyint DEFAULT NULL,
  `dormicum` tinyint DEFAULT NULL,
  `heart_surgery` tinyint DEFAULT NULL,
  `heart_surgery_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `implant` tinyint DEFAULT NULL,
  `implant_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `surgeries` tinyint DEFAULT NULL,
  `surgeries_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `remarks` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hereditary_heart` tinyint DEFAULT NULL,
  `hereditary_heart_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hereditary_vascular` tinyint DEFAULT NULL,
  `hereditary_vascular_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hereditary_tumors` tinyint DEFAULT NULL,
  `hereditary_tumors_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `allergies` tinyint DEFAULT NULL,
  `allergies_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `back_problems` tinyint DEFAULT NULL,
  `back_problems_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `heart_problems` tinyint DEFAULT NULL,
  `heart_problems_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `smoking` tinyint DEFAULT NULL,
  `smoking_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `diabetes` tinyint DEFAULT NULL,
  `spijsverteringsklachten` tinyint(1) NOT NULL DEFAULT '0',
  `digestive_complaints_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `diabetes_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `digestive_problems` tinyint DEFAULT NULL,
  `digestive_problems_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `heart_attack_risk` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint DEFAULT NULL,
  `advice_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `lead_id` int unsigned DEFAULT NULL,
  `sales_id` bigint unsigned DEFAULT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `gvl_form_link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `anamnesis_lead_person_unique` (`lead_id`,`person_id`),
  UNIQUE KEY `anamnesis_sales_person_unique` (`sales_id`,`person_id`),
  KEY `anamnesis_person_id_foreign` (`person_id`),
  KEY `anamnesis_created_by_foreign` (`created_by`),
  KEY `anamnesis_updated_by_foreign` (`updated_by`),
  CONSTRAINT `anamnesis_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `anamnesis_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `anamnesis_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `anamnesis_sales_id_foreign` FOREIGN KEY (`sales_id`) REFERENCES `salesleads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `anamnesis_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attribute_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attribute_options` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT NULL,
  `attribute_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `attribute_options_attribute_id_foreign` (`attribute_id`),
  CONSTRAINT `attribute_options_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attribute_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attribute_values` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'leads',
  `text_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `boolean_value` tinyint(1) DEFAULT NULL,
  `integer_value` int DEFAULT NULL,
  `float_value` double DEFAULT NULL,
  `datetime_value` datetime DEFAULT NULL,
  `date_value` date DEFAULT NULL,
  `json_value` json DEFAULT NULL,
  `entity_id` int unsigned NOT NULL,
  `attribute_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entity_type_attribute_value_index_unique` (`entity_type`,`entity_id`,`attribute_id`),
  KEY `attribute_values_attribute_id_foreign` (`attribute_id`),
  CONSTRAINT `attribute_values_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `attributes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lookup_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int DEFAULT NULL,
  `validation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `is_unique` tinyint(1) NOT NULL DEFAULT '0',
  `quick_add` tinyint(1) NOT NULL DEFAULT '0',
  `is_user_defined` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attributes_code_entity_type_unique` (`code`,`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `call_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `call_statuses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `omschrijving` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `activity_id` int unsigned NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `call_statuses_activity_id_foreign` (`activity_id`),
  KEY `call_statuses_created_by_foreign` (`created_by`),
  KEY `call_statuses_updated_by_foreign` (`updated_by`),
  CONSTRAINT `call_statuses_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `call_statuses_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `call_statuses_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinic_partner_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinic_partner_product` (
  `clinic_id` bigint unsigned NOT NULL,
  `partner_product_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`clinic_id`,`partner_product_id`),
  KEY `clinic_partner_product_partner_product_id_foreign` (`partner_product_id`),
  CONSTRAINT `clinic_partner_product_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clinic_partner_product_partner_product_id_foreign` FOREIGN KEY (`partner_product_id`) REFERENCES `partner_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `clinics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `clinics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `registration_form_clinic_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_confirmation_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `emails` json DEFAULT NULL,
  `phones` json DEFAULT NULL,
  `visit_address_id` bigint unsigned DEFAULT NULL,
  `postal_address_id` bigint unsigned DEFAULT NULL,
  `is_postal_address_same_as_visit_address` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clinics_name_unique` (`name`),
  KEY `clinics_created_by_foreign` (`created_by`),
  KEY `clinics_updated_by_foreign` (`updated_by`),
  KEY `clinics_external_id_index` (`external_id`),
  KEY `clinics_visit_address_id_foreign` (`visit_address_id`),
  KEY `clinics_postal_address_id_foreign` (`postal_address_id`),
  CONSTRAINT `clinics_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinics_postal_address_id_foreign` FOREIGN KEY (`postal_address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinics_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clinics_visit_address_id_foreign` FOREIGN KEY (`visit_address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `core_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `core_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `country_states`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `country_states` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `country_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `country_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `country_states_country_id_foreign` (`country_id`),
  CONSTRAINT `country_states_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `datagrid_saved_filters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `datagrid_saved_filters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `src` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `datagrid_saved_filters_user_id_name_src_unique` (`user_id`,`name`,`src`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `duplicates_false_positives`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `duplicates_false_positives` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id_1` bigint unsigned NOT NULL,
  `entity_id_2` bigint unsigned NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dfp_entity_pair_unique` (`entity_type`,`entity_id_1`,`entity_id_2`),
  KEY `duplicates_false_positives_created_by_foreign` (`created_by`),
  KEY `duplicates_false_positives_updated_by_foreign` (`updated_by`),
  CONSTRAINT `duplicates_false_positives_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `duplicates_false_positives_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_attachments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` int DEFAULT NULL,
  `content_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_attachments_email_id_foreign` (`email_id`),
  CONSTRAINT `email_attachments_email_id_foreign` FOREIGN KEY (`email_id`) REFERENCES `emails` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sync_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'graph',
  `started_at` timestamp NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `processed_count` int NOT NULL DEFAULT '0',
  `error_count` int NOT NULL DEFAULT '0',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_logs_sync_type_started_at_index` (`sync_type`,`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_tags` (
  `tag_id` int unsigned NOT NULL,
  `email_id` int unsigned NOT NULL,
  KEY `email_tags_tag_id_foreign` (`tag_id`),
  KEY `email_tags_email_id_foreign` (`email_id`),
  CONSTRAINT `email_tags_email_id_foreign` FOREIGN KEY (`email_id`) REFERENCES `emails` (`id`) ON DELETE CASCADE,
  CONSTRAINT `email_tags_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'algemeen',
  `language` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nl',
  `departments` json DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_templates_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `emails` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reply` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `folder_id` int unsigned DEFAULT NULL,
  `from` json DEFAULT NULL,
  `sender` json DEFAULT NULL,
  `reply_to` json DEFAULT NULL,
  `cc` json DEFAULT NULL,
  `bcc` json DEFAULT NULL,
  `unique_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_ids` json DEFAULT NULL,
  `person_id` int unsigned DEFAULT NULL,
  `lead_id` int unsigned DEFAULT NULL,
  `sales_lead_id` bigint unsigned DEFAULT NULL,
  `clinic_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `emails_unique_id_unique` (`unique_id`),
  KEY `emails_person_id_foreign` (`person_id`),
  KEY `emails_lead_id_foreign` (`lead_id`),
  KEY `emails_parent_id_foreign` (`parent_id`),
  KEY `emails_folder_id_foreign` (`folder_id`),
  KEY `emails_sales_lead_id_foreign` (`sales_lead_id`),
  KEY `emails_clinic_id_foreign` (`clinic_id`),
  CONSTRAINT `emails_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `emails_folder_id_foreign` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `emails_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `emails_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `emails` (`id`) ON DELETE CASCADE,
  CONSTRAINT `emails_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `emails_sales_lead_id_foreign` FOREIGN KEY (`sales_lead_id`) REFERENCES `salesleads` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `folders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `folders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `order` int NOT NULL DEFAULT '0',
  `is_deletable` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folders_name_parent_id_unique` (`name`,`parent_id`),
  KEY `folders_parent_id_foreign` (`parent_id`),
  CONSTRAINT `folders_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `groups_name_unique` (`name`),
  KEY `groups_department_id_foreign` (`department_id`),
  CONSTRAINT `groups_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_batches` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `data` json NOT NULL,
  `summary` json DEFAULT NULL,
  `import_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `import_batches_import_id_foreign` (`import_id`),
  CONSTRAINT `import_batches_import_id_foreign` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `import_run_id` bigint unsigned NOT NULL,
  `level` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `context` json DEFAULT NULL,
  `record_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_logs_import_run_id_foreign` (`import_run_id`),
  CONSTRAINT `import_logs_import_run_id_foreign` FOREIGN KEY (`import_run_id`) REFERENCES `import_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `import_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `import_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'running',
  `import_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `records_processed` int NOT NULL DEFAULT '0',
  `records_imported` int NOT NULL DEFAULT '0',
  `records_skipped` int NOT NULL DEFAULT '0',
  `records_errored` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `import_runs_created_by_foreign` (`created_by`),
  KEY `import_runs_updated_by_foreign` (`updated_by`),
  CONSTRAINT `import_runs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `import_runs_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `imports` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `state` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `process_in_queue` tinyint(1) NOT NULL DEFAULT '1',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `validation_strategy` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `allowed_errors` int NOT NULL DEFAULT '0',
  `processed_rows_count` int NOT NULL DEFAULT '0',
  `invalid_rows_count` int NOT NULL DEFAULT '0',
  `errors_count` int NOT NULL DEFAULT '0',
  `errors` json DEFAULT NULL,
  `field_separator` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `summary` json DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_channels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_channels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_persons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_persons` (
  `lead_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  UNIQUE KEY `lead_persons_lead_id_person_id_unique` (`lead_id`,`person_id`),
  KEY `lead_persons_person_id_foreign` (`person_id`),
  CONSTRAINT `lead_persons_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_persons_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_pipeline_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_pipeline_stages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `probability` int NOT NULL DEFAULT '0',
  `is_won` tinyint(1) NOT NULL DEFAULT '0',
  `is_lost` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '0',
  `lead_pipeline_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_pipeline_stages_code_lead_pipeline_id_unique` (`code`,`lead_pipeline_id`),
  UNIQUE KEY `lead_pipeline_stages_name_lead_pipeline_id_unique` (`name`,`lead_pipeline_id`),
  KEY `lead_pipeline_stages_lead_pipeline_id_foreign` (`lead_pipeline_id`),
  CONSTRAINT `lead_pipeline_stages_lead_pipeline_id_foreign` FOREIGN KEY (`lead_pipeline_id`) REFERENCES `lead_pipelines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_pipelines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_pipelines` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `type` enum('lead','workflow','order') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'lead',
  `rotten_days` int NOT NULL DEFAULT '30',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_sources` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_stages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_user_defined` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_tags` (
  `tag_id` int unsigned NOT NULL,
  `lead_id` int unsigned NOT NULL,
  KEY `lead_tags_tag_id_foreign` (`tag_id`),
  KEY `lead_tags_lead_id_foreign` (`lead_id`),
  CONSTRAINT `lead_tags_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_tags_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lead_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lead_types` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leads` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lead_channel_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `organization_id` int unsigned DEFAULT NULL,
  `contact_person_id` int unsigned DEFAULT NULL,
  `address_id` bigint unsigned DEFAULT NULL,
  `salutation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastname_prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `married_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `married_name_prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `initials` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `emails` json DEFAULT NULL,
  `phones` json DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `lost_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `closed_at` datetime DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `lead_source_id` int unsigned DEFAULT NULL,
  `lead_type_id` int unsigned DEFAULT NULL,
  `lead_pipeline_id` int unsigned DEFAULT NULL,
  `lead_pipeline_stage_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `mri_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diagnosis_form_id` int unsigned DEFAULT NULL,
  `diagnoseform_pdf_url` text COLLATE utf8mb4_unicode_ci,
  `national_identification_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'BSN',
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leads_lead_source_id_foreign` (`lead_source_id`),
  KEY `leads_lead_type_id_foreign` (`lead_type_id`),
  KEY `leads_kanban_performance_idx` (`lead_pipeline_id`,`lead_pipeline_stage_id`,`user_id`),
  KEY `leads_stage_idx` (`lead_pipeline_stage_id`),
  KEY `leads_user_idx` (`user_id`),
  KEY `leads_created_at_idx` (`created_at`),
  KEY `leads_lead_channel_id_foreign` (`lead_channel_id`),
  KEY `leads_department_id_foreign` (`department_id`),
  KEY `leads_created_by_foreign` (`created_by`),
  KEY `leads_updated_by_foreign` (`updated_by`),
  KEY `leads_external_id_index` (`external_id`),
  KEY `leads_organization_id_foreign` (`organization_id`),
  KEY `leads_deleted_at_index` (`deleted_at`),
  KEY `leads_address_id_foreign` (`address_id`),
  CONSTRAINT `leads_address_id_foreign` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_lead_channel_id_foreign` FOREIGN KEY (`lead_channel_id`) REFERENCES `lead_channels` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_lead_pipeline_id_foreign` FOREIGN KEY (`lead_pipeline_id`) REFERENCES `lead_pipelines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leads_lead_pipeline_stage_id_foreign` FOREIGN KEY (`lead_pipeline_stage_id`) REFERENCES `lead_pipeline_stages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_lead_source_id_foreign` FOREIGN KEY (`lead_source_id`) REFERENCES `lead_sources` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `leads_lead_type_id_foreign` FOREIGN KEY (`lead_type_id`) REFERENCES `lead_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `leads_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_campaigns` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mail_to` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `spooling` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `marketing_template_id` int unsigned DEFAULT NULL,
  `marketing_event_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `marketing_campaigns_marketing_template_id_foreign` (`marketing_template_id`),
  KEY `marketing_campaigns_marketing_event_id_foreign` (`marketing_event_id`),
  KEY `marketing_campaigns_external_id_index` (`external_id`),
  CONSTRAINT `marketing_campaigns_marketing_event_id_foreign` FOREIGN KEY (`marketing_event_id`) REFERENCES `marketing_events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `marketing_campaigns_marketing_template_id_foreign` FOREIGN KEY (`marketing_template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `marketing_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketing_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_checks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `done` tinyint(1) NOT NULL DEFAULT '0',
  `removable` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_checks_order_id_foreign` (`order_id`),
  CONSTRAINT `order_checks_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `product_type_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `person_id` int unsigned DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `total_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_product_id_foreign` (`product_id`),
  KEY `order_items_created_by_foreign` (`created_by`),
  KEY `order_items_updated_by_foreign` (`updated_by`),
  KEY `order_items_person_id_foreign` (`person_id`),
  KEY `order_items_product_type_id_foreign` (`product_type_id`),
  CONSTRAINT `order_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `order_items_product_type_id_foreign` FOREIGN KEY (`product_type_id`) REFERENCES `product_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_number_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_number_sequences` (
  `year` int unsigned NOT NULL,
  `last_number` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(9) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sales_lead_id` bigint unsigned NOT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `combine_order` tinyint(1) NOT NULL DEFAULT '1',
  `total_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `confirmation_letter_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `pipeline_stage_id` int unsigned DEFAULT NULL,
  `first_examination_at` datetime DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_order_number_unique` (`order_number`),
  KEY `orders_created_by_foreign` (`created_by`),
  KEY `orders_updated_by_foreign` (`updated_by`),
  KEY `orders_sales_lead_id_foreign` (`sales_lead_id`),
  KEY `orders_pipeline_stage_id_foreign` (`pipeline_stage_id`),
  KEY `orders_user_id_foreign` (`user_id`),
  CONSTRAINT `orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_pipeline_stage_id_foreign` FOREIGN KEY (`pipeline_stage_id`) REFERENCES `lead_pipeline_stages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_sales_lead_id_foreign` FOREIGN KEY (`sales_lead_id`) REFERENCES `salesleads` (`id`),
  CONSTRAINT `orders_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `organizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `organizations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `address_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `organizations_user_id_foreign` (`user_id`),
  KEY `organizations_created_by_foreign` (`created_by`),
  KEY `organizations_updated_by_foreign` (`updated_by`),
  KEY `organizations_address_id_foreign` (`address_id`),
  CONSTRAINT `organizations_address_id_foreign` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organizations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organizations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `organizations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partner_product_activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_product_activities` (
  `activity_id` int unsigned NOT NULL,
  `partner_product_id` bigint unsigned NOT NULL,
  KEY `partner_product_activities_activity_id_foreign` (`activity_id`),
  KEY `partner_product_activities_partner_product_id_foreign` (`partner_product_id`),
  CONSTRAINT `partner_product_activities_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `partner_product_activities_partner_product_id_foreign` FOREIGN KEY (`partner_product_id`) REFERENCES `partner_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partner_product_related`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_product_related` (
  `partner_product_id` bigint unsigned NOT NULL,
  `related_product_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`partner_product_id`,`related_product_id`),
  UNIQUE KEY `partner_product_related_unique` (`related_product_id`,`partner_product_id`),
  CONSTRAINT `partner_product_related_partner_product_id_foreign` FOREIGN KEY (`partner_product_id`) REFERENCES `partner_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `partner_product_related_related_product_id_foreign` FOREIGN KEY (`related_product_id`) REFERENCES `partner_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partner_product_resource`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_product_resource` (
  `partner_product_id` bigint unsigned NOT NULL,
  `resource_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`partner_product_id`,`resource_id`),
  KEY `partner_product_resource_resource_id_foreign` (`resource_id`),
  CONSTRAINT `partner_product_resource_partner_product_id_foreign` FOREIGN KEY (`partner_product_id`) REFERENCES `partner_products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `partner_product_resource_resource_id_foreign` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `partner_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned DEFAULT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `sales_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `related_sales_price` decimal(12,2) DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `discount_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `resource_type_id` bigint unsigned DEFAULT NULL,
  `clinic_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `duration` int unsigned DEFAULT NULL,
  `reporting` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `partner_products_created_by_foreign` (`created_by`),
  KEY `partner_products_updated_by_foreign` (`updated_by`),
  KEY `partner_products_resource_type_id_foreign` (`resource_type_id`),
  KEY `partner_products_external_id_index` (`external_id`),
  KEY `partner_products_product_id_foreign` (`product_id`),
  CONSTRAINT `partner_products_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `partner_products_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `partner_products_resource_type_id_foreign` FOREIGN KEY (`resource_type_id`) REFERENCES `resource_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `partner_products_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `patient_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `activity_id` int unsigned DEFAULT NULL,
  `person_id` int unsigned NOT NULL,
  `sender_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sender_id` int unsigned DEFAULT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_messages_person_id_foreign` (`person_id`),
  KEY `patient_messages_activity_id_foreign` (`activity_id`),
  CONSTRAINT `patient_messages_activity_id_foreign` FOREIGN KEY (`activity_id`) REFERENCES `activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_messages_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `patient_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int unsigned NOT NULL,
  `dismissable` tinyint(1) NOT NULL DEFAULT '0',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `summary` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_names` json DEFAULT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` bigint unsigned NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `dismissed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `last_notified_by_email_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_notifications_created_by_foreign` (`created_by`),
  KEY `patient_notifications_updated_by_foreign` (`updated_by`),
  KEY `patient_notifications_patient_active_idx` (`patient_id`,`dismissed_at`,`expires_at`),
  CONSTRAINT `patient_notifications_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `patient_notifications_patient_id_foreign` FOREIGN KEY (`patient_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_notifications_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `person_id` int unsigned NOT NULL,
  `key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json NOT NULL,
  `value_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_system_managed` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `person_preferences_created_by_foreign` (`created_by`),
  KEY `person_preferences_updated_by_foreign` (`updated_by`),
  KEY `person_preferences_person_id_foreign` (`person_id`),
  CONSTRAINT `person_preferences_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `person_preferences_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `person_preferences_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `person_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `person_tags` (
  `tag_id` int unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  KEY `person_tags_tag_id_foreign` (`tag_id`),
  KEY `person_tags_person_id_foreign` (`person_id`),
  CONSTRAINT `person_tags_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `person_tags_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `persons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `persons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `salutation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastname_prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `married_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `married_name_prefix` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `initials` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emails` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phones` json DEFAULT NULL,
  `organization_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `job_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `keycloak_user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `preferred_language` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `onboarding_completed_at` datetime DEFAULT NULL,
  `password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `national_identification_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'BSN',
  `address_id` bigint unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `persons_user_id_foreign` (`user_id`),
  KEY `persons_organization_id_foreign` (`organization_id`),
  KEY `persons_created_by_foreign` (`created_by`),
  KEY `persons_updated_by_foreign` (`updated_by`),
  KEY `persons_external_id_index` (`external_id`),
  KEY `persons_deleted_at_index` (`deleted_at`),
  KEY `persons_address_id_foreign` (`address_id`),
  CONSTRAINT `persons_address_id_foreign` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `persons_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `persons_organization_id_foreign` FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `persons_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `persons_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `parent_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_groups_parent_id_foreign` (`parent_id`),
  KEY `product_groups_created_by_foreign` (`created_by`),
  KEY `product_groups_updated_by_foreign` (`updated_by`),
  CONSTRAINT `product_groups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_groups_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `product_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_groups_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_inventories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_inventories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `in_stock` int NOT NULL DEFAULT '0',
  `allocated` int NOT NULL DEFAULT '0',
  `product_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_inventories_product_id_foreign` (`product_id`),
  CONSTRAINT `product_inventories_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_tags` (
  `tag_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  KEY `product_tags_tag_id_foreign` (`tag_id`),
  KEY `product_tags_product_id_foreign` (`product_id`),
  CONSTRAINT `product_tags_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_tags_tag_id_foreign` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `product_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `product_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_types_name_unique` (`name`),
  KEY `product_types_created_by_foreign` (`created_by`),
  KEY `product_types_updated_by_foreign` (`updated_by`),
  KEY `product_types_external_id_index` (`external_id`),
  CONSTRAINT `product_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_types_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'EUR',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` decimal(12,4) DEFAULT NULL,
  `resource_type_id` bigint unsigned DEFAULT NULL,
  `product_type_id` bigint unsigned DEFAULT NULL,
  `costs` decimal(12,4) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `product_group_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `products_product_group_id_foreign` (`product_group_id`),
  KEY `products_resource_type_id_foreign` (`resource_type_id`),
  KEY `products_product_type_id_foreign` (`product_type_id`),
  KEY `products_external_id_index` (`external_id`),
  CONSTRAINT `products_product_group_id_foreign` FOREIGN KEY (`product_group_id`) REFERENCES `product_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_product_type_id_foreign` FOREIGN KEY (`product_type_id`) REFERENCES `product_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_resource_type_id_foreign` FOREIGN KEY (`resource_type_id`) REFERENCES `resource_types` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `purchase_prices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `priceable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priceable_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'main',
  `purchase_price_misc` decimal(10,2) DEFAULT NULL,
  `purchase_price_doctor` decimal(10,2) DEFAULT NULL,
  `purchase_price_cardiology` decimal(10,2) DEFAULT NULL,
  `purchase_price_clinic` decimal(10,2) DEFAULT NULL,
  `purchase_price_radiology` decimal(10,2) DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_prices_priceable_type_priceable_id_type_index` (`priceable_type`,`priceable_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_orderitem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_orderitem` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `orderitem_id` bigint unsigned NOT NULL,
  `from` datetime NOT NULL,
  `to` datetime NOT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resource_orderitem_resource_id_foreign` (`resource_id`),
  KEY `resource_orderitem_orderitem_id_foreign` (`orderitem_id`),
  KEY `resource_orderitem_created_by_foreign` (`created_by`),
  KEY `resource_orderitem_updated_by_foreign` (`updated_by`),
  CONSTRAINT `resource_orderitem_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `resource_orderitem_orderitem_id_foreign` FOREIGN KEY (`orderitem_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `resource_orderitem_resource_id_foreign` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `resource_orderitem_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resource_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `resource_types_name_unique` (`name`),
  KEY `resource_types_created_by_foreign` (`created_by`),
  KEY `resource_types_updated_by_foreign` (`updated_by`),
  KEY `resource_types_external_id_index` (`external_id`),
  CONSTRAINT `resource_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `resource_types_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `resources` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `external_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resource_type_id` bigint unsigned NOT NULL,
  `clinic_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `allow_outside_availability` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resources_created_by_foreign` (`created_by`),
  KEY `resources_updated_by_foreign` (`updated_by`),
  KEY `resources_name_index` (`name`),
  KEY `resources_resource_type_id_foreign` (`resource_type_id`),
  KEY `resources_clinic_id_foreign` (`clinic_id`),
  KEY `resources_external_id_index` (`external_id`),
  CONSTRAINT `resources_clinic_id_foreign` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  CONSTRAINT `resources_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `resources_resource_type_id_foreign` FOREIGN KEY (`resource_type_id`) REFERENCES `resource_types` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `resources_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permission_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissions` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `saleslead_persons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `saleslead_persons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `saleslead_id` bigint unsigned NOT NULL,
  `person_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saleslead_persons_saleslead_id_person_id_unique` (`saleslead_id`,`person_id`),
  KEY `saleslead_persons_person_id_foreign` (`person_id`),
  CONSTRAINT `saleslead_persons_person_id_foreign` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saleslead_persons_saleslead_id_foreign` FOREIGN KEY (`saleslead_id`) REFERENCES `salesleads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salesleads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `salesleads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `lost_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `closed_at` date DEFAULT NULL,
  `pipeline_stage_id` int unsigned NOT NULL,
  `lead_id` int unsigned DEFAULT NULL,
  `quote_id` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `contact_person_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `shifts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `resource_id` bigint unsigned NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `available` tinyint(1) NOT NULL DEFAULT '1',
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `weekday_time_blocks` json DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `shifts_created_by_foreign` (`created_by`),
  KEY `shifts_updated_by_foreign` (`updated_by`),
  KEY `shifts_resource_id_index` (`resource_id`),
  CONSTRAINT `shifts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `shifts_resource_id_foreign` FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shifts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tags_user_id_foreign` (`user_id`),
  CONSTRAINT `tags_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries` (
  `sequence` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch_id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `family_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `should_display_on_index` tinyint(1) NOT NULL DEFAULT '1',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`sequence`),
  UNIQUE KEY `telescope_entries_uuid_unique` (`uuid`),
  KEY `telescope_entries_batch_id_index` (`batch_id`),
  KEY `telescope_entries_family_hash_index` (`family_hash`),
  KEY `telescope_entries_created_at_index` (`created_at`),
  KEY `telescope_entries_type_should_display_on_index_index` (`type`,`should_display_on_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_entries_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_entries_tags` (
  `entry_uuid` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`entry_uuid`,`tag`),
  KEY `telescope_entries_tags_tag_index` (`tag`),
  CONSTRAINT `telescope_entries_tags_entry_uuid_foreign` FOREIGN KEY (`entry_uuid`) REFERENCES `telescope_entries` (`uuid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `telescope_monitoring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `telescope_monitoring` (
  `tag` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_default_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_default_values` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_default_values_user_id_key_unique` (`user_id`,`key`),
  KEY `user_default_values_created_by_foreign` (`created_by`),
  KEY `user_default_values_updated_by_foreign` (`updated_by`),
  CONSTRAINT `user_default_values_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_default_values_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `user_default_values_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_groups` (
  `group_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  KEY `user_groups_group_id_foreign` (`group_id`),
  KEY `user_groups_user_id_foreign` (`user_id`),
  CONSTRAINT `user_groups_group_id_foreign` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_groups_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_password_resets` (
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `user_password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `external_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keycloak_user_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `view_permission` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'global',
  `signature` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `role_id` int unsigned NOT NULL,
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_id_foreign` (`role_id`),
  KEY `users_external_id_index` (`external_id`),
  KEY `users_created_by_foreign` (`created_by`),
  KEY `users_updated_by_foreign` (`updated_by`),
  CONSTRAINT `users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `web_form_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `web_form_attributes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `placeholder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int DEFAULT NULL,
  `attribute_id` int unsigned NOT NULL,
  `web_form_id` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `web_form_attributes_attribute_id_foreign` (`attribute_id`),
  KEY `web_form_attributes_web_form_id_foreign` (`web_form_id`),
  CONSTRAINT `web_form_attributes_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `web_form_attributes_web_form_id_foreign` FOREIGN KEY (`web_form_id`) REFERENCES `web_forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `web_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `web_forms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `form_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `submit_button_label` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `submit_success_action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `submit_success_content` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `create_lead` tinyint(1) NOT NULL DEFAULT '0',
  `background_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_background_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_title_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `form_submit_button_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribute_label_color` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `web_forms_form_id_unique` (`form_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `webhooks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `method` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `end_point` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `query_params` json DEFAULT NULL,
  `headers` json DEFAULT NULL,
  `payload_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `raw_payload_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `workflows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflows` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `workflow_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `condition_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'and',
  `conditions` json DEFAULT NULL,
  `actions` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*M!999999\- enable the sandbox mode */ 
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2018_08_08_100000_create_telescope_entries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2019_08_19_000000_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2021_03_12_060658_create_core_config_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2021_03_12_074578_create_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2021_03_12_074597_create_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2021_03_12_074857_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2021_03_12_074867_create_user_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2021_03_12_074957_create_user_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2021_04_02_080709_create_attributes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2021_04_02_080837_create_attribute_options_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2021_04_06_122751_create_attribute_values_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2021_04_09_051326_create_organizations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2021_04_09_065617_create_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2021_04_09_065617_create_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2021_04_12_173232_create_countries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2021_04_12_173344_create_country_states_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2021_04_21_172825_create_lead_sources_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2021_04_21_172847_create_lead_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2021_04_22_153258_create_lead_stages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2021_04_22_155706_create_lead_pipelines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2021_04_22_155838_create_lead_pipeline_stages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2021_04_22_164215_create_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2021_05_12_150329_create_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2021_05_12_150329_create_lead_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2021_05_15_151855_create_activity_files_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2021_05_20_141230_create_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2021_05_20_141240_create_lead_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2021_05_24_075618_create_emails_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2021_05_25_072700_create_email_attachments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2021_06_07_162808_add_lead_view_permission_column_in_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2021_08_26_133538_create_workflows_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2021_09_03_172713_create_email_templates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2021_09_22_194103_add_unique_index_to_name_in_organizations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2021_09_22_194622_add_unique_index_to_name_in_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2021_09_30_135857_add_column_rotten_days_in_lead_pipelines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2021_09_30_154222_alter_lead_pipeline_stages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2021_09_30_161722_alter_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2021_09_30_183825_change_user_id_to_nullable_in_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2021_11_11_180804_change_lead_pipeline_stage_id_constraint_in_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2021_11_12_171510_add_image_column_in_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2021_11_17_190943_add_location_column_in_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2021_12_14_213049_create_web_forms_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2021_12_14_214923_create_web_form_attributes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2024_01_11_154640_create_imports_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2024_01_11_154741_create_import_batches_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2024_03_19_000000_create_product_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2024_03_21_000001_add_workflow_type_to_workflows_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2024_05_10_152848_create_saved_filters_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2024_06_24_174241_insert_warehouse_attributes_in_attributes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2024_06_28_154009_create_product_inventories_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2024_07_24_150821_create_webhooks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2024_07_31_092951_add_job_title_in_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2024_07_31_093603_add_organization_sales_owner_attribute_in_attributes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2024_07_31_093605_add_person_job_title_attribute_in_attributes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2024_07_31_093605_add_person_sales_owner_attribute_in_attributes_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2024_08_06_145943_create_person_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2024_08_06_161212_create_person_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2024_08_10_150329_create_product_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2024_08_10_150340_create_product_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2024_08_14_102116_add_user_id_column_in_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2024_08_14_102136_add_user_id_column_in_organizations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2024_08_21_153011_add_leads_stage_and_pipeline_attributes',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2024_08_27_091619_create_email_tags_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2024_09_09_094040_create_job_batches_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2024_09_09_094042_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2024_09_09_112201_add_unique_id_to_person_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2024_10_29_044744_create_marketing_events_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2024_11_04_122500_create_marketing_campaigns_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2024_11_29_120302_modify_foreign_keys_in_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2024_12_19_120000_create_folders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2024_12_19_120001_update_emails_table_for_folders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2024_12_19_120002_remove_folders_column_from_emails',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2025_01_15_140000_setup_activity_email_relationship',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2025_01_15_160000_remove_lead_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2025_01_17_151632_alter_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2025_01_20_000001_add_assigned_at_to_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2025_01_20_120000_add_external_id_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2025_01_22_000003_fix_group_id_column_type_in_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2025_01_27_000000_add_kanban_performance_indexes_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2025_01_28_000000_create_email_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2025_01_29_000000_create_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2025_01_29_000000_rename_order_regels_to_order_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2025_01_29_133500_update_text_column_type_in_core_config_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2025_01_30_000000_create_order_checks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2025_01_31_000000_add_removable_to_order_checks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2025_03_19_132236_update_organization_id_column_in_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2025_03_21_000000_create_workflows_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2025_03_22_150000_create_saleslead_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2025_06_18_000000_add_group_id_to_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2025_06_24_143747_add_type_to_lead_pipelines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2025_07_07_124439_create_addresses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2025_07_07_164324_add_personal_fields_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2025_07_07_164354_add_personal_fields_to_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2025_07_07_204721_add_gender_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2025_07_07_204826_add_gender_to_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2025_07_09_152924_create_lead_channels_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2025_07_09_162030_create_departments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2025_07_09_163000_add_department_id_to_groups_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2025_07_09_163001_link_groups_to_departments',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2025_07_09_165751_add_fields_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2025_07_09_195246_add_emails_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (103,'2025_07_09_205529_add_phones_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (104,'2025_07_09_205801_add_phones_to_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (105,'2025_07_10_164111_add_created_by_and_updated_by_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (106,'2025_07_10_170351_add_created_by_and_updated_by_to_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (107,'2025_07_17_152123_create_anamnesis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (108,'2025_07_22_152123_person_make_name_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (109,'2025_07_23_000000_add_organization_id_to_addresses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (110,'2025_07_23_000001_remove_address_attribute_from_organizations',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (111,'2025_07_23_000002_migrate_organization_addresses',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (112,'2025_07_23_000003_remove_address_column_from_organizations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (113,'2025_07_25_000001_add_audit_trail_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (114,'2025_07_25_000002_add_audit_trail_to_organizations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (115,'2025_07_25_000003_set_default_locale_to_dutch',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (116,'2025_07_25_000004_fix_anamnesis_audit_trail_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (117,'2025_07_25_140000_fix_missing_labels_in_contact_arrays',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (118,'2025_07_25_152123_person_make_emails_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (119,'2025_08_21_194227_add_external_id_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (120,'2025_08_21_194949_add_external_id_to_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (121,'2025_08_22_000000_create_lead_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (122,'2025_08_22_000001_migrate_lead_person_data',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (123,'2025_08_22_000002_remove_person_id_from_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (124,'2025_08_22_000004_add_organization_id_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (125,'2025_08_22_000005_modify_lead_persons_table_structure',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (126,'2025_08_22_000006_remove_title_from_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (127,'2025_08_22_000007_rename_contact_numbers_to_phones_in_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (128,'2025_08_22_000008_add_combine_order_column_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (129,'2025_08_25_000001_add_unique_constraint_anamnesis_lead_person',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (130,'2025_08_25_154833_remove_lead_activities_table_and_add_lead_id_to_activities',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (131,'2025_08_26_000001_add_spijsverteringsklachten_to_anamnesis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (132,'2025_08_26_000002_drop_assigned_user_id_from_anamnesis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (133,'2025_08_29_120000_drop_lead_value_column_from_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (134,'2025_09_01_112454_add_external_id_to_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (135,'2025_09_01_180000_drop_activity_participants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (136,'2025_09_09_000000_add_status_to_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (137,'2025_09_09_121454_add_mri_status_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (138,'2025_09_09_140000_create_call_statuses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (139,'2025_09_10_000000_create_user_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (140,'2025_09_10_000001_add_has_diagnosis_form_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (141,'2025_09_16_000001_make_person_attributes_not_unique',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (142,'2025_09_24_111549_drop_unique_message_id_on_emails_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (143,'2025_09_25_120000_create_clinics_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (144,'2025_09_25_120100_create_resource_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (145,'2025_09_25_130000_create_resources_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (146,'2025_09_26_090000_create_shifts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (147,'2025_09_26_100000_create_product_types_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (148,'2025_09_26_120000_add_resource_type_id_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (149,'2025_09_26_120500_create_partner_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (150,'2025_09_26_150000_update_resources_add_resource_type_id',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (151,'2025_09_26_160000_create_partner_product_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (152,'2025_09_26_161000_drop_type_from_resources_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (153,'2025_09_26_170000_update_products_add_currency_drop_sku',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (154,'2025_09_26_171000_add_external_id_to_core_entities',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (155,'2025_09_29_120000_create_clinic_partner_product_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (156,'2025_09_30_120000_add_clinic_id_to_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (157,'2025_09_30_120000_create_partner_product_related_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (158,'2025_09_30_130000_create_partner_product_resource_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (159,'2025_09_30_150001_drop_quantity_from_products',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (160,'2025_10_01_200500_add_purchase_price_columns_to_partner_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (161,'2025_10_02_120000_add_active_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (162,'2025_10_02_120000_add_registration_form_clinic_name_to_clinics_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (163,'2025_10_02_120600_add_reporting_to_partner_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (164,'2025_10_02_150000_add_costs_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (165,'2025_10_06_120000_add_website_and_order_confirmation_to_clinics',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (166,'2025_10_07_100000_add_first_name_last_name_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (167,'2025_10_07_100000_add_product_id_to_partner_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (168,'2025_10_07_100001_remove_name_column_from_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (169,'2025_10_07_110000_drop_product_partner_product_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (170,'2025_10_07_120000_add_sales_lead_id_to_emails_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (171,'2025_10_07_130000_add_audit_trail_to_salesleads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (172,'2025_10_07_140000_create_import_runs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (173,'2025_10_07_140001_create_import_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (174,'2025_10_08_000100_create_order_regels_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (175,'2025_10_08_000200_add_sales_lead_id_to_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (176,'2025_10_08_000300_move_combine_order_from_leads_to_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (177,'2025_10_08_010000_add_status_to_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (178,'2025_10_08_010100_add_status_to_order_regels_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (179,'2025_10_08_010200_create_resource_orderitem_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (180,'2025_10_13_000000_add_sales_lead_id_to_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (181,'2025_10_13_000001_add_lost_fields_to_salesleads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (182,'2025_10_14_120000_add_person_id_to_orderregels_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (183,'2025_10_15_085352_add_is_won_is_lost_to_lead_pipeline_stages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (184,'2025_10_16_130000_add_is_active_to_clinics_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (185,'2025_10_20_100001_add_deleted_at_to_partner_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (186,'2025_10_20_100003_add_external_id_to_products_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (187,'2025_10_22_120000_add_signature_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (188,'2025_10_22_180005_add_order_to_folders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (189,'2025_10_22_201528_add_created_by_and_updated_by_to_import_runs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (190,'2025_10_23_000000_add_contact_person_id_to_salesleads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (191,'2025_10_23_120000_add_contact_person_id_to_leads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (192,'2025_10_23_120000_add_is_deletable_to_folders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (193,'2025_10_30_100000_add_is_active_to_resources_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (194,'2025_11_05_195626_add_gvl_form_link_to_salesleads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (195,'2025_11_11_132730_add_notes_to_resources_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (196,'2025_11_13_150735_convert_order_status_values_to_english',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (197,'2025_11_13_154909_add_clinic_id_to_emails_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (198,'2025_11_13_180403_add_confirmation_letter_content_to_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (199,'2025_11_17_120000_move_gvl_form_link_from_salesleads_to_anamnesis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (200,'2025_11_18_120837_add_type_and_lang_to_email_templates_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (201,'2025_11_19_124600_add_keycloak_user_id_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (202,'2025_11_24_100000_add_portal_columns_to_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (203,'2025_12_08_122632_add_description_to_lead_pipeline_stages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (204,'2025_12_18_134003_add_first_examination_at_to_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (205,'2025_12_22_163723_create_patient_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (206,'2025_12_22_164535_add_activity_id_to_patient_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (207,'2025_12_22_171858_add_is_read_to_patient_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (208,'2025_12_29_171858_drop_activity_from_email',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (209,'2026_01_06_163148_add_national_identification_number_to_leads_and_persons_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (210,'2026_01_13_000001_add_sales_id_to_anamnesis_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (211,'2026_01_13_000002_add_unique_constraint_anamnesis_sales_person',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (212,'2026_01_13_113314_create_duplicates_false_positives_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (213,'2026_01_15_000000_add_description_to_clinics_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (214,'2026_01_16_143323_drop_product_activities_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (215,'2025_09_10_000002_add_diagnosis_form_id_to_leads_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (216,'2026_01_22_120000_add_external_id_to_marketing_campaigns_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (217,'2026_01_22_161723_add_soft_deletes_to_persons_and_leads_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (218,'2026_01_29_000000_add_order_id_to_activities_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (219,'2026_01_30_000000_move_address_id_to_entities',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (220,'2026_01_30_000001_split_clinic_addresses_into_visit_and_postal',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (221,'2026_02_02_120000_create_patient_notifications_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (222,'2026_02_02_195729_create_person_preferences_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (223,'2026_02_09_150000_drop_unique_id_from_persons_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (224,'2026_02_10_000001_add_pipeline_stage_id_to_orders_and_migrate_status',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (225,'2026_02_10_000002_add_order_type_to_lead_pipelines',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (226,'2026_02_10_000003_change_persons_is_active_default_true',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (227,'2026_02_11_000000_add_diagnoseform_pdf_url_to_leads_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (228,'2026_02_12_000001_add_user_id_to_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (229,'2026_02_17_000000_drop_unique_index_from_name_in_organizations_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (230,'2026_02_17_100000_add_published_to_portal_column_in_activities_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (231,'2026_02_19_000000_drop_type_from_patient_notifications_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (232,'2026_02_20_000000_add_entity_names_to_patient_notifications_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (233,'2026_02_20_000001_add_preferred_language_to_persons_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (234,'2026_02_24_000001_add_onboarding_completed_at_to_persons_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (235,'2026_03_05_add_name_description_to_order_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (236,'2026_03_06_000001_create_order_number_sequences_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (237,'2026_03_06_000002_add_order_number_to_orders_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (238,'2026_03_06_000003_add_product_type_id_to_order_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (239,'2026_03_06_add_currency_to_order_items_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (240,'2026_03_07_create_purchase_prices_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (241,'2026_03_07_migrate_partner_product_purchase_prices',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (242,'2026_03_08_drop_purchase_price_columns_from_partner_products',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (243,'2026_03_09_100000_add_allow_outside_availability_to_resources_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (244,'2026_03_09_200000_add_person_id_to_activities_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (245,'2026_03_09_210000_drop_person_activities_table',3);
