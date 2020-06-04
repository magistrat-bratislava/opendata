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
        $acl->addRole('global', 'user');
        $acl->addRole('admin', 'global');

        $acl->addResource('interface');
        $acl->addResource('global');
        $acl->addResource('admin');

        $acl->deny('guest', 'interface');
        $acl->allow('user', 'interface');

        $acl->deny('guest','global');
        $acl->deny('user','global');
        $acl->allow('global', 'global');

        $acl->deny('guest','admin');
        $acl->deny('user','admin');
        $acl->deny('global','admin');
        $acl->allow('admin', 'admin');

        return $acl;
    }
}