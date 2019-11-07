<?php

namespace App\PublicModule\Presenters;

use Nette;
use Nette\Application\UI\Form;

abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /** @inject @var Nette\Database\Context */
    public $db;
    /** @inject @var \App\Model\CategoryControl */
    public $category;

    public function beforeRender()
    {
        parent::beforeRender();

        $this->template->categories = $this->category->getAll(true);
    }

    protected function startup()
    {
        parent::startup();
    }

    protected function createComponentSearchForm()
    {
        $form = $this->BootstrapForm->create();

        $form->setAction('/dataset/search');

        $form->addText('search')
            ->addRule(Form::MIN_LENGTH, 'Minimálny počet znakov vo vyhľadávaní je 3.', 3)
            ->addRule(Form::MAX_LENGTH, 'Maximálny počet znakov vo vyhľadávaní 100.', 100)
            ->setRequired(true);

        $form->onSuccess[] = [$this, 'SearchFormSucceeded'];

        return $form;
    }
}