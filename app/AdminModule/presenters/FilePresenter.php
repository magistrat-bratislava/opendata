<?php

namespace App\AdminModule\Presenters;

use App\Model\CategoryControl;
use App\Model\DatasetControl;
use App\Model\FileControl;
use App\Model\TagsControl;
use App\Model\UserControl;
use Nette;
use App\Components\Forms\BootstrapForm;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;

final class FilePresenter extends BasePresenter
{
    public $db;
    protected $file;
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

    public function __construct(Nette\Database\Context $db, FileControl $fileControl, DatasetControl $datasetControl, CategoryControl $categoryControl, TagsControl $tagsControl, BootstrapForm $BootstrapForm)
    {
        $this->db = $db;
        $this->file = $fileControl;
        $this->dataset = $datasetControl;
        $this->category = $categoryControl;
        $this->tag = $tagsControl;
        $this->BootstrapForm = $BootstrapForm;
    }

    public function renderDefault()
    {
        $this->redirect('Homepage:');
    }

    public function renderShow($id)
    {
        if (!$this->dataset->exists($id))
            $this->redirect('Homepage:');

        $this->template->dataset = $this->dataset->get($id);
        $this->template->files = $this->file->getByDataset($id);
    }

    public function actionAdd($id)
    {
        if (!$this->dataset->exists($id))
            $this->redirect('Homepage:');

        $this->template->dataset = $this->dataset->get($id);
    }

    protected function createComponentAddForm()
    {
        $form = $this->BootstrapForm->create();

        $form->addText('name')->setRequired(true);
        $form->addUpload('file', 'File')
            ->setRequired(true) // optional
            ->addRule(Form::MAX_FILE_SIZE, 'Maximálna veľkosť súboru je 550 MB.', 550 * 1024 * 1024 /* B */);

        $form->onSuccess[] = [$this, 'AddFormSucceeded'];

        return $form;
    }

    public function AddFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->file->create($values->name, $values->file, $this->getUser()->id, $this->template->dataset->id);

            $this->flashMessage('Súbor bol úspešne nahraný.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
            return false;
        }

        $this->redirect('File:show', $this->template->dataset->id);
    }

    public function actionEdit($id)
    {
        try {
            $this->record = $this->file->get($id);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        if (!$this->record)
            $this->redirect('Dataset:');

        $this->template->dataset = $this->dataset->get($this->record->dataset);

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
            $this->file->edit($this->record->id, $values->name);

            $this->flashMessage('Informácie boli úspešne upravené.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }
    }

    public function actionDelete($id)
    {
        $file = $this->file->get($id);

        try {
            $this->file->delete($id);
            $this->flashMessage('Súbor bol vymazaný.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('File:show', $file->dataset);
    }

    public function actionDownload($id)
    {
        $file = $this->file->get($id);

        try {
            $this->file->download($id);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('File:show', $file->dataset);
    }

    public function actionHide($id)
    {
        $file = $this->file->get($id);

        try {
            if ($this->file->hide($id))
                $this->flashMessage('Súbor je skrytý.', 'success');
            else
                $this->flashMessage('Súbor je zobrazený.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('File:show', $file->dataset);
    }
}
