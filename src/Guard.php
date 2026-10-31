<?php

namespace EduLazaro\Larachat;

use Illuminate\Support\Facades\Cache;

/**
 * Deterministic guardrails around the bot: input screening before the model
 * is called and output screening before a reply reaches the visitor. The
 * system prompt carries the same rules; this layer holds even when the model
 * is talked out of them. Patterns cover English, Spanish, Catalan and French.
 */
class Guard
{
    protected const STRIKE_TTL = 86400;

    protected const INJECTION = [
        '/\b(ignore|disregard|forget|override|bypass)\b.{0,40}\b(instructions?|rules?|prompt|guidelines|restrictions?|above|previous)\b/iu',
        '/\b(ignora|olvida|omite|salta(te)?|anula|desactiva)\b.{0,40}\b(instrucci[oó]n(es)?|reglas?|prompt|indicaciones|restricci[oó]n(es)?|anterior(es)?)\b/iu',
        '/\b(ignora|oblida|omet|salta\'t|anul·la|desactiva)\b.{0,40}\b(instrucci[oó]ns?|regles?|prompt|indicacions|restriccions?|anteriors?)\b/iu',
        '/\b(ignore[sz]?|oublie[sz]?|contourne[sz]?)\b.{0,40}\b(instructions?|r[eè]gles?|consignes?|prompt|pr[eé]c[eé]dent(e)?s?)\b/iu',
        '/\b(system|initial|hidden|original)\s+(prompt|message|instructions?)\b/iu',
        '/\b(prompt|mensaje|instrucciones)\s+(del\s+sistema|de\s+sistema|inicial(es)?|ocult[oa]s?|originales?)\b/iu',
        '/\b(reveal|show|print|repeat|display|dump|leak)\b.{0,30}\b(your|the)\b.{0,20}\b(prompt|instructions?|rules|configuration|knowledge)\b/iu',
        '/\b(muestra|mu[eé]strame|revela|repite|imprime|dime|ens[eé][ñn]ame)\b.{0,30}\b(tu|tus|el|las)\b.{0,20}\b(prompt|instrucciones|reglas|configuraci[oó]n)\b/iu',
        '/\b(you\s+are\s+now|from\s+now\s+on\s+you|pretend\s+(to\s+be|you\s+are)|act\s+as\s+(an?\s+)?(unrestricted|unfiltered|jailbroken|different))\b/iu',
        '/\b(ahora\s+eres|a\s+partir\s+de\s+ahora\s+(eres|ser[aá]s|act[uú]a)|finge\s+(ser|que\s+eres)|haz\s+como\s+si\s+fueras)\b/iu',
        '/\b(ara\s+ets|a\s+partir\s+d\'ara\s+(ets|seràs)|fes\s+veure\s+que\s+ets)\b/iu',
        '/\b(tu\s+es\s+maintenant|d[eé]sormais\s+tu\s+es|fais\s+semblant\s+d\'[eê]tre)\b/iu',
        '/\b(jailbreak|DAN\s+mode|developer\s+mode|modo\s+desarrollador|mode\s+d[eé]veloppeur|god\s+mode|sudo\s+mode)\b/iu',
        '/<\|?(im_start|im_end|system|endoftext)\|?>|\[\/?(INST|SYS|HANDOFF)\]|^\s*(system|assistant)\s*:/imu',
    ];

    protected const CODE_VERBS = '(write|generate|create|make\s+me|give\s+me|build\s+me|fix|debug|refactor|convert|translate|hazme|haz|escr[ií]be(me)?|gen[eé]ra(me)?|cr[eé]a(me)?|dame|p[aá]same|progr[aá]ma(me)?|arr[eé]gla(me)?|corr[ií]ge(me)?|depura(me)?|convierte(me)?|traduce(me)?|escriu(-me)?|fes(-me)?|dona\'m|corregeix(-me)?|[eé]cris(-moi)?|fais(-moi)?|g[eé]n[eè]re(-moi)?|cr[eé]e(-moi)?|donne(-moi)?|corrige(-moi)?)';

    protected const CODE_NOUNS = '(scripts?|c[oó]digo|codi|code|snippets?|funci[oó]n(es)?|funcions?|fonctions?|functions?|regex|regexp|expresi[oó]n\s+regular|sql|quer(y|ies)|requ[eê]tes?\s+sql|consulta\s+sql|shell|bash|comandos?|commandes?|commands?|macros?|f[oó]rmula\s+de\s+excel|excel\s+formula|algoritmo|algorisme|algorithme|algorithm)';

    protected const CODE_LANGS = '(python|javascript|typescript|js|ts|php|java|c\+\+|c#|golang|go|rust|ruby|kotlin|swift|bash|powershell|sql|html|css|laravel|react|vue|node(\.js)?|excel|vba|solidity)';

    /**
     * The only one and two character words a visitor really writes here. Any
     * other message made of nothing but tokens that short carries no question.
     */
    protected const SHORT_WORDS = [
        'ok', 'oka', 'si', 'sí', 'no', 'ya', 'va', 'hi', 'hey', 'ei', 'eh', 'ah',
        'ia', 'ai', 'ui', 'ux', 'bi', 'io', 'tu', 'yo', 'me', 'mi', 'el', 'la',
        'lo', 'de', 'un', 'es', 'oh', 'we', 'oc', 'oui', 'jo', 'et',
    ];

    /**
     * A message with nothing in it to answer: a single character, punctuation
     * alone, one character repeated, or only tokens of one or two letters that
     * are not words. It is NOT the same as an insult or a joke, which the scope
     * filter treats as small talk on purpose: here there is no question at all,
     * so the turn would spend a scope call, an embedding and a reply from the
     * main model on nothing. Checked before any HTTP call, so a visitor sending
     * letters one by one costs zero.
     */
    public function isNoise(string $message): bool
    {
        $text = trim(preg_replace('/\s+/u', ' ', $message));

        if (preg_match_all('/[\p{L}\p{N}]/u', $text) < 2) {
            return true;
        }

        if (preg_match('/^(.)\1*$/u', $text)) {
            return true;
        }

        foreach (preg_split('/[^\p{L}\p{N}@+]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (mb_strlen($token) > 2 || in_array($token, self::SHORT_WORDS, true)) {
                return false;
            }
        }

        return true;
    }

    public function isInjection(string $message): bool
    {
        foreach (self::INJECTION as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    public function isCodeRequest(string $message): bool
    {
        if (preg_match('/```|<\?php|<script\b/i', $message)) {
            return true;
        }

        if ($this->codeLineCount($message) >= 3) {
            return true;
        }

        $verbNoun = '/\b' . self::CODE_VERBS . '\b.{0,50}\b' . self::CODE_NOUNS . '\b/iu';
        $nounLang = '/\b' . self::CODE_NOUNS . '\b.{0,30}\b(in|en|con|with|using|de|amb)\s+' . self::CODE_LANGS . '\b/iu';
        $verbLang = '/\b' . self::CODE_VERBS . '\b.{0,40}\b(in|en|con|with|using|amb)\s+' . self::CODE_LANGS . '\b.{0,40}\b(que|that|which|to|para|per|pour)\b/iu';

        return preg_match($verbNoun, $message) || preg_match($nounLang, $message) || preg_match($verbLang, $message);
    }

    public function replyIsUnsafe(string $reply): bool
    {
        if (preg_match('/```|<\?php|<script\b|#!\//i', $reply)) {
            return true;
        }

        if ($this->codeLineCount($reply) >= 2) {
            return true;
        }

        // Section names of the prompt: a reply that quotes them is leaking it.
        return (bool) preg_match('/OUTPUT FORMAT \(STRICT\)|LIVE SITE CONTENT|ACCURACY GUARDRAILS|SCOPE \(STRICT|LEAD CAPTURE \(contact/i', $reply);
    }

    public function refusal(string $kind, string $locale): string
    {
        $kinds = ['code', 'injection', 'blocked', 'limit', 'noise', 'offtopic', 'offtopic_blocked'];

        return Larachat::persona()->text(in_array($kind, $kinds, true) ? $kind : 'injection', $locale);
    }

    public function strike(int $sessionId): int
    {
        $key = $this->strikeKey($sessionId);
        Cache::add($key, 0, self::STRIKE_TTL);

        return (int) Cache::increment($key);
    }

    public function isBlocked(int $sessionId): bool
    {
        return (int) Cache::get($this->strikeKey($sessionId), 0) >= $this->maxStrikes();
    }

    /**
     * Counts an off-topic message against both the conversation and the IP, so
     * opening a new chat does not reset it. Returns the higher of the two.
     */
    public function offTopicStrike(int $sessionId, ?string $ip): int
    {
        $count = 0;
        foreach ($this->offTopicKeys($sessionId, $ip) as $key) {
            Cache::add($key, 0, self::STRIKE_TTL);
            $count = max($count, (int) Cache::increment($key));
        }

        return $count;
    }

    public function isOffTopicBlocked(int $sessionId, ?string $ip): bool
    {
        foreach ($this->offTopicKeys($sessionId, $ip) as $key) {
            if ((int) Cache::get($key, 0) >= $this->maxOffTopic()) {
                return true;
            }
        }

        return false;
    }

    /**
     * One more message with nothing in it, counted per conversation and per
     * IP. Returns the longest current run: only messages in a row count, and
     * resetNoise() ends the run.
     */
    public function noiseStrike(int $sessionId, ?string $ip): int
    {
        $count = 0;
        foreach ($this->noiseKeys($sessionId, $ip) as $key) {
            Cache::add($key, 0, self::STRIKE_TTL);
            $count = max($count, (int) Cache::increment($key));
        }

        return $count;
    }

    /** A message with something in it ends the run of noise. */
    public function resetNoise(int $sessionId, ?string $ip): void
    {
        foreach ($this->noiseKeys($sessionId, $ip) as $key) {
            Cache::forget($key);
        }
    }

    /** Closes the chat for the day, as enough off-topic messages do. */
    public function blockOffTopic(int $sessionId, ?string $ip): void
    {
        foreach ($this->offTopicKeys($sessionId, $ip) as $key) {
            Cache::put($key, $this->maxOffTopic(), self::STRIKE_TTL);
        }
    }

    /**
     * Takes one model reply from today's budget for the whole site. False once
     * it is spent: the caller answers without the model until midnight.
     */
    public function takeReply(): bool
    {
        $key = 'larachat-replies:' . now()->toDateString();
        Cache::add($key, 0, now()->addDays(2));

        return (int) Cache::increment($key) <= $this->maxRepliesPerDay();
    }

    public function maxRepliesPerDay(): int
    {
        return (int) config('larachat.limits.replies_per_day', 500);
    }

    public function maxNoise(): int
    {
        return (int) config('larachat.limits.noise', 3);
    }

    public function maxStrikes(): int
    {
        return (int) config('larachat.limits.strikes', 3);
    }

    public function maxOffTopic(): int
    {
        return (int) config('larachat.limits.off_topic', 3);
    }

    /** @return list<string> */
    protected function offTopicKeys(int $sessionId, ?string $ip): array
    {
        return array_values(array_filter([
            'larachat-offtopic:s:' . $sessionId,
            $ip ? 'larachat-offtopic:ip:' . $ip : null,
        ]));
    }

    /** @return list<string> */
    protected function noiseKeys(int $sessionId, ?string $ip): array
    {
        return array_values(array_filter([
            'larachat-noise:s:' . $sessionId,
            $ip ? 'larachat-noise:ip:' . $ip : null,
        ]));
    }

    protected function strikeKey(int $sessionId): string
    {
        return 'larachat-strikes:' . $sessionId;
    }

    protected function codeLineCount(string $text): int
    {
        $pattern = '/^\s*('
            . 'def\s+\w+\s*\(|class\s+\w+[\s:({]|import\s+[\w.{*]|from\s+[\w.]+\s+import\b|#include\b|package\s+\w+;'
            . '|(public|private|protected)\s+(static\s+)?function\b|function\s*\w*\s*\(|(const|let|var)\s+\w+\s*=|\$\w+\s*=.+;'
            . '|(if|for|while)\s*\(.+\)\s*\{|}\s*(else)?\s*\{?\s*$|return\s+.+;|print\(|console\.log\(|echo\s+.+;'
            . '|SELECT\s+.+\s+FROM\b|INSERT\s+INTO\b|UPDATE\s+\w+\s+SET\b|CREATE\s+TABLE\b'
            . '|(sudo\s+)?(pip|npm|yarn|composer|apt(-get)?|brew)\s+install\b|curl\s+-|<\/?[a-z]+[^>]*>\s*$'
            . ')/imu';

        return preg_match_all($pattern, $text);
    }
}
