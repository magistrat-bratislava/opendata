<?php

namespace App\AdminModule\Presenters;

use Nette;
use App\Components\Forms\ProtectedForm;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;

final class HomepagePresenter extends \Nette\Application\UI\Presenter
{
    /** @inject @var \App\Components\Forms\ProtectedForm */
    public $form;
    
    public $db;

    public function __construct(Nette\Database\Context $db)
    {
        $this->db = $db;
    }

    protected function startup()
    {
        parent::startup();
        if ($this->getUser()->isAllowed('interface')) {
            $this->redirect('Dashboard:');
        }
    }

    public function renderDefault()
    {

    }

    protected function createComponentLoginForm()
    {
        $form = $this->form->create();

        $form->addText('username')->setRequired(true)->setOption('right-addon', 'Username');
        $form->addPassword('password')->setRequired(true)->setOption('right-addon', 'Password');
        $form->addCheckbox('remember', 'Zapamatat prihlasenie?');

        $form->onSuccess[] = [$this, 'LoginFormSucceeded'];

        return $form;
    }

    public function LoginFormSucceeded(Form $form, \stdClass $values)
    {
        $user = $this->db->table('users')->where('username = ?',$values->username)->fetch();

        if (!$user) {
            $this->flashMessage('Prihlasovacie meno alebo heslo je nesprávne.', 'error');
            return false;
        }

        try {
            $this->user->setExpiration($values->remember ? '14 days' : '20 minutes');
            $this->user->login($values->username, $values->password);

            if ($user->blocked) {
                $this->redirect('Homepage:blocked');
            }

            $this->flashMessage('Boli ste úspešne prihlásený.', 'success');
            $this->redirect('Dashboard:');

        } catch (Nette\Security\AuthenticationException $e) {
            $this->flashMessage('Prihlasovacie meno alebo heslo je nesprávne.', 'error');
        }
    }

    /*protected function createComponentRegisterForm()
    {
        $form = $this->form->create();

        $form->addText('username')->setRequired(true);
        $form->addText('email')->setRequired(true);
        $form->addPassword('password')->setRequired(true);

        $form->onSuccess[] = [$this, 'RegisterFormSucceeded'];

        return $form;
    }

    public function RegisterFormSucceeded(Form $form, \stdClass $values)
    {
        Nette\Utils\Validators::assert($values->email, 'email');
        try {
            $this->db->table('users')->insert([
                'username' => $values->username,
                'password' => Passwords::hash($values->password),
                'email' => $values->email,
                'role' => 'user'
            ]);

            $this->flashMessage('Boli ste úspešne registrovaný.','success');

            $this->redirect('Homepage:');

        } catch (Nette\Database\UniqueConstraintViolationException $e) {
            $this->flashMessage('Prihlasovacie meno už je registrované.','error');
        }
    }*/
}
