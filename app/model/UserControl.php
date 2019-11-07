<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;
use Nette\Security\Passwords;

class UserControl
{
    use Nette\SmartObject;

    private $db;

    private $roles;

    public function __construct(Context $database)
    {
        $this->db = $database;

        $this->roles = ['user', 'admin'];
    }

    public function getAll()
    {
        return $this->db->table('users')->fetchAll();
    }

    public function get($id)
    {
        $user = $this->db->table('users')->get($id);

        if (!$user)
            throw new \Exception('Užívateľ neexistuje.');

        return $user;
    }

    public function create($name, $username, $password, $email)
    {
        return $this->db->table('users')->insert([
            'name' => $name,
            'username' => $username,
            'password' => Passwords::hash($password),
            'email' => $email,
            'role' => 'user'
        ]);
    }

    public function edit($id, $name, $username, $email, $role, $password = null, $new_password = null)
    {
        $user = $this->get($id);

        if (!in_array($role, $this->roles))
            throw new \Exception('Rola \''.$role.'\' neexistuje.');

        if ($password !== null) {
            if (!Passwords::verify($password, $user->password))
                throw new \Exception('Heslo je nesprávne.');

            if ($new_password !== null) {

                $user->update([
                    'name' => $name,
                    'username' => $username,
                    'password' => Passwords::hash($new_password),
                    'email' => $email,
                    'role' => $role,
                ]);
            } else {
                $user->update([
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'role' => $role,
                ]);
            }
        } else
            throw new \Exception('Musíte zadať heslo.');
    }

    public function editData($id, $name, $username, $email, $role)
    {
        $user = $this->get($id);

        if (!in_array($role, $this->roles))
            throw new \Exception('Rola \''.$role.'\' neexistuje.');

        $user->update([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'role' => $role,
        ]);
    }

    public function isPasswordCorrect($id, $password)
    {
        $user = $this->get($id);

        if (Passwords::hash($password) !== $user->password)
            return false;

        return true;
    }

    public function delete($id)
    {
        $user = $this->get($id);

        $user->delete();
    }

    public function block($id)
    {
        $user = $this->get($id);

        $block = 1;

        if ($user->blocked)
            $block = 0;

        $user->update([
            'blocked' => $block
        ]);

        if ($block)
            return true;

        return false;
    }

}