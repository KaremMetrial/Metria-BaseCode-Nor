<?php

/**
 * Location hierarchy messages.
 *
 * Kept in their own file rather than added to `lang/validation.php`: publishing
 * that file replaces Laravel's built-in validation messages wholesale, so a
 * couple of custom keys there would silently remove every default message
 * ("The :attribute field is required.") unless the whole set is republished and
 * kept in sync.
 */
return [
    'hierarchy' => [
        'governorate_not_in_country' => 'The selected governorate does not belong to the selected country.',
        'city_not_in_governorate' => 'The selected city does not belong to the selected governorate.',
    ],
];
