<?php

namespace App\AdminModule\Presenters;

use App\Model\AuthorsControl;
use App\Model\CategoryControl;
use App\Model\DatasetControl;
use App\Model\TagsControl;
use App\Model\UserControl;
use Nette;
use App\Components\Forms\ProtectedForm;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;

final class DashboardPresenter extends BasePresenter
{
    private $category;
    private $dataset;
    private $author;
    private $tag;
    private $user;
    private $record;
    private $BootstrapForm;

    protected function startup()
    {
        parent::startup();
        if (!$this->getUser()->isAllowed('interface')) {
            throw new Nette\Application\ForbiddenRequestException;
        }
    }

    public function __construct(CategoryControl $categoryControl, DatasetControl $datasetControl, AuthorsControl $authorsControl, TagsControl $tagsControl, UserControl $user, ProtectedForm $BootstrapForm)
    {
        $this->category = $categoryControl;
        $this->dataset = $datasetControl;
        $this->author = $authorsControl;
        $this->tag = $tagsControl;
        $this->user = $user;
        $this->BootstrapForm = $BootstrapForm;
    }

    public function renderDefault()
    {

        if ($this->getUser()->isAllowed('global')) {
            $this->template->stats_files = $this->dataset->getFileCount();
            $this->template->stats_dataset = $this->dataset->getCount();

            $this->template->latest_datasets = $this->dataset->getLatest();
            $this->template->latest_files = $this->dataset->getLatestFiles();
        } else {
            $this->template->stats_files = $this->dataset->getFileCount(0, $this->getUser()->id);
            $this->template->stats_dataset = $this->dataset->getCount(false, $this->getUser()->id);

            $this->template->latest_datasets = $this->dataset->getLatest($this->getUser()->id);
            $this->template->latest_files = $this->dataset->getLatestFiles($this->getUser()->id);
        }

        $this->template->stats_authors = $this->author->getCount();
        $this->template->stats_tags = $this->tag->getCount();

        $this->template->categories_datasets = $this->category->getDatasetCount();
    }

    public function actionSignout()
    {
        $this->getUser()->logout();
        $this->flashMessage('Boli ste odhlásený.');
        $this->redirect('Homepage:');
    }

    public function actionProfile()
    {
        try {
            $this->record = $this->user->get($this->getUser()->id);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this['profileForm']->setDefaults($this->record->toArray());
    }

    protected function createComponentProfileForm()
    {
        $form = $this->form->create();

        $form->addText('name');
        $form->addText('username');
        $form->addEmail('email');
        $form->addPassword('password')->setRequired('Pass');
        $form->addPassword('new_password');

        $form->onSuccess[] = [$this, 'ProfileFormSucceeded'];

        return $form;
    }

    public function ProfileFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            if (empty($values->new_password))
                $values->new_password = null;

            $this->user->edit($this->userData->id, $values->name, $this->record->username, $values->email, $this->userData->role, $values->password, $values->new_password);

            $this->flashMessage('Profil bol úspešne upravený.', 'success');
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }
    }
}