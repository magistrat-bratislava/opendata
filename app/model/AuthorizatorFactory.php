<?php

namespace App\Model;

use Nette;

class AuthorizatorFactory
{

    public static function create() : Nette\Security\Permission
    {
        $acl = new Nette\Security\Permission;

        $acl->addRole('guest');
        $acl->addRole('user', 'guest');
        $acl->addRole('admin', 'user');

        $acl->addResource('interface');
        $acl->addResource('admin');

        $acl->deny('guest', 'interface');
        $acl->allow('user', 'interface');

        $acl->deny('guest','admin');
        $acl->deny('user','admin');
        $acl->allow('admin', 'admin');

        return $acl;
    }
}