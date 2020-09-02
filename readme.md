OpenData
=================

Requirements
------------

PHP 7.2 or higher.


Installation
------------

The best way to install Web Project is using Composer. If you don't have Composer yet,
download it following [the instructions](https://doc.nette.org/composer). Then use command:

	cd path/to/install
	composer install


Make directories `temp/` and `log/` writable.


Web Server Setup
----------------

For Apache or Nginx, setup a virtual host to point to the `www/` directory of the project and you
should be ready to go.

**It is CRITICAL that whole `app/`, `log/` and `temp/` directories are not accessible directly
via a web browser. See [security warning](https://nette.org/security-warning).**


Database installation
---------------------

Create new MySQL database and user, that can access database. Make a copy of file `app/config/db.example.neon` 
and rename it to `db.neon`. Next, change database and user credentials in this file.
Import file `database.sql` into new database.


First login
-----------

Go to `yourwebsite.com/admin` and login with username `admin` and password `admin`. Please, change your password immediately (click on username).
1. create new categories
2. create new author
3. create new dataset



Notice: Composer PHP version
----------------------------
This project forces `PHP 7.2` as your PHP version for Composer packages. If you have newer version on production you should change it in `composer.json`.
```json
"config": {
	"platform": {
		"php": "7.2"
	}
}
```
