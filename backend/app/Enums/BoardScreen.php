<?php

namespace App\Enums;

/**
 * What the classroom board (electronic whiteboard) currently shows.
 */
enum BoardScreen: string
{
    case Pairing = 'pairing';
    case Lobby = 'lobby';
    case Drawing = 'drawing';
    case Question = 'question';
    case Answer = 'answer';
    case Score = 'score';
    case Summary = 'summary';
    case Blackout = 'blackout';
    case Pause = 'pause';
}
