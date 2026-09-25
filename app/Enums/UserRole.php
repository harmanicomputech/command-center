<?php

namespace App\Enums;

/**
 * Who sees what. Admins and strategists see the whole state; an LGA leader
 * sees one LGA; ward coordinators and agents one ward (agents only their
 * own records inside it). Scope is always enforced on the server.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Strategist = 'strategist';
    case LgaLeader = 'lga_leader';
    case WardCoordinator = 'ward_coordinator';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Strategist => 'Strategist',
            self::LgaLeader => 'LGA leader',
            self::WardCoordinator => 'Ward coordinator',
            self::Agent => 'Field agent',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Everything, including users, settings, imports and exports',
            self::Strategist => 'All LGAs and analytics; messages, surveys and media',
            self::LgaLeader => 'One LGA: its coordinators, volunteers, tasks and analytics',
            self::WardCoordinator => 'One ward: its agents, tasks and verifying registrations',
            self::Agent => 'The field app: registering voters, tasks, issues and surveys',
        };
    }

    /** Higher ranks manage lower ones. */
    public function rank(): int
    {
        return match ($this) {
            self::Admin => 50,
            self::Strategist => 40,
            self::LgaLeader => 30,
            self::WardCoordinator => 20,
            self::Agent => 10,
        };
    }

    public function isStatewide(): bool
    {
        return in_array($this, [self::Admin, self::Strategist], true);
    }

    public function needsLga(): bool
    {
        return $this === self::LgaLeader;
    }

    public function needsWard(): bool
    {
        return in_array($this, [self::WardCoordinator, self::Agent], true);
    }

    /** Field users get the phone app; everyone else the Command Center. */
    public function usesFieldApp(): bool
    {
        return $this === self::Agent;
    }
}
