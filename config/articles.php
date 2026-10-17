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
| Photos are real photographs from Tanzania and East Africa on Wikimedia
| Commons, under CC0 or Creative Commons licences. They are cropped to 16:9
| and stored in public/images/articles/{slug}-{640,1200}.webp, so the site
| never loads images from another server. CC BY / BY-SA photos are shown
| with the author, a licence link and a note that they were cropped. No
| child's face is shown next to a sensitive topic (the periods photo is
| cropped to the booklet in a pupil's hands). To change a photo, replace both
| files and update the credit here. Without the files, the article falls
| back to the coloured icon cover.
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
            'photo' => ['credit' => 'Gilbert Sibomana', 'licence' => 'CC BY-SA 4.0', 'licence_url' => 'https://creativecommons.org/licenses/by-sa/4.0/', 'source' => 'https://commons.wikimedia.org/wiki/File:Younger_man_ready_to_have_his_HIV_test_results.jpg'],
        ],
        'self-esteem-and-body-image' => [
            'topic' => 'girls', 'icon' => 'sprout',
            'photo' => ['credit' => 'Jacksonjk255', 'licence' => 'CC0', 'licence_url' => null, 'source' => 'https://commons.wikimedia.org/wiki/File:UNIVERSITY_OF_DAR_ES_SALAAM_STUDENT_TEACHERS%27_ASSOCIATION_(UDSTA-MLIMANI).jpg'],
        ],
        'periods-mood-and-wellbeing' => [
            'topic' => 'girls', 'icon' => 'flower-2',
            'photo' => ['credit' => 'Marni Sommer, SuSanA Secretariat', 'licence' => 'CC BY 2.0', 'licence_url' => 'https://creativecommons.org/licenses/by/2.0/', 'source' => 'https://commons.wikimedia.org/wiki/File:Hygiene_education_at_school_for_both_girls_and_boys_(photo_by_Marni_Sommer)_(5741027150).jpg'],
        ],
        'understanding-anxiety' => [
            'topic' => 'wellbeing', 'icon' => 'cloud-rain',
            'photo' => ['credit' => 'Dominic Deusdedith', 'licence' => 'CC BY-SA 4.0', 'licence_url' => 'https://creativecommons.org/licenses/by-sa/4.0/', 'source' => 'https://commons.wikimedia.org/wiki/File:Health_and_wellness_Yoga_practice_at_sunset.jpg'],
        ],
        'healthy-relationships' => [
            'topic' => 'relationships', 'icon' => 'heart-handshake',
            'photo' => ['credit' => 'Morris Galavu Masidza Keyonzo', 'licence' => 'CC BY-SA 4.0', 'licence_url' => 'https://creativecommons.org/licenses/by-sa/4.0/', 'source' => 'https://commons.wikimedia.org/wiki/File:Youth_Drama.jpg'],
        ],
        'pressure-at-school-and-home' => [
            'topic' => 'family', 'icon' => 'graduation-cap',
            'photo' => ['credit' => 'Lawsome.Advocate', 'licence' => 'CC BY-SA 4.0', 'licence_url' => 'https://creativecommons.org/licenses/by-sa/4.0/', 'source' => 'https://commons.wikimedia.org/wiki/File:Uluguru_through_the_view_of_Mzumbe_University_Library.jpg'],
        ],
    ],
];
