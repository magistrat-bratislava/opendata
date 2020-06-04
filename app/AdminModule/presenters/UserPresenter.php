<?php

namespace App\AdminModule\Presenters;

use App\Model\UserControl;
use Nette;
use App\Components\Forms\ProtectedForm;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;

final class UserPresenter extends BasePresenter
{
    protected $user;
    private $record;
    public $BootstrapForm;

    protected function startup()
    {
        parent::startup();

        if (!$this->getUser()->isAllowed('admin')) {
            throw new Nette\Application\ForbiddenRequestException;
        }
    }

    public function __construct(UserControl $user, ProtectedForm $BootstrapForm)
    {
        $this->user = $user;
        $this->BootstrapForm = $BootstrapForm;
    }

    public function renderDefault()
    {
        $this->template->users = $this->user->getAll();
    }

    public function actionBlock($id)
    {
        if ($this->getUser()->id == $id) {
            $this->flashMessage('Nemôžete zablokovať samého seba.', 'error');
            $this->redirect('User:');
            return;
        }

        try {
            if ($this->user->block($id))
                $this->flashMessage('Užívateľ bol zablokovaný.', 'success');
            else
                $this->flashMessage('Užívateľ bol odblokovaný.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('User:');
    }

    protected function createComponentAddForm()
    {
        $form = $this->form->create();

        $form->addText('name')->setRequired(true);
        $form->addText('username')->setRequired(true);
        $form->addEmail('email')->setRequired(true);
        $form->addPassword('password')->setRequired(true);

        $form->onSuccess[] = [$this, 'AddFormSucceeded'];

        return $form;
    }

    public function AddFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->user->create($values->name, $values->username, $values->password, $values->email);

            $this->flashMessage('Užívateľ bol úspešne vytvorený.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
            return false;
        }

        $this->redirect('User:');
    }

    public function actionEdit($id)
    {
        try {
            $this->record = $this->user->get($id);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        if (!$this->record)
            $this->redirect('User:');

        $this['editForm']->setDefaults($this->record->toArray());
    }

    protected function createComponentEditForm()
    {
        $form = $this->form->create();

        $form->addText('name')->setRequired(true);
        $form->addText('username')->setRequired(true);
        $form->addEmail('email')->setRequired(true);
        $form->addSelect('role', 'role', ['user' => 'User', 'global' => 'Global', 'admin' => 'Admin'])->setRequired(true);

        $form->onSuccess[] = [$this, 'EditFormSucceeded'];

        return $form;
    }

    public function EditFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->user->editData($this->record->id, $values->name, $values->username, $values->email, $values->role);

            $this->flashMessage('Užívateľ bol upravený.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }
    }

    public function actionDelete($id)
    {
        if ($this->getUser()->id == $id) {
            $this->flashMessage('Nemôžete vymazať samého seba!', 'error');
            $this->redirect('User:');
            return;
        }

        try {
            $this->user->delete($id);
            $this->flashMessage('Užívateľ bol vymazaný.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('User:');
    }
}