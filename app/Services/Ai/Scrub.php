<?php

namespace App\Services\Ai;

/**
 * Last line of defence for "no personal data in prompts": free text typed
 * by staff (a goal, a narrative summary) loses anything that looks like a
 * phone number or an email address before it is sent to Claude.
 */
class Scrub
{
    public static function text(string $text): string
    {
        $text = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/u', '[email removed]', $text) ?? '';

        return preg_replace('/\+?\d[\d\s().-]{6,}\d/', '[number removed]', $text) ?? '';
    }
}
