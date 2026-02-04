<?php

namespace App\Enum;

enum Role: string
{
    case ADMIN = 'ADMIN';
    case PERSONNEL_MEDICAL = 'PERSONNEL_MEDICAL';
    case PATIENT = 'PATIENT';
    case PROPRIETAIRE_MEDICAUX = 'PROPRIETAIRE_MEDICAUX';
}
