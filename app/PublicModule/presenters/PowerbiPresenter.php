<?php

namespace App\PublicModule\Presenters;

use App\Model\CategoryControl;
use App\Model\DatasetControl;
use App\Model\FileControl;
use Nette;
use App\Components\Forms\ProtectedForm;
use Nette\Application\UI\Form;

final class PowerbiPresenter extends BasePresenter
{
    public $httpRequest;
    public $BootstrapForm;
    public $db;
    public $file;
    public $dataset;
    public $category;

    public function __construct(Nette\Http\Request $httpRequest, ProtectedForm $BootstrapForm, Nette\Database\Context $db, DatasetControl $datasetControl, FileControl $fileControl, CategoryControl $categoryControl)
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
        $this->redirect('Powerbi:Category', 'doprava');
    }

    public function renderCategory($id, $page = 1)
    {
        if (!$this->category->existSlug($id))
            return $this->redirect('Homepage:');

        if (!is_numeric($page) || $page < 1)
            return $this->redirect('Homepage:');

        $this->template->actual_page = $page;

        $category = $this->category->getSlug($id);

        $this->template->activeCategory = $category->id;
        $this->template->activeCategoryName = $category->{$this->name_col};

        $this->template->datasets_count = $this->db->table('dataset')
            ->where('dataset.category', $category->id)
            ->where('dataset.hidden = 0')
            ->where(':dataset_files.powerbi IS NOT NULL OR dataset.powerbi IS NOT NULL')
            ->count();

        $this->template->max_pages = ceil($this->template->datasets_count / 10);

        if ($this->template->actual_page > $this->template->max_pages && $this->template->datasets_count > 0)
            return $this->redirect('Homepage:');

        if (!isset($this->template->order))
            $this->template->order = 1;

        $order = 'changed_at DESC';

        switch ($this->template->order)
        {
            case 2: $order = 'name_'.$this->locale; break;
            case 3: $order = 'downloaded DESC'; break;
        }

        $this->template->datasets = $this->db->table('dataset')
            ->where('dataset.category', $category->id)
            ->where('dataset.hidden = 0')
            ->where(':dataset_files.powerbi IS NOT NULL OR dataset.powerbi IS NOT NULL')
            ->group('dataset.id')
            ->order($order)
            ->limit(10, ($this->template->actual_page-1)*10)
            ->fetchAll();

        $this->template->category_slug = $id;
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
            ->where('dataset.category', $category->id)
            ->where('dataset.hidden = 0')
            ->where(':dataset_files.powerbi IS NOT NULL OR dataset.powerbi IS NOT NULL')
            ->count();

        $this->template->max_pages = ceil($this->template->datasets_count / 10);
        $this->template->actual_page = $actual_page+1;

        if ($this->template->actual_page > $this->template->max_pages && $this->template->datasets_count > 0)
            die('invalid');

        $this->template->datasets = $this->db->table('dataset')
            ->where('dataset.category', $category->id)
            ->where('dataset.hidden = 0')
            ->where(':dataset_files.powerbi IS NOT NULL OR dataset.powerbi IS NOT NULL')
            ->group('id')
            ->order($order)
            ->limit(10, ($this->template->actual_page-1)*10)
            ->fetchAll();

        if (!$this->template->datasets)
            die('invalid');
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

        $pbi = false;

        foreach ($this->template->d->related('dataset_files') as $f) {
            if (!empty($f->powerbi))
                $pbi = true;
        }

        if (!$pbi && empty($this->template->d->powerbi))
            return $this->redirect('Homepage:');
    }

    public function renderDataset($id)
    {
        if (!$this->dataset->exists($id))
            return $this->redirect('Homepage:');

        $this->template->d = $this->dataset->get($id);

        if ($this->template->d->hidden == 1)
            return $this->redirect('Homepage:');
    }

    public function renderFile($id)
    {
        if (!$this->file->exists($id))
            return $this->redirect('Homepage:');

        $this->template->f = $this->file->get($id);

        if ($this->template->f->hidden == 1)
            return $this->redirect('Homepage:');
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
