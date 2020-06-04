<?php

namespace App\AdminModule\Presenters;

use App\Model\BannerControl;
use Nette;
use App\Components\Forms\ProtectedForm;
use Nette\Application\UI\Form;

final class BannerPresenter extends BasePresenter
{
    public $db;
    protected $category;
    private $banner;
    public $BootstrapForm;

    protected function startup()
    {
        parent::startup();

        if (!$this->getUser()->isAllowed('global')) {
            throw new Nette\Application\ForbiddenRequestException;
        }
    }

    public function __construct(Nette\Database\Context $db, BannerControl $banner, ProtectedForm $BootstrapForm)
    {
        $this->db = $db;
        $this->banner = $banner;
        $this->BootstrapForm = $BootstrapForm;
    }

    public function renderDefault()
    {
        $this->template->banner = $this->banner->getAll();

        $days = $this->banner->getLastDays(7);
        $days7 = [];

        foreach ($days as $d) {
            $days7[$d->day] = $d->count;
        }

        $this->template->days7 = [];

        $timestamp = time() - 7 * 24 * 3600;

        for ($i = 0; $i < 7; $i++) {
            $timestamp += 24 * 3600;
            $day = date('Y-m-d', $timestamp);

            if (isset($days7[$day]))
                $this->template->days7[] = $days7[$day];
            else
                $this->template->days7[] = 0;
        }

        // 15 days

        $days = $this->banner->getLastDays(15);
        $days15 = [];

        foreach ($days as $d) {
            $days15[$d->day] = $d->count;
        }

        $this->template->days15 = [];

        $timestamp = time() - 15 * 24 * 3600;

        for ($i = 0; $i < 15; $i++) {
            $timestamp += 24 * 3600;
            $day = date('Y-m-d', $timestamp);

            if (isset($days15[$day]))
                $this->template->days15[] = $days15[$day];
            else
                $this->template->days15[] = 0;
        }
    }
}