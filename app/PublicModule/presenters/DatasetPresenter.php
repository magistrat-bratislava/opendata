<?php

namespace App\PublicModule\Presenters;

use App\Model\CategoryControl;
use App\Model\DatasetControl;
use App\Model\FileControl;
use App\Model\OnlinedataControl;
use Nette;
use App\Components\Forms\ProtectedForm;
use Nette\Application\UI\Form;

final class DatasetPresenter extends BasePresenter
{
    public $httpRequest;
    public $BootstrapForm;
    public $db;
    public $file;
    public $dataset;
    public $category;
    public $onlinedata;

    public function __construct(Nette\Http\Request $httpRequest, ProtectedForm $BootstrapForm, Nette\Database\Context $db,
                                DatasetControl $datasetControl, FileControl $fileControl, CategoryControl $categoryControl,
                                OnlinedataControl $onlinedataControl)
    {
        $this->httpRequest = $httpRequest;
        $this->BootstrapForm = $BootstrapForm;
        $this->db = $db;
        $this->file = $fileControl;
        $this->dataset = $datasetControl;
        $this->category = $categoryControl;
        $this->onlinedata = $onlinedataControl;
    }

    public function renderDefault()
    {
        $this->redirect('Dataset:Category', 'doprava');
    }

    public function renderCategory($id, $page = 1)
    {
        if (!$this->category->existSlug($id))
            return $this->redirect('Homepage:');

        if (!is_numeric($page) || $page < 1)
            return $this->redirect('Dataset:show');

        $this->template->actual_page = $page;
        $category = $this->category->getSlug($id);
        $this->template->activeCategory = $category->id;
        $this->template->activeCategoryName = $category->{$this->name_col};

        $this->template->datasets_count = $this->db->table('dataset')
            ->where('category', $category->id)
            ->where('hidden = 0')
            ->count();

        $this->template->max_pages = ceil($this->template->datasets_count / 10);

        if ($this->template->actual_page > $this->template->max_pages && $this->template->datasets_count > 0)
            return $this->redirect('Dataset:show');

        if (!isset($this->template->order))
            $this->template->order = 1;

        $order = 'changed_at DESC';

        switch ($this->template->order)
        {
            case 2: $order = $this->name_col; break;
            case 3: $order = 'downloaded DESC'; break;
        }

        $this->template->datasets = $this->db->table('dataset')
            ->where('category', $category->id)
            ->where('hidden = 0')
            ->group('id')
            ->order($order)
            ->limit(10, ($this->template->actual_page-1)*10)
            ->fetchAll();

        $this->template->category_slug = $id;

        $this->template->powerbi_link = false;
        $this->template->map_link = false;
        $this->template->onlinedata_link = false;
    }

    public function renderShow($id)
    {
        if (!$this->dataset->existSlug($id))
            return $this->redirect('Homepage:');


        $this->template->d = $this->dataset->getTables($id);

        if ($this->template->d->hidden == 1)
            return $this->redirect('Homepage:');

        $category = $this->category->get($this->template->d->category);
        $this->template->activeCategory = $category->id;
        $this->template->activeCategoryName = $category->{$this->name_col};

        $this->template->tags = $this->dataset->getTagsName($this->template->d->id);
        $this->template->files = $this->file->getByDataset($this->template->d->id, true);

        if ($this->template->d->onlinedata > 0)
            $this->template->onlinedata = $this->onlinedata->loadMonths($this->template->d->onlinedata);
    }

    public function renderDownload($id, $page)
    {
        $slug = $id;
        $id = $page;

        if (!$this->dataset->existSlug($slug))
            return $this->redirect('Homepage:');

        try {
            $dataset = $this->dataset->getTables($slug);
            $file = $this->file->getSlug($dataset->id, $id);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        if (!$file)
            return $this->redirect('Homepage:');

        if ($dataset->hidden == 1 || $file->hidden == 1)
            return $this->redirect('Homepage:');

        $dataset->update([
            'downloaded' => $dataset->downloaded + 1
        ]);

        try {
            $this->file->download($file->id, $id);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
        }

        $this->redirect('Dataset:show', $file->dataset);
    }

    public function renderInsight($id)
    {
        $file = $this->file->get($id);
        $dataset = $this->dataset->get($file->dataset);

        if ($dataset->hidden == 1 || $file->hidden == 1 || !in_array($file->file_type, $this->file->accepted_file_types))
            return $this->redirect('Homepage:');

        try {
            $this->template->content = $this->file->insight($id);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
            $this->redirect('Homepage:');
        }
    }

    public function renderSearch()
    {
        if (!$this->httpRequest->isMethod('POST')) {
            $this->template->search = '';
            $this->template->search_count = 0;
            $this->template->max_search = 0;
            $this->template->datasets = [];
        }
    }

    public function SearchFormSucceeded(Form $form, \stdClass $values)
    {
        $this->template->search = $values->search;
        $this->template->search_count = $this->dataset->search_count($values->search);
        $this->template->max_search = ceil($this->template->search_count / 10);
        $this->template->datasets = $this->dataset->search($values->search, 0);

        $this['searchExtendedForm']->setDefaults(['search' => $values->search]);
//        try {
//            $this->template->search = $values->search;
//            $this->template->search_count = $this->dataset->search_count($values->search);
//            $this->template->max_search = ceil($this->template->search_count / 10);
//            $this->template->datasets = $this->dataset->search($values->search, 0);
//
//            $this['searchExtendedForm']->setDefaults(['search' => $values->search]);
//        }
//        catch (\Exception $e) {
//            $this->flashMessage($e->getMessage(), 'error');
//            return false;
//        }
    }

    public function renderExtend()
    {
        if (!$this->httpRequest->isMethod('POST')) {
            $this->template->search = '';
            $this->template->search_count = 0;
            $this->template->max_search = 0;
            $this->template->datasets = [];
        }
    }

    public function SearchExtendedFormSucceeded(Form $form, \stdClass $values)
    {
        $page = $this->httpRequest->getPost('page');
        $search = $this->dataset->searchExtend($values, $page);

        $this->template->search = $values;
        $this->template->search = '';
        $this->template->search_count = $search['count'];
        $this->template->max_search = ceil($this->template->search_count / 10);
        $this->template->datasets = $search['data'];

//        try {
//            $page = $this->httpRequest->getPost('page');
//            $search = $this->dataset->searchExtend($values, $page);
//
//            $this->template->search = $values;
//            $this->template->search = '';
//            $this->template->search_count = $search['count'];
//            $this->template->max_search = ceil($this->template->search_count / 10);
//            $this->template->datasets = $search['data'];
//        }
//        catch (\Exception $e) {
//            $this->flashMessage($e->getMessage(), 'error');
//            return false;
//        }
    }

    public function actionNextCategory()
    {
        if (!$this->httpRequest->isAjax())
            die('invalid');

        $category = $this->httpRequest->getPost('cat');
        $actual_page = $this->httpRequest->getPost('page');
        $ord = $this->httpRequest->getPost('order');

        if (empty($category) || empty($actual_page))
            die('invalid');

        if (!is_numeric($actual_page) || $actual_page < 1)
            die('invalid');

        if (!is_numeric($ord) || $ord < 1 || $ord > 3)
            die('invalid');

        $category = $this->category->get($category);

        if (!$category)
            die('invalid');

        $order = 'changed_at DESC';

        switch ($ord)
        {
            case 2: $order = 'name_'.$this->locale; break;
            case 3: $order = 'downloaded DESC'; break;
        }

        $this->template->datasets_count = $this->db->table('dataset')
            ->where('category', $category->id)
            ->where('hidden = 0')
            ->count();

        $this->template->max_pages = ceil($this->template->datasets_count / 10);
        $this->template->actual_page = $actual_page+1;

        if ($this->template->actual_page > $this->template->max_pages && $this->template->datasets_count > 0)
            die('invalid');

        $this->template->datasets = $this->db->table('dataset')
            ->where('category', $category->id)
            ->where('hidden = 0')
            ->group('id')
            ->order($order)
            ->limit(10, ($this->template->actual_page-1)*10)
            ->fetchAll();

        if (!$this->template->datasets)
            die('invalid');

    }

    public function actionNextSearch()
    {
        if (!$this->httpRequest->isAjax())
            die('invalid');

        $search = $this->httpRequest->getPost('search');
        $actual_page = $this->httpRequest->getPost('page');

        if (empty($search) || empty($actual_page))
            die('invalid');

        if (!is_numeric($actual_page) || $actual_page < 1)
            die('invalid');

        $this->template->search_count = $this->dataset->search_count($search);

        $this->template->max_pages = ceil($this->template->search_count / 10);
        $this->template->actual_page = $actual_page+1;

        if ($this->template->actual_page > $this->template->max_pages && $this->template->search_count > 0)
            die('invalid');

        $this->template->datasets = $this->dataset->search($search, $actual_page);

        if (!$this->template->datasets)
            die('invalid');
    }

    public function actionNextSearchExtend()
    {
        if (!$this->httpRequest->isAjax())
            die('invalid');

    }

    protected function createComponentOrderForm()
    {
        $form = new Form;

        $form->addSelect(
            'order',
            '',
            [
                '1' => $this->translator->translate('ui.order_date'),
                '2' => $this->translator->translate('ui.order_name'),
                '3' => $this->translator->translate('ui.order_popularity')
            ]
        );

        $form->onSuccess[] = [$this, 'OrderFormSucceeded'];

        return $form;
    }

    public function OrderFormSucceeded(Form $form, \stdClass $values)
    {
        $this->template->order = $values->order;
    }
}
