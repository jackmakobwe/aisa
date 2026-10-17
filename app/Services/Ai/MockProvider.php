<?php

namespace App\Services\Ai;

use App\Services\LanguageDetector;
use App\Services\Safety\RuleBasedSafetyClassifier;

/**
 * DEVELOPMENT ONLY. Produces canned, clearly labelled responses so the
 * product can be exercised without an API key. Messages it produces are
 * stored with is_mock = true and shown with a "simulated" badge. The
 * service container refuses to use it when APP_ENV=production.
 */
class MockProvider implements AiProvider
{
    public const MARKER_EN = '[Simulated reply for development — not a real AI response]';

    public const MARKER_SW = '[Jibu la majaribio — si jibu halisi la AI]';

    // Checked in order: the first category with a matching word wins.
    private const CATEGORY_WORDS = [
        'womens_health' => ['pregnant', 'pregnancy', 'mimba', 'ujauzito', 'mjamzito', 'period', 'hedhi', 'contraception'],
        'parenting' => ['child', 'children', 'baby', 'toddler', 'son', 'daughter', 'mtoto', 'watoto', 'mwanangu', 'parenting', 'malezi'],
        'business' => ['business', 'biashara', 'mtaji', 'capital', 'customers', 'wateja'],
        'money' => ['money', 'budget', 'bajeti', 'salary', 'mshahara', 'pesa', 'save', 'akiba', 'debt', 'deni', 'loan', 'mkopo', 'scam', 'utapeli'],
        'education' => ['school', 'exam', 'exams', 'study', 'shule', 'mtihani', 'chuo', 'university'],
        'career' => ['career', 'cv', 'interview', 'usaili', 'ajira', 'internship'],
        'relationship' => ['boyfriend', 'girlfriend', 'partner', 'husband', 'wife', 'marriage', 'mpenzi', 'mume', 'mke', 'ndoa', 'relationship', 'uhusiano'],
        'family' => ['mother', 'father', 'parents', 'family', 'mama', 'baba', 'wazazi', 'familia', 'ndugu', 'in-laws', 'wakwe'],
        'work' => ['job', 'work', 'boss', 'kazi', 'bosi'],
        'grief' => ['died', 'death', 'funeral', 'loss', 'lost', 'amefariki', 'kifo', 'msiba', 'mazishi', 'alifariki'],
        'growth' => ['confidence', 'goals', 'future', 'decision', 'purpose', 'kujiamini', 'malengo', 'uamuzi', 'ndoto'],
    ];

    public function __construct(
        private readonly RuleBasedSafetyClassifier $rules,
        private readonly LanguageDetector $languages,
    ) {}

    public function name(): string
    {
        return 'mock';
    }

    public function model(): string
    {
        return 'mock';
    }

    public function isMock(): bool
    {
        return true;
    }

    public function generateJson(string $system, array $messages, array $schema, int $maxTokens): AiResult
    {
        $lastUser = '';
        foreach (array_reverse($messages) as $message) {
            if ($message['role'] === 'user') {
                $lastUser = $message['content'];
                break;
            }
        }

        $language = $this->languages->detect($lastUser);
        $sw = $language === 'sw';
        $risk = $this->rules->assess($lastUser);

        if (isset($schema['properties']['draft'])) {
            return new AiResult([
                'draft' => ($sw ? self::MARKER_SW : self::MARKER_EN)."\n\n".($sw
                    ? 'Asante kwa kuniandikia na kunieleza kinachoendelea. (Hii ni rasimu ya majaribio. Tafadhali iandike upya kwa maneno yako mwenyewe.)'
                    : 'Thank you for writing to me and telling me what is going on. (This is a development placeholder draft. Please write the real reply yourself.)'),
                'sensitive' => $risk->flagged,
                'notes_for_responder' => 'Development mock draft: no real model was called.',
            ], 'mock');
        }

        $userTurns = count(array_filter($messages, fn ($m) => $m['role'] === 'user'));
        $asks = $userTurns <= 2 && ! $risk->flagged;

        $reply = $sw
            ? self::MARKER_SW."\n\nAsante kwa kunieleza. Mimi ni msaidizi wa AI, si binadamu. ".($risk->flagged
                ? 'Ninasikitika unapitia hali hii. Kama uko hatarini sasa hivi, tafadhali wasiliana na huduma za dharura au mtu unayemwamini mara moja, na tumia namba za msaada zilizo kwenye ukurasa huu.'
                : ($asks ? 'Ungependa kunieleza zaidi kuhusu jinsi hali hii inavyokuathiri?' : 'Hapa kuna njia chache unazoweza kufikiria. Pia unaweza kuchagua "Ongea na Aisa" ili upate jibu binafsi kutoka kwa binadamu.'))
            : self::MARKER_EN."\n\nThank you for telling me this. I'm an AI assistant, not a person. ".($risk->flagged
                ? "I'm sorry you're going through this. If you are in danger right now, please contact emergency services or someone you trust straight away, and use the help contacts shown on this page."
                : ($asks ? 'Would you like to tell me a little more about how this is affecting you?' : 'Here are a few options you could think about. You can also choose "Talk to Aisa" for a personal reply from a human.'));

        return new AiResult([
            'reply' => $reply,
            'language' => $language,
            'category' => $this->category($lastUser),
            'urgency' => $risk->flagged ? ($risk->level === 'critical' ? 'critical' : 'high') : 'low',
            'risk' => ['flag' => $risk->flagged, 'types' => $risk->types],
            'summary' => 'Development mock summary (no real model was called).',
            'suggest_human' => $risk->flagged,
            'asked_question' => $asks,
        ], 'mock');
    }

    private function category(string $text): string
    {
        $text = mb_strtolower($text);
        foreach (self::CATEGORY_WORDS as $category => $words) {
            foreach ($words as $word) {
                if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $text)) {
                    return $category;
                }
            }
        }

        return 'other';
    }
}
