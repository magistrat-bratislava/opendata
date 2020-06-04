<?php

namespace App\AdminModule\Presenters;

use App\Model\CategoryControl;
use App\Model\DatasetControl;
use App\Model\TagsControl;
use App\Model\UserControl;
use Nette;
use App\Components\Forms\ProtectedForm;
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

    public function __construct(Nette\Database\Context $db, DatasetControl $datasetControl, CategoryControl $categoryControl, TagsControl $tagsControl, ProtectedForm $BootstrapForm)
    {
        $this->db = $db;
        $this->dataset = $datasetControl;
        $this->category = $categoryControl;
        $this->tag = $tagsControl;
        $this->BootstrapForm = $BootstrapForm;
    }

    public function renderDefault()
    {
        if ($this->getUser()->isAllowed('global'))
            $this->template->datasets = $this->dataset->getAll();
        else
            $this->template->datasets = $this->dataset->getByUser($this->getUser()->id);
    }

    public function actionHide($id)
    {
        try {
            if (!$this->getUser()->isAllowed('global') && $this->dataset->get($id)->users != $this->getUser()->id)
                throw new \Exception('Na túto akciu nemáte dostatočné oprávnenie.');

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
            $this->template->authors[$u->id] = $u->name_sk;
        }

        $this->template->categories = [];
        $categories = $this->category->getAll();

        foreach ($categories as $c) {
            $this->template->categories[$c->id] = $c->name_sk;
        }

        $this->template->tags = [];
        $tags = $this->tag->getAll();

        foreach ($tags as $t) {
            $this->template->tags[$t->id] = $t->name_sk;
        }
    }

    protected function createComponentAddForm()
    {
        $form = $this->form->create();

        $form->addText('name_sk')->setRequired(true);
        $form->addText('name_en')->setRequired(true);
        $form->addText('slug')->setRequired(true);
        $form->addTextArea('description_sk')->setRequired(true);
        $form->addTextArea('description_en')->setRequired(true);
        $form->addSelect('authors', 'Authors', $this->template->authors)->setRequired(true);
        $form->addText('licence')->setRequired(true);
        $form->addSelect('category', 'Category', $this->template->categories)->setRequired(true);
        $form->addMultiSelect('tags', "Tags", $this->template->tags);
        $form->addText('powerbi');
        $form->addText('map');
        $form->addText('year');
        $form->addText('district');
        $form->addSelect('onlinedata', 'OnlineData', ['0' => '-- Online Data --', '1' => 'Form', '2' => 'Locations', '3' => 'Summary']);

        $form->onSuccess[] = [$this, 'AddFormSucceeded'];

        return $form;
    }

    public function AddFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->dataset->create($values->name_sk, $values->name_en, $values->slug, $values->description_sk, $values->description_en, $values->authors, $values->licence, $values->category, $values->tags, $values->powerbi, $values->map, $values->year, $values->district, $values->onlinedata, $this->getUser()->id);

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

        if (!$this->getUser()->isAllowed('global') && $this->record->users != $this->getUser()->id) {
            $this->redirect('Dataset:');
        }

        $authors = $this->db->table('authors')->fetchAll();

        $this->template->authors = [];

        foreach ($authors as $u) {
            $this->template->authors[$u->id] = $u->name_sk;
        }

        $this->template->categories = [];
        $categories = $this->category->getAll();

        foreach ($categories as $c) {
            $this->template->categories[$c->id] = $c->name_sk;
        }

        $this->template->tags = [];
        $tags = $this->tag->getAll();

        foreach ($tags as $t) {
            $this->template->tags[$t->id] = $t->name_sk;
        }

        $tags = $this->dataset->getTags($id);
        $selected_tags = [];

        foreach ($tags as $t) {
            $selected_tags[] = $t->tags;
        }

        $c = $this->record->toArray();
        $c['tags'] = $selected_tags;

        $this['editForm']->setDefaults($c);

        $this->template->dataset = $this->record;
    }

    protected function createComponentEditForm()
    {
        $form = $this->form->create();

        $form->addText('name_sk')->setRequired(true);
        $form->addText('name_en')->setRequired(true);
        $form->addText('slug')->setRequired(true);
        $form->addTextArea('description_sk')->setRequired(true);
        $form->addTextArea('description_en')->setRequired(true);
        $form->addSelect('authors', 'Authors', $this->template->authors)->setRequired(true);
        $form->addText('licence')->setRequired(true);
        $form->addText('year');
        $form->addText('district');
        $form->addSelect('category', 'Category', $this->template->categories)->setRequired(true);
        $form->addMultiSelect('tags', "Tags", $this->template->tags);
        $form->addText('powerbi');
        $form->addText('map');
        $form->addSelect('onlinedata', 'OnlineData', ['0' => '-- Online Data --', '1' => 'Form', '2' => 'Locations', '3' => 'Summary']);

        $form->onSuccess[] = [$this, 'EditFormSucceeded'];

        return $form;
    }

    public function EditFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->dataset->edit($this->record->id, $values->name_sk, $values->name_en, $values->slug, $values->description_sk, $values->description_en, $values->authors, $values->licence, $values->category, $values->tags, $values->powerbi, $values->map, $values->year, $values->district, $values->onlinedata);

            $this->flashMessage('Dataset bol úspešne upravený.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }
    }

    public function actionDelete($id)
    {
        try {
            if (!$this->getUser()->isAllowed('global') && $this->dataset->get($id)->users != $this->getUser()->id)
                throw new \Exception('Na túto akciu nemáte dostatočné oprávnenie.');

            $this->dataset->delete($id);
            $this->flashMessage('Dataset bol vymazaný.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('Dataset:');
    }

    /*public function actionMakefiles()
    {
        if (!$this->getUser()->isAllowed('admin')) {
            throw new Nette\Application\ForbiddenRequestException;
        }

        $ds = $this->db->table('dataset')->fetchAll();

        foreach ($ds as $d) {
            $files = $this->db->table('dataset_files')->where('dataset', $d->id)->order('created_at')->fetchAll();

            if (!$files)
                continue;

            $i = 1;

            foreach ($files as $f) {
                $f->update([
                    'ord' => $i
                ]);

                $i++;
            }
        }

        die;
    }*/
}