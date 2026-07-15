<?php

namespace App\Enums;

enum UserRole: string
{
    case Participant = 'participant';
    case Organizer = 'organizer';
    case Admin = 'admin';
}
