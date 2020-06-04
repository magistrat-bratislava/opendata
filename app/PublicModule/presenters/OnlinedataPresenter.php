<?php

namespace App\PublicModule\Presenters;

use App\Model\CategoryControl;
use App\Model\DatasetControl;
use App\Model\FileControl;
use App\Model\LastOnlinedataControl;
use App\Model\OnlinedataControl;
use Nette;
use App\Components\Forms\ProtectedForm;
use Nette\Application\UI\Form;

final class OnlinedataPresenter extends BasePresenter
{
    public $httpRequest;
    public $BootstrapForm;
    public $db;
    public $file;
    public $dataset;
    public $lastonlinedata;
    public $onlinedata;
    public function __construct(Nette\Http\Request $httpRequest, ProtectedForm $BootstrapForm, Nette\Database\Context $db,
                                DatasetControl $datasetControl, FileControl $fileControl, CategoryControl $categoryControl,
                                LastOnlinedataControl $lastOnlinedataControl, OnlinedataControl $onlinedataControl)
    {
        $this->httpRequest = $httpRequest;
        $this->BootstrapForm = $BootstrapForm;
        $this->db = $db;
        $this->file = $fileControl;
        $this->dataset = $datasetControl;
        $this->category = $categoryControl;
        $this->lastonlinedata = $lastOnlinedataControl;
        $this->onlinedata = $onlinedataControl;
    }

    public function renderDefault()
    {
        $this->template->last_devices = $this->lastonlinedata->getLastDevices();
        $this->template->last_os = $this->lastonlinedata->getLastOs();
        $this->template->last_browser = $this->lastonlinedata->getLastBrowser();
        $this->template->last_lang = $this->lastonlinedata->getLastLang();

        $this->template->last_new_users = $this->lastonlinedata->getLastNewUsers();
        $this->template->last_return_users = $this->lastonlinedata->getLastReturnUsers();
        $this->template->last_uniq_users = $this->lastonlinedata->getLastUniqUsers();

        $this->template->last_user_location = $this->lastonlinedata->getLastUserLocation();

        $this->template->last_dwell = $this->lastonlinedata->getLastDwell();
        $this->template->last_max_average = $this->lastonlinedata->getLastMaxAverage();

        $this->template->actual_page = '';
        $this->template->max_pages = '';
        $this->template->activeCategory = '';

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
            ->where('dataset.onlinedata != 0')
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
            ->where('dataset.onlinedata != 0')
            ->group('dataset.id')
            ->order($order)
            ->limit(10, ($this->template->actual_page-1)*10)
            ->fetchAll();

        $this->template->category_slug = $id;

        $this->template->powerbi_link = false;
        $this->template->map_link = false;
        $this->template->onlinedata_link = true;
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
            ->where('dataset.onlinedata != 0')
            ->count();

        $this->template->max_pages = ceil($this->template->datasets_count / 10);
        $this->template->actual_page = $actual_page+1;

        if ($this->template->actual_page > $this->template->max_pages && $this->template->datasets_count > 0)
            die('invalid');

        $this->template->datasets = $this->db->table('dataset')
            ->where('dataset.category', $category->id)
            ->where('dataset.hidden = 0')
            ->where('dataset.onlinedata != 0')
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
            return $this->redirect('Onlinedata:');

        $this->template->d = $this->dataset->getTables($id);

        if ($this->template->d->hidden == 1)
            return $this->redirect('Onlinedata:');

        if ($this->template->d->onlinedata == 0)
            return $this->redirect('Onlinedata:');

        $category = $this->category->get($this->template->d->category);
        $this->template->activeCategory = $category->id;
        $this->template->activeCategoryName = $category->{$this->name_col};

        $this->template->tags = $this->dataset->getTagsName($this->template->d->id);
        $this->template->files = $this->file->getByDataset($this->template->d->id, true);

        $this->template->onlinedata = $this->onlinedata->loadMonths($this->template->d->onlinedata);
    }

    public function actionDownload($id, $page)
    {
        if (!is_numeric($page) || $page < 1 || $page > 3)
            return $this->redirect('Onlinedata:');

        if (!$this->onlinedata->download($id, $page))
            return $this->redirect('Onlinedata:');
    }

    public function renderForm($id, $page)
    {
        if (!$this->dataset->existSlug($id))
            return $this->redirect('Onlinedata:');

        $this->template->d = $this->dataset->getTables($id);

        if ($this->template->d->hidden == 1)
            return $this->redirect('Onlinedata:');

        if ($this->template->d->onlinedata == 0)
            return $this->redirect('Onlinedata:');

        $category = $this->category->get($this->template->d->category);
        $this->template->activeCategory = $category->id;
        $this->template->activeCategoryName = $category->{$this->name_col};

        $date = explode('-', $page);

        if (count($date) != 2 || !is_numeric($date[0]) || !is_numeric($date[1]) || $date[0] < 2000 || $date[1] < 1 || $date[1] > 12)
            return $this->redirect('Onlinedata:');

        $y = $date[0];
        $m = $date[1];

        $this->template->year = $y;
        $this->template->month = $m;

        $this->template->devices = $this->onlinedata->getDevices($y, $m);
        $this->template->os = $this->onlinedata->getOs($y, $m);
        $this->template->browser = $this->onlinedata->getBrowser($y, $m);
        $this->template->lang = $this->onlinedata->getLang($y, $m);
    }

    public function renderLocations($id, $page)
    {
        if (!$this->dataset->existSlug($id))
            return $this->redirect('Onlinedata:');

        $this->template->d = $this->dataset->getTables($id);

        if ($this->template->d->hidden == 1)
            return $this->redirect('Onlinedata:');

        if ($this->template->d->onlinedata == 0)
            return $this->redirect('Onlinedata:');

        $category = $this->category->get($this->template->d->category);
        $this->template->activeCategory = $category->id;
        $this->template->activeCategoryName = $category->{$this->name_col};

        $date = explode('-', $page);

        if (count($date) != 2 || !is_numeric($date[0]) || !is_numeric($date[1]) || $date[0] < 2000 || $date[1] < 1 || $date[1] > 12)
            return $this->redirect('Onlinedata:');

        $y = $date[0];
        $m = $date[1];

        $this->template->year = $y;
        $this->template->month = $m;

        $this->template->user_location = $this->onlinedata->getUserLocation($y, $m);
        $this->template->all_locations = $this->onlinedata->getAllLocations();
    }

    public function renderSummary($id, $page)
    {
        if (!$this->dataset->existSlug($id))
            return $this->redirect('Onlinedata:');

        $this->template->d = $this->dataset->getTables($id);

        if ($this->template->d->hidden == 1)
            return $this->redirect('Onlinedata:');

        if ($this->template->d->onlinedata == 0)
            return $this->redirect('Onlinedata:');

        $category = $this->category->get($this->template->d->category);
        $this->template->activeCategory = $category->id;
        $this->template->activeCategoryName = $category->{$this->name_col};

        $date = explode('-', $page);

        if (count($date) != 2 || !is_numeric($date[0]) || !is_numeric($date[1]) || $date[0] < 2000 || $date[1] < 1 || $date[1] > 12)
            return $this->redirect('Onlinedata:');

        $y = $date[0];
        $m = $date[1];

        $this->template->year = $y;
        $this->template->month = $m;


        $this->template->new_users = $this->onlinedata->getNewUsers($y, $m);
        $this->template->return_users = $this->onlinedata->getReturnUsers($y, $m);
        $this->template->uniq_users = $this->onlinedata->getUniqUsers($y, $m);

        $this->template->dwell = $this->onlinedata->getDwell($y, $m);
        $this->template->max_average = $this->onlinedata->getMaxAverage($y, $m);
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
