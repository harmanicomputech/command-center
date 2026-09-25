<?php

namespace App\Services;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Str;

/**
 * Invite links: a coordinator adds an agent by name and phone and shares a
 * link (WhatsApp or SMS); the agent opens it and chooses their own PIN.
 * Only a hash of the token is stored.
 */
class Invitations
{
    public const DAYS = 14;

    /** A fresh link for the user (any earlier link stops working). */
    public function issue(User $user): string
    {
        $token = Str::random(40);

        $user->forceFill([
            'invite_token_hash' => hash('sha256', $token),
            'invite_expires_at' => now()->addDays(self::DAYS),
        ])->save();

        return route('invite', $token);
    }

    public function find(string $token): ?User
    {
        return User::query()
            ->where('invite_token_hash', hash('sha256', $token))
            ->where('invite_expires_at', '>', now())
            ->whereNull('disabled_at')
            ->first();
    }

    /** The message the coordinator sends with the link. */
    public function message(User $user, string $link, string $appName): string
    {
        return "Hello {$user->firstName()}, you're invited to the {$appName} field app. Open this link on your phone to choose your PIN: {$link} (it works for ".self::DAYS.' days). Then sign in with your phone number '.Phone::local($user->phone).'.';
    }
}
