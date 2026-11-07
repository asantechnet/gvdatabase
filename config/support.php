<?php

return [
    /*
    | The private inbox that receives newly submitted support requests.
    | Keep this out of source control and configure it per environment.
    */
    // A valid default keeps the in-app support form operational in a fresh
    // deployment. Environments can still override this inbox with
    // SUPPORT_TICKET_RECIPIENT.
    'ticket_recipient' => env('SUPPORT_TICKET_RECIPIENT') ?: 'info@asantech.net',
];
