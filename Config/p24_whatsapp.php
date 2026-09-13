<?php
return [
 'verify_token'=>env('PRIYASA_META_VERIFY_TOKEN'),
 'app_secret'=>env('PRIYASA_META_APP_SECRET'),
 'access_token'=>env('PRIYASA_META_ACCESS_TOKEN'),
 'phone_number_id'=>env('PRIYASA_META_PHONE_NUMBER_ID'),
 'graph_version'=>env('PRIYASA_META_GRAPH_VERSION','v23.0'),
 'enabled'=>(bool) env('PRIYASA_META_WHATSAPP_ENABLED',false),
];
