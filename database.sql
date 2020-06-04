#
# Open data
#
# DB tables
#

#Users
CREATE TABLE if not exists `users` (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` varchar(250) NOT NULL,
  `username` varchar(250) NOT NULL,
  `email` varchar(250) NOT NULL,
  `password` varchar(100) NOT NULL,
  `role` varchar(100) NOT NULL DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `blocked` tinyint NOT NULL DEFAULT '0'
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `users` ADD UNIQUE(`username`);
ALTER TABLE `users` ADD FULLTEXT(`name`);

create table if not exists category (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name_sk varchar(250) NOT NULL,
  name_en varchar(250) NOT NULL,
  slug varchar(250) NOT NULL,
  `picto` varchar(50) NOT NULL
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `category` ADD FULLTEXT(`name_sk`);
ALTER TABLE `category` ADD FULLTEXT(`name_en`);


create table if not exists dataset (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name_sk varchar(250) NOT NULL,
  name_en varchar(250) NOT NULL,
  slug varchar(250) NOT NULL,
  description_sk text NOT NULL,
  description_en text NOT NULL,
  authors int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  licence varchar(250) NOT NULL,
  category int not null,
  year int null,
  district varchar(50) null,
  users int not null,
  uniq_id varchar(50) not null,
  downloaded int not null,
  powerbi varchar(300),
  map varchar(300),
  `onlinedata` TINYINT(4) NOT NULL DEFAULT '0',
  hidden tinyint default '1',
  deleted tinyint default '0'
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `dataset` ADD FOREIGN KEY (category) REFERENCES category(id);
ALTER TABLE `dataset` ADD FOREIGN KEY (users) REFERENCES users(id);
ALTER TABLE `dataset` ADD FOREIGN KEY (authors) REFERENCES authors(id);

ALTER TABLE `dataset` ADD FULLTEXT(`name_sk`);
ALTER TABLE `dataset` ADD FULLTEXT(`name_en`);
ALTER TABLE `dataset` ADD FULLTEXT(`slug`);
ALTER TABLE `dataset` ADD FULLTEXT(`description_sk`);
ALTER TABLE `dataset` ADD FULLTEXT(`description_en`);
ALTER TABLE `dataset` ADD FULLTEXT(`licence`);

ALTER TABLE `dataset`
  ADD FULLTEXT INDEX `FullText`
    (`name_sk` ASC, `name_en` ASC, `slug` ASC, `description_sk` ASC, `description_en` ASC, `licence` ASC);


create table if not exists dataset_files (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ord int NOT NULL default '1',
  dataset int NOT NULL,
  name_sk varchar(250) NOT NULL,
  name_en varchar(250) NOT NULL,
  file_type varchar(50) NOT NULL,
  users int not null,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  powerbi varchar(300),
  map varchar(300),
  hidden tinyint default '0'
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `dataset_files` ADD FOREIGN KEY (dataset) REFERENCES dataset(id);
ALTER TABLE `dataset_files` ADD FOREIGN KEY (users) REFERENCES users(id);

ALTER TABLE `dataset_files` ADD FULLTEXT(`name_sk`);
ALTER TABLE `dataset_files` ADD FULLTEXT(`name_en`);


create table if not exists dataset_tags (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dataset int NOT NULL,
  tags int NOT NULL
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `dataset_tags` ADD FOREIGN KEY (tags) REFERENCES tags(id);
ALTER TABLE `dataset_tags` ADD FOREIGN KEY (dataset) REFERENCES dataset(id);


create table if not exists tags (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name_sk varchar(250) NOT NULL,
  name_en varchar(250) NOT NULL
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `tags` ADD FULLTEXT(`name_sk`);
ALTER TABLE `tags` ADD FULLTEXT(`name_en`);

create table if not exists authors (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name_sk varchar(250) NOT NULL,
  name_en varchar(250) NOT NULL
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `authors` ADD FULLTEXT(`name_sk`);
ALTER TABLE `authors` ADD FULLTEXT(`name_en`);

create table if not exists banner_stats (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  day varchar(20),
  count int
);


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

