<?php

/*
|--------------------------------------------------------------------------
| Educational articles
|--------------------------------------------------------------------------
|
| The text of each article lives in lang/{en,sw}/articles.php under the same
| slug. This file only holds what does not depend on the language: the order
| (first = shown first, and the first three appear on the home page), the
| topic, the fallback cover icon (a Lucide icon name) and the photo credit.
|
| Photos are free-licence images from Unsplash (https://unsplash.com/license),
| cropped to 16:9 and stored in public/images/articles/{slug}-{640,1200}.webp,
| so the site never loads images from another server. To change a photo,
| replace both files and update the credit here. Without the files, the
| article falls back to the coloured icon cover.
|
| The articles are general information written for development. They must be
| reviewed by a qualified counsellor or health worker (and the Kiswahili by a
| native speaker) before launch; until then each article says it is a draft.
|
*/

return [
    'reviewed' => (bool) env('AISA_ARTICLES_REVIEWED', false),

    'home_count' => 3,

    'topics' => ['therapy', 'girls', 'wellbeing', 'relationships', 'family'],

    'items' => [
        'what-to-expect-from-counselling' => [
            'topic' => 'therapy', 'icon' => 'messages-square',
            'photo' => ['credit' => 'Vitaly Gariev', 'source' => 'https://unsplash.com/photos/PYbWNtXcpjM'],
        ],
        'self-esteem-and-body-image' => [
            'topic' => 'girls', 'icon' => 'sprout',
            'photo' => ['credit' => 'Gift Habeshaw', 'source' => 'https://unsplash.com/photos/kSTcz5gE018'],
        ],
        'periods-mood-and-wellbeing' => [
            'topic' => 'girls', 'icon' => 'flower-2',
            'photo' => ['credit' => 'Baptista Ime James', 'source' => 'https://unsplash.com/photos/ooQAWk3WPU4'],
        ],
        'understanding-anxiety' => [
            'topic' => 'wellbeing', 'icon' => 'cloud-rain',
            'photo' => ['credit' => 'Alexander Mass', 'source' => 'https://unsplash.com/photos/P9NNQXKQ3dA'],
        ],
        'healthy-relationships' => [
            'topic' => 'relationships', 'icon' => 'heart-handshake',
            'photo' => ['credit' => 'Dwayne Joe', 'source' => 'https://unsplash.com/photos/wL2g7qhA9to'],
        ],
        'pressure-at-school-and-home' => [
            'topic' => 'family', 'icon' => 'graduation-cap',
            'photo' => ['credit' => 'Emmanuel Ikwuegbu', 'source' => 'https://unsplash.com/photos/h5xEHzfepNk'],
        ],
    ],
];
