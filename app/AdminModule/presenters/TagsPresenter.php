<?php

namespace App\AdminModule\Presenters;

use App\Model\AuthorsControl;
use App\Model\TagsControl;
use Nette;
use App\Components\Forms\BootstrapForm;
use Nette\Application\UI\Form;

final class TagsPresenter extends BasePresenter
{
    public $db;
    protected $tags;
    private $record;
    public $BootstrapForm;

    protected function startup()
    {
        parent::startup();

        if (!$this->getUser()->isAllowed('interface')) {
            throw new Nette\Application\ForbiddenRequestException;
        }
    }

    public function __construct(Nette\Database\Context $db, TagsControl $tagsControl, BootstrapForm $BootstrapForm)
    {
        $this->db = $db;
        $this->tags = $tagsControl;
        $this->BootstrapForm = $BootstrapForm;
    }

    public function renderDefault()
    {
        $this->template->tags = $this->tags->getAll();
    }

    protected function createComponentAddForm()
    {
        $form = $this->BootstrapForm->create();

        $form->addText('name')->setRequired(true);
        $form->onSuccess[] = [$this, 'AddFormSucceeded'];

        return $form;
    }

    public function AddFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->tags->create($values->name);
            $this->flashMessage('Značka bola úspešne vytvorená.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
            return false;
        }

        $this->redirect('Tags:');
    }

    public function actionEdit($id)
    {
        try {
            $this->record = $this->tags->get($id);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        if (!$this->record)
            $this->redirect('Tags:');

        $this['editForm']->setDefaults($this->record->toArray());
    }

    protected function createComponentEditForm()
    {
        $form = $this->BootstrapForm->create();

        $form->addText('name')->setRequired(true);
        $form->onSuccess[] = [$this, 'EditFormSucceeded'];

        return $form;
    }

    public function EditFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->tags->edit($this->record->id, $values->name);
            $this->flashMessage('Značka bola úspešne upravená.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }
    }

    public function actionDelete($id)
    {
        try {
            $this->tags->delete($id);
            $this->flashMessage('Značka bola vymazaná.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('Tags:');
    }
}