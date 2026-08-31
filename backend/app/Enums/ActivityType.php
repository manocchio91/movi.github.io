<?php

namespace App\Enums;

enum ActivityType: string
{
    case Restaurant = 'restaurant';
    case Tasting = 'tasting';
    case Wellness = 'wellness';
    case Sport = 'sport';
    case GuidedTour = 'guided_tour';
    case Other = 'other';
}
