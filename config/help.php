<?php

/*
|--------------------------------------------------------------------------
| Get help now: regions and the service map
|--------------------------------------------------------------------------
|
| Regions of Tanzania (26 on the mainland, 5 in Zanzibar), used to filter
| contacts and to search the map. A contact without a region is national.
|
| The map is a Google Maps search ("Police station in Mwanza, Tanzania").
| It is loaded only after the person asks for it, because embedding Google
| tells Google who visited, and some visitors are hiding from an abuser.
| With GOOGLE_MAPS_EMBED_KEY set, the official Maps Embed API is used (free
| of charge, but needs a Google Cloud key restricted to this site's domain).
| Without a key, the classic keyless embed is used, which Google does not
| guarantee: set a key before launch.
|
*/

return [
    'regions' => [
        'Arusha', 'Dar es Salaam', 'Dodoma', 'Geita', 'Iringa', 'Kagera', 'Katavi', 'Kigoma',
        'Kilimanjaro', 'Lindi', 'Manyara', 'Mara', 'Mbeya', 'Morogoro', 'Mtwara', 'Mwanza',
        'Njombe', 'Pwani', 'Rukwa', 'Ruvuma', 'Shinyanga', 'Simiyu', 'Singida', 'Songwe',
        'Tabora', 'Tanga',
        'Kaskazini Pemba', 'Kusini Pemba', 'Kaskazini Unguja', 'Kusini Unguja', 'Mjini Magharibi',
    ],

    // Service type => English search words for Google Maps. Labels are in lang/*/help_page.php.
    'map_services' => [
        'police' => 'Police station',
        'hospital' => 'Hospital',
        'health_centre' => 'Health centre',
        'legal_aid' => 'Legal aid',
        'social_welfare' => 'Social welfare office',
    ],

    'maps_embed_key' => env('GOOGLE_MAPS_EMBED_KEY'),
];
