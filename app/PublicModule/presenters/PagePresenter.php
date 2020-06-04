<?php

namespace App\PublicModule\Presenters;

use App\Model\BannerControl;
use Nette;
use App\Components\Forms\ProtectedForm;
use Nette\Application\UI\Form;

final class PagePresenter extends BasePresenter
{
    public $BootstrapForm;
    public $db;
    public $banner;

    public function __construct(ProtectedForm $BootstrapForm, BannerControl $banner, Nette\Database\Context $db)
    {
        $this->BootstrapForm = $BootstrapForm;
        $this->db = $db;
        $this->banner = $banner;
    }

    public function renderDefault()
    {
        $this->redirect('Homepage:');
    }

    public function actionEsluzby()
    {
        $this->banner->count();
        $this->redirectUrl('https://esluzby.bratislava.sk');
    }
}
