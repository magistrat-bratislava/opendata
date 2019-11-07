<?php

namespace App\AdminModule\Presenters;

use Nette;

abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /** @inject @var Nette\Database\Context */
    public $db;
    public $userData;

    public function beforeRender()
    {
        parent::beforeRender();

        $this->template->userdata = $this->userData;
        $this->template->menuItems = [
            [
                'name' => 'Dashboard',
                'link' => 'Dashboard:',
                'icon' => 'fa fa-th',
            ],
            [
                'name' => 'Kategórie',
                'link' => 'Category:default',
                'icon' => 'fa fa-bars',
                'role' => 'interface',
            ],
            [
                'name' => 'Datasety',
                'link' => 'Dataset:default',
                'icon' => 'fa fa-database',
                'role' => 'interface',
            ],
            [
                'name' => 'Autori',
                'link' => 'Authors:default',
                'icon' => 'fa fa-pen-fancy',
                'role' => 'interface',
            ],
            [
                'name' => 'Značky',
                'link' => 'Tags:default',
                'icon' => 'fa fa-tag',
                'role' => 'interface',
            ],
            [
                'name' => 'Užívatelia',
                'link' => 'User:default',
                'icon' => 'fa fa-users',
                'role' => 'admin',
            ],
            [
                'name' => 'Odhlásenie',
                'link' => 'Dashboard:signout',
                'icon' => 'fa fa-sign-out-alt',
            ],
        ];
    }

    protected function startup()
    {
        parent::startup();
        $this->userData = $this->db->table('users')->get($this->getUser()->id);

        if ($this->userData) {

            if ($this->userData->blocked) {
                $this->getUser()->logout();
                $this->redirect('Homepage:blocked');
            }
        } else
            $this->redirect('Homepage:');
    }
}