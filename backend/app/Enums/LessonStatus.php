<?php

namespace App\Enums;

enum LessonStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Finished = 'finished';
}
