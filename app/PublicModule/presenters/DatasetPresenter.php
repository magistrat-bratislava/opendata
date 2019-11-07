<?php

namespace App\PublicModule\Presenters;

use App\Model\CategoryControl;
use App\Model\DatasetControl;
use App\Model\FileControl;
use Nette;
use App\Components\Forms\BootstrapForm;
use Nette\Application\UI\Form;

final class DatasetPresenter extends BasePresenter
{
    public $httpRequest;
    public $BootstrapForm;
    public $db;
    public $file;
    public $dataset;
    public $category;

    public function __construct(Nette\Http\Request $httpRequest, BootstrapForm $BootstrapForm, Nette\Database\Context $db, DatasetControl $datasetControl, FileControl $fileControl, CategoryControl $categoryControl)
    {
        $this->httpRequest = $httpRequest;
        $this->BootstrapForm = $BootstrapForm;
        $this->db = $db;
        $this->file = $fileControl;
        $this->dataset = $datasetControl;
        $this->category = $categoryControl;
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
        $this->template->activeCategoryName = $category->name;

        //$this->template->datasets = $this->dataset->getByCategory($category->id);

        $this->template->datasets_count = count($this->db->query('
            select id
            from dataset
            where category = ? and hidden = 0
        ', [$category->id])->fetchAll());

        $this->template->max_pages = ceil($this->template->datasets_count / 10);

        if ($this->template->actual_page > $this->template->max_pages && $this->template->datasets_count > 0)
            return $this->redirect('Dataset:show');

        $this->template->datasets = $this->db->query('
            select d.*, a.name aname, 
                (SELECT GROUP_CONCAT(df.file_type SEPARATOR \',\')
                FROM dataset_files as df
                WHERE df.dataset = d.id) as files
            from dataset as d
            left join authors as a
                on a.id = d.authors
            where d.category = ? and d.hidden = 0
            group by d.id
            order by d.created_at desc
            limit ?,10
        ', $category->id, ($this->template->actual_page-1)*10)->fetchAll();

        $this->template->category_slug = $id;
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
        $this->template->activeCategoryName = $category->name;

        $this->template->tags = $this->dataset->getTagsName($this->template->d->id);
        $this->template->files = $this->file->getByDataset($this->template->d->id, true);
    }

    public function renderDownload($id)
    {
        $file = $this->file->get($id);

        $dataset = $this->dataset->get($file->dataset);

        if ($dataset->hidden == 1 || $file->hidden == 1)
            return $this->redirect('Homepage:');

        $dataset->update([
            'downloaded' => $dataset->downloaded + 1
        ]);

        try {
            $this->file->download($id);
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
    }

    public function SearchFormSucceeded(Form $form, \stdClass $values)
    {
        try {
            $this->template->search = $values->search;
            $this->template->search_count = $this->dataset->search_count($values->search);
            $this->template->max_search = ceil($this->template->search_count / 10);
            $this->template->datasets = $this->dataset->search($values->search, 0);
        }
        catch (\Exception $e) {
            $this->flashMessage($e->getMessage(), 'error');
            return false;
        }
    }

    public function actionNextCategory()
    {
        if (!$this->httpRequest->isAjax())
            die('invalid');

        $category = $this->httpRequest->getPost('cat');
        $actual_page = $this->httpRequest->getPost('page');

        if (empty($category) || empty($actual_page))
            die('invalid');

        if (!is_numeric($actual_page) || $actual_page < 1)
            die('invalid');

        $category = $this->category->get($category);

        if (!$category)
            die('invalid');

        $this->template->datasets_count = count($this->db->query('
            select id
            from dataset
            where category = ? and hidden = 0
        ', [$category->id])->fetchAll());

        $this->template->max_pages = ceil($this->template->datasets_count / 10);
        $this->template->actual_page = $actual_page+1;

        if ($this->template->actual_page > $this->template->max_pages && $this->template->datasets_count > 0)
            die('invalid');

        $this->template->datasets = $this->db->query('
            select d.*, a.name aname, 
                (SELECT GROUP_CONCAT(df.file_type SEPARATOR \',\')
                FROM dataset_files as df
                WHERE df.dataset = d.id) as files
            from dataset as d
            left join authors as a
                on a.id = d.authors
            where d.category = ? and d.hidden = 0
            group by d.id
            order by d.created_at desc
            limit ?,10
        ', $category->id, ($this->template->actual_page-1)*10)->fetchAll();

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
}
