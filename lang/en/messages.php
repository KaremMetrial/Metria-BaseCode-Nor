<?php

/**
 * Human-readable success messages for the `message` field of the API envelope.
 *
 * The `:resource` placeholder keeps one key per verb instead of one per
 * resource-verb pair, while still allowing each locale to place the noun where
 * its own grammar wants it. Arabic needs that freedom: it does not put the verb
 * first the way the English phrasing does.
 */
return [
    'created' => ':resource created successfully.',
    'updated' => ':resource updated successfully.',
    'deleted' => ':resource deleted successfully.',

    'resources' => [
        'country' => 'Country',
        'governorate' => 'Governorate',
        'city' => 'City',
    ],
];
