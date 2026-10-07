<?php

return [
    'features' => array_values(array_filter(explode(',', (string) env('MYFATOORAH_FEATURES', 'payments,embedded,tokenization,reporting')))),
];
