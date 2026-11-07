<?php

namespace App\Ai;

use App\Models\SelfProgramTrack;
use App\Support\AiSettings;
use App\Support\SelfProgramUnit;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Reads a week's self programme out of the message a supervisor already writes
 * for his students, and lays it on the week's fields.
 *
 * Nothing it returns is saved: it fills the boxes on the supervisor's page, he
 * reads them over, and he presses save. And nothing it returns is trusted — a
 * field the programme does not have, a unit the field does not take, or an
 * amount that is not a number is dropped before it reaches the page.
 */
class SelfProgramWeekReader implements Agent
{
    use Promptable;

    /**
     * The manager's configured provider chain, as every other agent here uses.
     *
     * @return array<string, string|null>
     */
    public function provider(): array
    {
        AiSettings::apply();

        return AiSettings::providerChain();
    }

    /** Whether there is a provider to ask at all. */
    public static function available(): bool
    {
        return AiSettings::hasKey(AiSettings::provider());
    }

    public function instructions(): Stringable|string
    {
        $fields = SelfProgramTrack::ordered()
            ->map(fn (SelfProgramTrack $track) => "- {$track->key}: {$track->label()} — units: "
                .implode('، ', array_keys($track->unitOptions()) ?: [$track->defaultUnit()]))
            ->implode("\n");

        return <<<INSTRUCTIONS
        You read the weekly self-study programme a supervisor at an Arabic Islamic
        academy sends his students, and sort it into the programme's fields.

        Return ONLY a JSON object. No prose, no markdown fence.

        Its keys are field keys from the list below; leave out any field the
        message does not mention. Each value is an object with:
          "description"  what to study, in Arabic, as the message says it
                         (book, lesson, pages, link text) — short, no field name
          "amount"       the week's quantity as a number, or 0 if none is given
          "unit"         one of that field's units below

        Fields:
        {$fields}

        Rules:
        - Time is always given in minutes: «ساعة ونصف» is 90, «ساعتان» is 120.
        - A range of pages (من ١٠ إلى ٢٥) is its count: 16.
        - Arabic-Indic digits are numbers like any other.
        - Do not invent content the message does not contain.
        INSTRUCTIONS;
    }

    /**
     * The message as field rows, ready to lay on the page; empty when it could
     * not be read, so the page simply stays as it was.
     *
     * @return array<string, array{description: string, amount: float, unit: string}>
     */
    public static function extract(string $message): array
    {
        $message = trim($message);

        if ($message === '') {
            return [];
        }

        try {
            $reply = (string) (new self)->prompt($message);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        return self::sanitize($reply);
    }

    /**
     * Keep only what the programme's fields can honour.
     *
     * @return array<string, array{description: string, amount: float, unit: string}>
     */
    public static function sanitize(string $reply): array
    {
        $decoded = json_decode(self::unwrap($reply), true);

        if (! is_array($decoded)) {
            return [];
        }

        $rows = [];

        foreach ($decoded as $key => $raw) {
            $track = is_string($key) ? SelfProgramTrack::findByKey($key) : null;

            if (! $track || ! is_array($raw)) {
                continue;
            }

            $description = is_string($raw['description'] ?? null) ? trim($raw['description']) : '';
            $amount = is_numeric($raw['amount'] ?? null) ? (float) $raw['amount'] : 0.0;
            $unit = $track->unitFor(is_string($raw['unit'] ?? null) ? $raw['unit'] : null);

            if ($description === '' && $amount <= 0) {
                continue;
            }

            $rows[$track->key] = [
                'description' => mb_substr($description, 0, 500),
                'amount' => SelfProgramUnit::normalise(min(max($amount, 0), 9999), $unit),
                'unit' => $unit,
            ];
        }

        return $rows;
    }

    /** Models wrap JSON in a fence or a sentence however firmly they are asked not to. */
    private static function unwrap(string $reply): string
    {
        $reply = trim($reply);

        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $reply, $matches)) {
            return trim($matches[1]);
        }

        $start = strpos($reply, '{');
        $end = strrpos($reply, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($reply, $start, $end - $start + 1);
        }

        return $reply;
    }
}
