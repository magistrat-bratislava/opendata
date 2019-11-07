<?php

namespace App\Components\Forms;

use Nette;
use AlesWita;

final class BootstrapForm extends Nette\Application\UI\Control
{
    private $factory;

    public function __construct(AlesWita\FormRenderer\Factory $factory)
    {
        $this->factory = $factory;
    }

    public function create()
    {
        $form = $this->factory->create();

        return $form;
    }
}