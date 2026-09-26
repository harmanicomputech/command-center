<?php

namespace App\Support;

/**
 * The SMS and USSD poll endpoints (Africa's Talking callbacks). Their URL
 * carries a secret derived from APP_KEY, shown on the survey page, since
 * Africa's Talking doesn't sign its callbacks.
 */
class SurveyPoll
{
    public static function token(): string
    {
        return substr(hash_hmac('sha256', 'command-center-poll', (string) config('app.key')), 0, 32);
    }

    public static function valid(string $token): bool
    {
        return hash_equals(self::token(), $token);
    }

    public static function ussdSurveyId(): ?int
    {
        $id = Settings::get('survey.ussd_id');

        return $id ? (int) $id : null;
    }

    public static function setUssdSurvey(int $id): void
    {
        Settings::set('survey.ussd_id', (string) $id);
    }
}
