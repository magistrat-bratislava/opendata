<?php

namespace App\AdminModule\Presenters;

use App\Model\AuthorsControl;
use Nette;
use App\Components\Forms\ProtectedForm;
use Nette\Application\UI\Form;

final class AuthorsPresenter extends BasePresenter
{
    public $db;
    protected $authors;
    private $record;
    public $BootstrapForm;

    protected function startup()
    {
        parent::startup();

        if (!$this->getUser()->isAllowed('interface')) {
            throw new Nette\Application\ForbiddenRequestException;
        }
    }

    public function __construct(Nette\Database\Context $db, AuthorsControl $authorsControl, ProtectedForm $BootstrapForm)
    {
        $this->db = $db;
        $this->authors = $authorsControl;
        $this->BootstrapForm = $BootstrapForm;
    }

    public function renderDefault()
    {
        $this->template->authors = $this->authors->getAll();
    }

    protected function createComponentAddForm()
    {
        $form = $this->form->create();

        $form->addText('name_sk')->setRequired(true);
        $form->addText('name_en')->setRequired(true);
        $form->onSuccess[] = [$this, 'AddFormSucceeded'];

        return $form;
    }

    public function AddFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->authors->create($values->name_sk, $values->name_en);
            $this->flashMessage('Autor bol úspešne vytvorený.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
            return false;
        }

        $this->redirect('Authors:');
    }

    public function actionEdit($id)
    {
        try {
            if (!$this->getUser()->isAllowed('global'))
                throw new \Exception('Na túto akciu nemáte dostatočné oprávnenie.');

            $this->record = $this->authors->get($id);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        if (!$this->record)
            $this->redirect('Authors:');

        $this['editForm']->setDefaults($this->record->toArray());
    }

    protected function createComponentEditForm()
    {
        $form = $this->form->create();

        $form->addText('name_sk')->setRequired(true);
        $form->addText('name_en')->setRequired(true);
        $form->onSuccess[] = [$this, 'EditFormSucceeded'];

        return $form;
    }

    public function EditFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            if (!$this->getUser()->isAllowed('global'))
                throw new \Exception('Na túto akciu nemáte dostatočné oprávnenie.');

            $this->authors->edit($this->record->id, $values->name_sk, $values->name_en);
            $this->flashMessage('Autor bol úspešne upravený.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }
    }

    public function actionDelete($id)
    {
        try {
            if (!$this->getUser()->isAllowed('global'))
                throw new \Exception('Na túto akciu nemáte dostatočné oprávnenie.');

            $this->authors->delete($id);
            $this->flashMessage('Autor bol vymazaný.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('Authors:');
    }
}