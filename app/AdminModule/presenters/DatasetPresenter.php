<?php

namespace App\AdminModule\Presenters;

use App\Model\CategoryControl;
use App\Model\DatasetControl;
use App\Model\TagsControl;
use App\Model\UserControl;
use Nette;
use App\Components\Forms\BootstrapForm;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;

final class DatasetPresenter extends BasePresenter
{
    public $db;
    protected $dataset;
    protected $category;
    protected $tag;
    private $record;
    public $BootstrapForm;

    protected function startup()
    {
        parent::startup();

        if (!$this->getUser()->isAllowed('interface')) {
            throw new Nette\Application\ForbiddenRequestException;
        }
    }

    public function __construct(Nette\Database\Context $db, DatasetControl $datasetControl, CategoryControl $categoryControl, TagsControl $tagsControl, BootstrapForm $BootstrapForm)
    {
        $this->db = $db;
        $this->dataset = $datasetControl;
        $this->category = $categoryControl;
        $this->tag = $tagsControl;
        $this->BootstrapForm = $BootstrapForm;
    }

    public function renderDefault()
    {
        $this->template->datasets = $this->dataset->getAll();
    }

    public function actionHide($id)
    {
        try {
            $this->dataset->hide($id);
            $this->flashMessage('Dataset information was updated.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('Dataset:');
    }

    public function actionAdd()
    {
        $authors = $this->db->table('authors')->fetchAll();

        $this->template->authors = ['0' => '-- select author --'];

        foreach ($authors as $u) {
            $this->template->authors[$u->id] = $u->name;
        }

        $this->template->categories = [];
        $categories = $this->category->getAll();

        foreach ($categories as $c) {
            $this->template->categories[$c->id] = $c->name;
        }

        $this->template->tags = [];
        $tags = $this->tag->getAll();

        foreach ($tags as $t) {
            $this->template->tags[$t->id] = $t->name;
        }
    }

    protected function createComponentAddForm()
    {
        $form = $this->BootstrapForm->create();

        $form->addText('name')->setRequired(true);
        $form->addText('slug')->setRequired(true);
        $form->addTextArea('description')->setRequired(true);
        $form->addSelect('authors', 'Authors', $this->template->authors)->setRequired(true);
        $form->addText('licence')->setRequired(true);
        $form->addSelect('category', 'Category', $this->template->categories)->setRequired(true);
        $form->addMultiSelect('tags', "Tags", $this->template->tags);

        $form->onSuccess[] = [$this, 'AddFormSucceeded'];

        return $form;
    }

    public function AddFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->dataset->create($values->name, $values->slug, $values->description, $values->authors, $values->licence, $values->category, $values->tags, $this->getUser()->id);

            $this->flashMessage('Dataset bol úspešne vytvorený.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
            return false;
        }

        //$this->redirect('Dataset:');
    }

    public function actionEdit($id)
    {
        try {
            $this->record = $this->dataset->get($id);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        if (!$this->record)
            $this->redirect('Dataset:');

        $authors = $this->db->table('authors')->fetchAll();

        $this->template->authors = [];

        foreach ($authors as $u) {
            $this->template->authors[$u->id] = $u->name;
        }

        $this->template->categories = [];
        $categories = $this->category->getAll();

        foreach ($categories as $c) {
            $this->template->categories[$c->id] = $c->name;
        }

        $this->template->tags = [];
        $tags = $this->tag->getAll();

        foreach ($tags as $t) {
            $this->template->tags[$t->id] = $t->name;
        }

        $tags = $this->dataset->getTags($id);
        $selected_tags = [];

        foreach ($tags as $t) {
            $selected_tags[] = $t->tags;
        }

        $c = $this->record->toArray();
        $c['tags'] = $selected_tags;

        $this['editForm']->setDefaults($c);
    }

    protected function createComponentEditForm()
    {
        $form = $this->BootstrapForm->create();

        $form->addText('name')->setRequired(true);
        $form->addText('slug')->setRequired(true);
        $form->addTextArea('description')->setRequired(true);
        $form->addSelect('authors', 'Authors', $this->template->authors)->setRequired(true);
        $form->addText('licence')->setRequired(true);
        $form->addSelect('category', 'Category', $this->template->categories)->setRequired(true);
        $form->addMultiSelect('tags', "Tags", $this->template->tags);

        $form->onSuccess[] = [$this, 'EditFormSucceeded'];

        return $form;
    }

    public function EditFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->dataset->edit($this->record->id, $values->name, $values->slug, $values->description, $values->authors, $values->licence, $values->category, $values->tags);

            $this->flashMessage('Dataset bol úspešne upravený.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }
    }

    public function actionDelete($id)
    {
        try {
            $this->dataset->delete($id);
            $this->flashMessage('Dataset bol vymazaný.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('Dataset:');
    }
}