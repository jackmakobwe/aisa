<?php

/*
|--------------------------------------------------------------------------
| Mama Care photos
|--------------------------------------------------------------------------
|
| Real photographs taken in Tanzania, from Wikimedia Commons, under public
| domain or Creative Commons licences. They are cropped to 16:9 and stored in
| public/images/mama/{key}-{640,1200}.webp, so the site never loads images
| from another server. Alt text is in lang/{locale}/mama.php under "photos".
|
| CC BY-SA photos must keep their credit, licence link and the note that they
| were cropped. The people shown are not connected to the topics on the page,
| and the page says so. No identifiable schoolgirls or minors are shown next
| to sensitive topics.
|
| Keys match the page: "hero" is the header photo; the others are shown at the
| top of the section with the same key in lang/{locale}/mama.php.
|
*/

return [
    'photos' => [
        'hero' => [
            'credit' => 'USAID in Africa',
            'licence' => 'Public domain',
            'licence_url' => null,
            'source' => 'https://commons.wikimedia.org/wiki/File:Rehema_and_her_healthy_newborn_son,_Tanzania_(25856083348).jpg',
        ],
        'pregnancy' => [
            'credit' => 'Riccardo Gangale / VectorWorks, President\'s Malaria Initiative',
            'licence' => 'Public domain',
            'licence_url' => null,
            'source' => 'https://commons.wikimedia.org/wiki/File:Net_Distribution_In_Mwanza,_Tanzania_2016_(31134561873).jpg',
        ],
        'baby' => [
            'credit' => 'Brocken Inaglory',
            'licence' => 'CC BY-SA 3.0',
            'licence_url' => 'https://creativecommons.org/licenses/by-sa/3.0/',
            'source' => 'https://commons.wikimedia.org/wiki/File:Zanzibar_31.JPG',
        ],
        'feelings' => [
            'credit' => 'Nevit Dilmen',
            'licence' => 'CC BY-SA 3.0',
            'licence_url' => 'https://creativecommons.org/licenses/by-sa/3.0/',
            'source' => 'https://commons.wikimedia.org/wiki/File:People_in_Tanzania_2192_Nevit.jpg',
        ],
        'future' => [
            'credit' => 'Eid John, Wiki Loves Women Tanzania',
            'licence' => 'CC BY-SA 4.0',
            'licence_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
            'source' => 'https://commons.wikimedia.org/wiki/File:Wiki_Loves_Women_Tanzania_first_group_session_January_2019.jpg',
        ],
        'supporters' => [
            'credit' => 'Arnold Tibaijuka',
            'licence' => 'CC BY-SA 4.0',
            'licence_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
            'source' => 'https://commons.wikimedia.org/wiki/File:A_man_with_a_child_in_Tanzania1.jpg',
        ],
    ],
];
