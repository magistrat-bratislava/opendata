<?php

namespace App\AdminModule\Presenters;

use App\Model\AuthorsControl;
use App\Model\CategoryControl;
use Nette;
use App\Components\Forms\ProtectedForm;
use Nette\Application\UI\Form;

final class CategoryPresenter extends BasePresenter
{
    public $db;
    protected $category;
    private $record;
    public $BootstrapForm;

    protected function startup()
    {
        parent::startup();

        if (!$this->getUser()->isAllowed('global')) {
            throw new Nette\Application\ForbiddenRequestException;
        }
    }

    public function __construct(Nette\Database\Context $db, CategoryControl $categoryControl, ProtectedForm $BootstrapForm)
    {
        $this->db = $db;
        $this->category = $categoryControl;
        $this->BootstrapForm = $BootstrapForm;
    }

    public function renderDefault()
    {
        $this->template->category = $this->category->getAll();
    }

    protected function createComponentAddForm()
    {
        $form = $this->form->create();

        $form->addText('name_sk')->setRequired(true);
        $form->addText('name_en')->setRequired(true);
        $form->addText('slug')->setRequired(true);
        $form->onSuccess[] = [$this, 'AddFormSucceeded'];

        return $form;
    }

    public function AddFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->category->create($values->name_sk, $values->name_en, $values->slug);
            $this->flashMessage('Kategória bola úspešne vytvorená.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
            return false;
        }

        $this->redirect('Category:');
    }

    public function actionEdit($id)
    {
        try {
            $this->record = $this->category->get($id);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        if (!$this->record)
            $this->redirect('Category:');

        $this['editForm']->setDefaults($this->record->toArray());
    }

    protected function createComponentEditForm()
    {
        $form = $this->form->create();

        $form->addText('name_sk')->setRequired(true);
        $form->addText('name_en')->setRequired(true);
        $form->addText('slug')->setRequired(true);
        $form->onSuccess[] = [$this, 'EditFormSucceeded'];

        return $form;
    }

    public function EditFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->category->edit($this->record->id, $values->name_sk, $values->name_en, $values->slug);
            $this->flashMessage('Kategória bola úspešne upravená.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }
    }

    public function actionDelete($id)
    {
        try {
            $this->category->delete($id);
            $this->flashMessage('Kategória bola vymazaná.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('Category:');
    }
}