<?php

return [
    /*
    | The private inbox that receives newly submitted support requests.
    | Keep this out of source control and configure it per environment.
    */
    'ticket_recipient' => env('SUPPORT_TICKET_RECIPIENT'),
];
