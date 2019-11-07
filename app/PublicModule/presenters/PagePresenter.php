<?php

namespace App\PublicModule\Presenters;

use Nette;
use App\Components\Forms\BootstrapForm;
use Nette\Application\UI\Form;

final class PagePresenter extends BasePresenter
{
    public $BootstrapForm;
    public $db;

    public function __construct(BootstrapForm $BootstrapForm, Nette\Database\Context $db)
    {
        $this->BootstrapForm = $BootstrapForm;
        $this->db = $db;
    }

    public function renderDefault()
    {
        $this->redirect('Homepage:');
    }

    public function renderData()
    {

    }

    public function renderLicence()
    {

    }

    public function renderGdpr()
    {

    }
}
