<?php

/*

CREATE TABLE `users` (
    `id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `username` varchar(250) NOT NULL,
    `email` varchar(250) NOT NULL,
    `password` varchar(100) NOT NULL,
    `role` varchar(100) NOT NULL DEFAULT 'guest',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8;

ALTER TABLE `users` ADD UNIQUE(`username`);

*/

namespace App\Model;

use Nette;
use Nette\Database\Context;
use Nette\Security;

class UserAuthenticator implements Nette\Security\IAuthenticator
{
    use Nette\SmartObject;

    private $db;
    private $pass;

    public function __construct(Context $database, Security\Passwords $passwords)
    {
        $this->db = $database;
        $this->pass = $passwords;
    }

    public function authenticate(array $credentials) : Nette\Security\IIdentity
    {
        list($username, $password) = $credentials;

        $row = $this->db->table('users')->where('username', $username)->fetch();

        if (!$row) {
            throw new Security\AuthenticationException('Neznámy užívateľ.');
        }

        if (!$this->pass->verify($password, $row->password)) {
            throw new Security\AuthenticationException('Neznámy užívateľ.');
        }

        return new Security\Identity($row->id, $row->role, ['username' => $row->username]);
    }
}