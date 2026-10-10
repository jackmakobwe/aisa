<?php

namespace App\Services\Safety;

/**
 * Deterministic first-pass risk detection for English, Kiswahili, Sheng and
 * mixed text.
 *
 * This layer exists so that risk detection never depends solely on the AI
 * provider being available or behaving correctly, and so that a message
 * cannot talk its way out of being flagged. It is intentionally
 * over-inclusive: a false alarm costs a human review, a miss can cost much
 * more. It is NOT a complete detector. Indirect or novel phrasing will be
 * missed, which is why the AI layer and human review sit on top of it.
 *
 * Phrase lists must be reviewed by a native Kiswahili speaker and extended
 * from the safety test set (tests/Unit/Safety) before launch.
 */
class RuleBasedSafetyClassifier
{
    /**
     * Regular expressions per risk type, matched against normalised text
     * (lowercase, punctuation stripped, repeated letters collapsed to two).
     *
     * @var array<string, list<string>>
     */
    private const PATTERNS = [
        'suicide' => [
            // English
            '\bkill(ing)? my ?self\b', '\bend(ing)? (my|it) (life|all)\b', '\bend it all\b',
            '\bsuicid(e|al)\b', '\bwant(ed)? to die+\b', '\bwish i (was|were) dead\b',
            '\b(dont|do not|don t) want to (live|be alive|be here)\b', '\bno (reason|point) (to|in) (live|living)\b',
            '\bbetter off (dead|without me|bila mimi)\b', '\btake (all )?(my|the) (pills|tablets)\b',
            '\bsleep and (never|not) wake up\b', '\b(giving|given|gave) (away )?(all )?my (things|stuff|belongings)( away)?\b',
            '\b(saved|saving|collected|collecting|stockpil\w*)( up)?\b.{0,40}\b(pills|tablets)\b',
            '\bnot (be|being) (here|around) anymore\b', '\bdone with (life|living)\b',
            '\bcant (go on|take it anymore)\b', '\bcan ?not go on\b', '\bgoodbye forever\b',
            // Kiswahili
            '\b(ku|ni|na|nita|ali|ame|wa)?jiu(a|e|we)\b', '\bkujitoa uhai\b', '\bkujiondoa (duniani|maishani)\b',
            '\b(nataka|natamani|ningependa) kufa\b', '\bsitaki kuishi\b', '\bsina sababu ya kuishi\b',
            '\b(ni )?bora (nife|nikufe)\b', '\bnimechoka (na )?(kuishi|maisha)\b', '\bmaisha (hayana|haina) maana\b',
            '\bnitakunywa sumu\b', '\bkunywa sumu\b', '\bkumeza vidonge vyote\b', '\bnijinyonge\b', '\bkujinyonga\b',
            '\bsitaki kuwepo\b', '\bnataka kupotea (milele|kabisa)\b',
            '\b(nilale )?nisiamke (tena|kabisa|milele)\b', '\b(nimeshanunua|nimenunua|nimeficha|nina) sumu\b',
            '\b(nimekusanya|nimeweka) vidonge\b',
            // Sheng / mixed
            '\b(ku|ni|na)?jimaliz(a|e)\b', '\bniko done na (life|maisha)\b', '\bnataka ku(end|end it)\b',
            '\bsitaki ku ?live\b',
        ],
        'self_harm' => [
            '\b(hurt(ing)?|harm(ing)?|cut(ting)?|burn(ing)?) my ?self\b', '\bself ?harm(ing)?\b', '\bcutting (my|on my) (arm|arms|wrist|wrists|skin|legs)\b',
            '\b(ku|na|ni)?jidhuru\b', '\b(ku|na|ni)?jikata\b', '\bnajiumiza\b', '\bkujiumiza\b',
        ],
        'abuse' => [
            '\b(he|she|they|my husband|my wife|my partner|my father|my mother|my boss) (hits|beats|beat|hit|slaps|kicks|chokes|strangles) me\b',
            '\b(hits|beats|slaps|chokes) me\b', '\braped?\b',
            '\b(he|she|they) (kicked|hit|slapped|punched|choked|strangled|burned) me\b',
            '\b(he|she|they) (pushed|threw|shoved) me (down|into|against|onto|off|out|to the (floor|ground))\b', '\blocks? me (in|inside)\b',
            '\b(a|wa)?(na|li|me|hu)nishika shingo\b', '\b(a|wa)?(na|li|me|hu)ninyonga\b', '\bsexual(ly)? (abuse|abused|assault|assaulted)\b',
            '\bforced me to have sex\b', '\b(domestic|physical) (violence|abuse)\b', '\babusive\b', '\babuses me\b',
            // Kiswahili: -piga (hit), -chapa (beat), -baka (rape), -nyanyasa (abuse), -lazimisha (force)
            // subject (a/wa/m) + tense (na/li/me/ta/ki/hu) + object "ni" (me) + verb, e.g. ananipiga, alinibaka, hunichapa
            '\b(a|wa|m)?(na|li|me|ta|ki|hu)ni(piga|chapa|tandika|baka|nyanyasa|dhulumu|tesa)\b',
            '\bkunipiga\b', '\bkunibaka\b', '\bubakaji\b', '\bunyanyasaji\b', '\bukatili wa kijinsia\b',
            '\b(a|ali|ame)nilazimisha (kufanya )?(mapenzi|ngono)\b', '\bananitesa\b',
        ],
        'violence' => [
            '\b(i will|ill|i am going to|im going to|i want to) (kill|hurt|stab|shoot) (him|her|them|someone|somebody)\b',
            '\bnita(mu|wa)u(a|e)\b', '\bnataka (kumuua|kuwaua)\b', '\bnimuue\b',
        ],
        'immediate_danger' => [
            '\b(he|she|they)( is| are)? (going to|will|wants to|threatened to) kill me\b', '\bthreaten(s|ed)? to kill\b',
            '\b(has|have|holding) a (knife|gun|weapon)\b', '\bi am in danger\b', '\bim in danger\b', '\bnot safe (at home|right now)\b',
            '\b(a|wa)?(ta|na)niua\b', '\b(a|wa)nataka kuniua\b', '\b(ame|ali)tishia kuniua\b', '\bniko hatarini\b',
            '\bana (kisu|bunduki|panga)\b', '\bsiko salama\b',
        ],
        'child_safeguarding' => [
            '\b(touches|touched) me (inappropriately|there|down there)\b', '\b(uncle|stepfather|teacher|neighbour|neighbor) (touches|touched|abuses|abused)\b',
            '\bchild (abuse|marriage)\b',
            // Secrecy demands are a common grooming sign.
            '\btells? me not to tell (anyone|anybody|my (mother|mum|mom|father|dad|parents))\b',
            '\bnisimwambie (mtu|mama|baba|yeyote|mtu yeyote)\b', '\bnisiwaambie (wazazi|watu)\b', '\b(i am|im) (1[0-7]|[5-9]) (years old|yrs)\b.*\b(marry|married|touch|sex)\b',
            '\b(a|wa)?(na|li|me|hu)ni(gusa|shika) vibaya\b', '\bndoa za utotoni\b', '\bnalazimishwa kuolewa\b',
            '\bwananilazimisha kuolewa\b', '\b(mjomba|baba wa kambo|mwalimu|jirani) (a|wa)?(na|li|me|hu)ni(gusa|shika|baka|nyanyasa)\b',
        ],
    ];

    public function assess(string $text): SafetyAssessment
    {
        $normalized = self::normalize($text);
        $types = [];

        foreach (self::PATTERNS as $type => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/'.$pattern.'/u', $normalized) === 1) {
                    $types[] = $type;
                    break;
                }
            }
        }

        return SafetyAssessment::fromTypes($types, 'rules');
    }

    public static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        // Straight and curly apostrophes are removed so "don't" becomes "dont".
        $text = str_replace(["'", '’', '‘'], '', $text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        // "sooo saaad" -> "soo saad": reduces elongation without merging real doubles.
        $text = preg_replace('/(\p{L})\1{2,}/u', '$1$1', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
