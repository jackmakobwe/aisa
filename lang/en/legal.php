<?php

/*
 * DRAFT legal text. Must be reviewed by a qualified Tanzanian legal adviser
 * (Personal Data Protection Act 2022, cross-border transfer, counselling
 * rules, minors) before public launch. Nothing here is a claim of legal
 * compliance.
 */

return [
    'draft_notice' => 'Draft: this page is waiting for review by a qualified Tanzanian legal adviser and may change.',
    'last_updated' => 'Version :version',

    'privacy_title' => 'Privacy policy',
    'privacy_intro' => 'Privacy is at the heart of Aisa. This page explains, in plain language, what we collect, why, who can see it, and what you can do.',
    'privacy_sections' => [
        [
            'title' => '1. What we collect',
            'points' => [
                'The messages you write and the replies you receive.',
                'The interface language you choose and the language your messages are written in.',
                'Your age group (18 or older, or under 18) and the version of the notice you agreed to.',
                'A name, only if you choose to give one. You can use any name.',
                'Simple service records, such as when messages were sent and whether a safety concern was flagged.',
            ],
            'paragraphs' => ['We do not ask for your phone number, email address or location. We do not store your IP address with your conversation.'],
        ],
        [
            'title' => '2. Why we use it',
            'points' => [
                'To reply to you, through the AI assistant and, if you ask, through Aisa personally.',
                'To notice when someone may be in danger and make sure a person looks at it.',
                'To keep the service safe, fix problems and understand, in totals only, how it is used.',
            ],
            'paragraphs' => ['We never sell your data or use it for advertising or marketing.'],
        ],
        [
            'title' => '3. The AI service',
            'paragraphs' => [
                'Your messages are sent to an AI provider so the assistant, :name, can reply, suggest a topic, summarise the conversation for Aisa and check for safety concerns. We send only the conversation text needed for that, not your name or code. The provider may process data outside Tanzania. We choose providers whose terms do not allow them to train their models on your conversations.',
            ],
        ],
        [
            'title' => '4. Who can see your conversation',
            'points' => [
                'Aisa, and a trained backup responder who has signed a confidentiality agreement, when you ask for a personal reply or when a safety concern is flagged.',
                'Every time a responder opens a conversation, this is recorded in an access log.',
                'Our hosting and database providers store your data in encrypted form.',
            ],
        ],
        [
            'title' => '5. When we might share information',
            'paragraphs' => ['If what you write suggests that someone, especially a child, is at serious risk of harm, Aisa may need to act on it, as the law requires or allows. We do not know who you are, so this is limited to what you have told us.'],
        ],
        [
            'title' => '6. Your private code',
            'paragraphs' => ['Instead of an account, you receive a long, random private code. We store only a scrambled (hashed) version of it, so we cannot tell you your code if you lose it. Anyone who has your code can read your conversation, so keep it private.'],
        ],
        [
            'title' => '7. Cookies and storage on your device',
            'points' => [
                'A session cookie keeps your conversation open while you use the site. It ends when you leave or use Quick exit.',
                'A language cookie remembers English or Kiswahili.',
                'If you choose "Remember this code on this device", an encrypted cookie stores your code for up to 30 days. You can make the device forget it at any time.',
                'While you type on the home page, your unsent message is kept in this browser tab only, so changing language does not lose it.',
            ],
            'paragraphs' => ['We do not use advertising or tracking cookies.'],
        ],
        [
            'title' => '8. How long we keep it',
            'paragraphs' => ['Conversations are deleted automatically :days days after the last activity. A conversation with an unresolved safety concern is kept until a person has reviewed it.'],
        ],
        [
            'title' => '9. Deleting your conversation',
            'paragraphs' => ['On the conversation page, choose "Delete my conversation". Aisa will delete the conversation and everything linked to it, and your code will stop working.'],
        ],
        [
            'title' => '10. Security',
            'paragraphs' => ['We use encrypted connections, encrypt conversations when stored, protect the dashboard with two-factor sign-in, and log every staff view. No system is perfectly secure, and we cannot control what happens on your own device: use Quick exit and private browsing if others share it.'],
        ],
        [
            'title' => '11. Age',
            'paragraphs' => ['Aisa is designed for adults. Our approach for people under 18 is explained when you start a conversation and is being reviewed with legal advice.'],
        ],
        [
            'title' => '12. Contact',
            'paragraphs' => ['Questions or concerns about privacy can be sent through the Contact & report page.'],
        ],
    ],

    'terms_title' => 'Terms of use',
    'terms_intro' => 'These terms explain what Aisa offers and what it does not. By using the site you agree to them.',
    'terms_sections' => [
        [
            'title' => '1. What Aisa is',
            'paragraphs' => ['Aisa offers personal guidance and emotional support: an AI assistant that replies straight away, and an optional personal reply from a human. It is general guidance, not professional advice.'],
        ],
        [
            'title' => '2. What Aisa is not',
            'points' => [
                'Not therapy, counselling by a licensed professional, medical treatment or psychiatric care.',
                'Not a crisis line or emergency service, and not monitored around the clock.',
                'Not legal, medical or financial advice.',
            ],
        ],
        [
            'title' => '3. The AI assistant',
            'paragraphs' => [':name is an AI. It can be wrong, and it does not know you or your situation beyond what you write. Please use your own judgement and seek professional help for serious matters.'],
        ],
        [
            'title' => '4. Personal replies',
            'paragraphs' => ['Aisa aims to reply within :hours hours but cannot guarantee it. If she has too many requests, new requests may be paused.'],
        ],
        [
            'title' => '5. Using the site respectfully',
            'points' => [
                'Do not use Aisa to harass, threaten or abuse anyone, including Aisa.',
                'Do not try to break, overload or misuse the service.',
                'We may block codes used for abuse.',
            ],
        ],
        [
            'title' => '6. Your responsibility',
            'paragraphs' => ['Keep your code private. Decisions you make after using Aisa are your own.'],
        ],
        [
            'title' => '7. Limits of responsibility',
            'paragraphs' => ['To the extent the law allows, Aisa is not responsible for decisions made on the basis of general guidance given here. Nothing in these terms limits rights you have under Tanzanian law.'],
        ],
        [
            'title' => '8. Changes',
            'paragraphs' => ['We may update these terms. The version number at the bottom of this page shows which version applies.'],
        ],
    ],
];
