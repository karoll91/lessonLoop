<?php

namespace App\Enums;

/**
 * Commands the teacher phone sends to the classroom board.
 */
enum BoardCommand: string
{
    case ShowLobby = 'show_lobby';
    case Draw = 'draw';
    case Redraw = 'redraw';
    case ShowQuestion = 'show_question';
    case RevealAnswer = 'reveal_answer';
    case HideAnswer = 'hide_answer';
    case ShowScore = 'show_score';
    case Finish = 'finish';
    case Blackout = 'blackout';
    case Pause = 'pause';
    case Resume = 'resume';
}
