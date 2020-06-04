#
# v1.01 -> v1.02
#

# powerbi
ALTER TABLE `dataset_files` ADD `powerbi` VARCHAR(300) NULL AFTER `created_at`;

ALTER TABLE `dataset` ADD `powerbi` VARCHAR(300) NULL AFTER `downloaded`;

ALTER TABLE `dataset_files` ADD `ord` INT NOT NULL DEFAULT '1' AFTER `id`;

# mapa
ALTER TABLE `dataset_files` ADD `map` VARCHAR(300) NULL AFTER `powerbi`;

ALTER TABLE `dataset` ADD `map` VARCHAR(300) NULL AFTER `powerbi`;

#
# v1.02a -> v1.02b
#

# banner statistiky

create table if not exists banner_stats (
	`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	day varchar(20),
	count int
);

#
# v1.02b -> v1.03
#

# rok, mestska cast
ALTER TABLE `dataset` ADD `year` INT NULL AFTER `category`, ADD `district` VARCHAR(50) NULL AFTER `year`;

#
# v1.03a -> v1.04
#

# zmeny názvov stĺpcov
ALTER TABLE `authors` CHANGE `name` `name_sk` VARCHAR(250) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `category` CHANGE `name` `name_sk` VARCHAR(250) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `dataset` CHANGE `name` `name_sk` VARCHAR(250) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `dataset` CHANGE `description` `description_sk` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `dataset_files` CHANGE `name` `name_sk` VARCHAR(250) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
ALTER TABLE `tags` CHANGE `name` `name_sk` VARCHAR(250) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;

ALTER TABLE `authors` ADD `name_en` VARCHAR(250) NOT NULL AFTER `name_sk`;
ALTER TABLE `category` ADD `name_en` VARCHAR(250) NOT NULL AFTER `name_sk`;
ALTER TABLE `dataset` ADD `name_en` VARCHAR(250) NOT NULL AFTER `name_sk`;
ALTER TABLE `dataset` ADD `description_en` text NOT NULL AFTER `description_sk`;
ALTER TABLE `dataset_files` ADD `name_en` VARCHAR(250) NOT NULL AFTER `name_sk`;
ALTER TABLE `tags` ADD `name_en` VARCHAR(250) NOT NULL AFTER `name_sk`;

ALTER TABLE `authors` ADD FULLTEXT(`name_en`);
ALTER TABLE `category` ADD FULLTEXT(`name_en`);
ALTER TABLE `dataset` ADD FULLTEXT(`name_en`);
ALTER TABLE `dataset` ADD FULLTEXT(`description_en`);
ALTER TABLE `dataset_files` ADD FULLTEXT(`name_en`);
ALTER TABLE `tags` ADD FULLTEXT(`name_en`);

ALTER TABLE `dataset` DROP INDEX `FullText`;
ALTER TABLE `dataset`
	ADD FULLTEXT INDEX `FullText`
		(`name_sk` ASC, `name_en` ASC, `slug` ASC, `description_sk` ASC, `description_en` ASC, `licence` ASC);

ALTER TABLE `dataset_tags` ADD FOREIGN KEY (dataset) REFERENCES dataset(id);

# UPDATE authors SET name_en=name_sk;
# UPDATE category SET name_en=name_sk;
# UPDATE dataset SET name_en=name_sk;
# UPDATE dataset SET description_en=description_sk;
# UPDATE dataset_files SET name_en=name_sk;
# UPDATE tags SET name_en=name_sk;

ALTER TABLE `dataset` ADD `onlinedata` TINYINT(4) NOT NULL DEFAULT '0' AFTER `map`;
ALTER TABLE `category` ADD `picto` varchar(50) NOT NULL AFTER `slug`;


# Online Data - Summary
create table if not exists onlinedata_summary (
	`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	data_date varchar(20),
	new_users int not null,
	returning_users int not null,
	average_online int not null,
	max_online int not null,
	dwell_5m int not null,
	dwell_10m int not null,
	dwell_30m int not null,
	dwell_60m int not null,
	dwell_long int not null
);

# Online Data - Locations
create table if not exists onlinedata_locations (
	`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	data_date varchar(20),
	location varchar(150),
	value int not null
);

# Online Data - Form Data
create table if not exists onlinedata_form (
	`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	data_date varchar(20),
	type enum('device_type', 'os', 'browser', 'lang'),
	name varchar(100),
	value varchar(100)
);

ALTER TABLE `onlinedata_summary` ADD INDEX `data_date` (`data_date`);
ALTER TABLE `onlinedata_locations` ADD INDEX `data_date` (`data_date`);
ALTER TABLE `onlinedata_locations` ADD INDEX `data_date` (`location`);
ALTER TABLE `onlinedata_form` ADD INDEX `data_date` (`data_date`);
ALTER TABLE `onlinedata_form` ADD INDEX `data_date` (`type`);
