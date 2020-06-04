<?php

namespace App\Components\Forms;

use Nette;

final class ProtectedForm extends Nette\Application\UI\Control
{
    public $translator;

    public function __construct(Nette\Localization\ITranslator $translator)
    {
        $this->translator = $translator;
    }

    public function create()
    {
        $form = new Nette\Application\UI\Form();
        $form->addProtection($this->translator->translate('ui.csrf'));

        return $form;
    }
}