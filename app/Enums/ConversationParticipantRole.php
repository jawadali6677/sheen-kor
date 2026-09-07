<?php

namespace App\Enums;

enum ConversationParticipantRole: string
{
    case Admin = 'admin';
    case Member = 'member';
}
