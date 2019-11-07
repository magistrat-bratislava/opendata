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
  name varchar(250) NOT NULL,
  slug varchar(250) NOT NULL
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `category` ADD FULLTEXT(`name`);


create table if not exists dataset (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(250) NOT NULL,
  slug varchar(250) NOT NULL,
  description text NOT NULL,
  authors int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `changed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  licence varchar(250) NOT NULL,
  category int not null,
  users int not null,
  uniq_id varchar(50) not null,
  downloaded int not null,
  hidden tinyint default '1',
  deleted tinyint default '0'
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `dataset` ADD FOREIGN KEY (category) REFERENCES category(id);
ALTER TABLE `dataset` ADD FOREIGN KEY (users) REFERENCES users(id);
ALTER TABLE `dataset` ADD FOREIGN KEY (authors) REFERENCES authors(id);

ALTER TABLE `dataset` ADD FULLTEXT(`name`);
ALTER TABLE `dataset` ADD FULLTEXT(`slug`);
ALTER TABLE `dataset` ADD FULLTEXT(`description`);
ALTER TABLE `dataset` ADD FULLTEXT(`licence`);

ALTER TABLE `dataset`
  ADD FULLTEXT INDEX `FullText`
    (`name` ASC, `slug` ASC, `description` ASC, `licence` ASC);


create table if not exists dataset_files (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dataset int NOT NULL,
  name varchar(250) NOT NULL,
  file_type varchar(50) NOT NULL,
  users int not null,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  hidden tinyint default '0'
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `dataset_files` ADD FOREIGN KEY (dataset) REFERENCES dataset(id);
ALTER TABLE `dataset_files` ADD FOREIGN KEY (users) REFERENCES users(id);

ALTER TABLE `dataset_files` ADD FULLTEXT(`name`);


create table if not exists dataset_tags (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dataset int NOT NULL,
  tags int NOT NULL
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `dataset_tags` ADD FOREIGN KEY (tags) REFERENCES tags(id);


create table if not exists tags (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(250) NOT NULL
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `tags` ADD FULLTEXT(`name`);

create table if not exists authors (
  `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name varchar(250) NOT NULL
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `authors` ADD FULLTEXT(`name`);
