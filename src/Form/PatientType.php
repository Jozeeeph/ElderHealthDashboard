<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;

class PatientType extends AbstractType
{
    public function getParent(): string
    {
        return UtilisateurBaseType::class;
    }
}
