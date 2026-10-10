<?php

namespace App\Services;

/**
 * Cheap heuristic language tag (en / sw / mixed) based on common function
 * words. Used for dashboard filters when the AI is unavailable and by the
 * development mock. The AI's own detection takes precedence when present.
 */
class LanguageDetector
{
    private const SW = [
        'na', 'ni', 'kwa', 'ya', 'wa', 'za', 'la', 'cha', 'katika', 'sana', 'sina', 'nina', 'mimi', 'wewe',
        'yeye', 'sisi', 'kwamba', 'lakini', 'kama', 'hii', 'huyu', 'hapa', 'sasa', 'leo', 'jana', 'kesho',
        'nini', 'gani', 'nataka', 'sijui', 'naomba', 'tafadhali', 'asante', 'habari', 'mume', 'mke', 'familia',
        'kazi', 'maisha', 'mama', 'baba', 'rafiki', 'ndiyo', 'hapana', 'sitaki', 'nimechoka', 'shida', 'tatizo',
        'pia', 'bado', 'tu', 'hata', 'kila', 'wangu', 'yangu', 'changu', 'zangu', 'wake', 'yake', 'nimekuwa',
    ];

    private const EN = [
        'the', 'and', 'is', 'are', 'was', 'were', 'i', 'my', 'me', 'you', 'your', 'he', 'she', 'they', 'it',
        'to', 'of', 'in', 'on', 'for', 'with', 'what', 'how', 'why', 'because', 'but', 'not', 'dont', 'have',
        'has', 'do', 'does', 'feel', 'feeling', 'want', 'need', 'about', 'this', 'that', 'so', 'very', 'really',
        'work', 'family', 'husband', 'wife', 'friend', 'life', 'can', 'cant', 'will', 'just', 'am', 'be',
    ];

    public function detect(string $text): string
    {
        $words = preg_split('/[^\p{L}]+/u', mb_strtolower(str_replace(["'", '’'], '', $text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sw = $en = 0;
        foreach ($words as $word) {
            $sw += in_array($word, self::SW, true) ? 1 : 0;
            $en += in_array($word, self::EN, true) ? 1 : 0;
        }

        if ($sw === 0 && $en === 0) {
            return 'en';
        }
        $total = $sw + $en;
        if ($sw / $total >= 0.8) {
            return 'sw';
        }
        if ($en / $total >= 0.8) {
            return 'en';
        }

        return 'mixed';
    }
}
