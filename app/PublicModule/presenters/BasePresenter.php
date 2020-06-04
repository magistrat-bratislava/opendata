<?php

namespace App\PublicModule\Presenters;

use Nette;
use Contributte;
use Nette\Application\UI\Form;

abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /** @persistent */
    public $locale;
    /** @var Nette\Localization\ITranslator @inject */
    public $translator;
    /** @inject @var Nette\Database\Context */
    public $db;
    /** @inject @var \App\Model\CategoryControl */
    public $category;
    /** @inject @var \App\Model\DatasetControl */
    public $dataset;
    /** @inject @var \App\Components\Forms\ProtectedForm */
    public $form;

    protected $name_col;
    protected $description_col;

    public function beforeRender()
    {
        parent::beforeRender();

        $this->template->locale = $this->locale;
        $this->name_col = $this->template->name_col = 'name_'.$this->locale;
        $this->description_col = $this->template->description_col = 'description_'.$this->locale;
        $this->template->categories = $this->category->getAll(true);

        $this->template->last_dataset = $this->dataset->getLast('dataset');
        $this->template->last_powerbi = $this->dataset->getLast('powerbi');
        $this->template->last_map = $this->dataset->getLast('map');
        $this->template->total_dataset_files = $this->dataset->getFileCount();

        $this->template->onlinedata_link = false;
    }

    protected function startup()
    {
        parent::startup();
    }

    protected function createComponentSearchForm()
    {
        $form = $this->form->create();

        $form->addText('search')
            ->addRule(Form::MAX_LENGTH, $this->translator->translate('search.max_chars', ['count' => 100]), 100)
            ->setRequired(true);

        $form->onSuccess[] = [$this, 'SearchFormSucceeded'];

        return $form;
    }

    protected function createComponentSearchExtendedForm()
    {
        $col = 'name_'.$this->locale;

        $cat = $this->category->loadCategories();
        $y = $this->dataset->loadYears();
        $dis = $this->dataset->loadDistricts();
        $aut = $this->db->table('authors')->order($col)->fetchAll();

        $categories = ['0' => $this->translator->translate('ui.category')];
        $years = ['0' => $this->translator->translate('ui.year')];
        $districts = ['0' => $this->translator->translate('ui.district')];
        $authors = ['0' => $this->translator->translate('ui.author')];

        foreach ($cat[$col] as $k => $v)
            $categories[$k] = $v;

        foreach ($y as $yy)
            $years[$yy] = $yy;

        foreach ($dis as $yy)
            $districts[$yy] = $yy;

        foreach ($aut as $yy)
            $authors[$yy->id] = $yy->$col;

        $form = $this->form->create();

        $form->setAction('/dataset/extend');

        $form->addText('search')
            ->addRule(Form::MAX_LENGTH, $this->translator->translate('search.max_chars', ['count' => 100]), 100)
            ->setRequired(false);
        $form->addSelect('category', '', $categories);
        $form->addSelect('year', '', $years);
        $form->addSelect('district', '', $districts);
        $form->addSelect('authors', '', $authors);
        $form->addSelect('visualization', '', ['0' => $this->translator->translate('ui.visualisation'), '1' => 'Power BI', '2' => $this->translator->translate('ui.map_ba')]);
        $form->addText('uniq_id');

        $form->onSuccess[] = [$this, 'SearchExtendedFormSucceeded'];

        return $form;
    }
}