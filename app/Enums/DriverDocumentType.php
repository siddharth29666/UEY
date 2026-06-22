<?php

namespace App\Enums;

enum DriverDocumentType: string
{
    case DRIVING_LICENSE = 'driving_license';
    case VEHICLE_REGISTRATION = 'vehicle_registration';
    case INSURANCE = 'insurance';
    case POLICE_CLEARANCE = 'police_clearance';
}
