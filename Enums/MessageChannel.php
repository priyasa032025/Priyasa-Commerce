<?php

namespace Modules\PriyasaCore\Enums;

enum MessageChannel:string
{
    case WHATSAPP='whatsapp';

    case SMS='sms';

    case FIREBASE='firebase';

    case EMAIL='email';
}