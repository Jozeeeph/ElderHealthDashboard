<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;

class AdminType extends AbstractType
{
    public function getParent(): string
    {
        return UtilisateurBaseType::class;
    }
}
