<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\PollingUnit;
use App\Models\User;
use App\Models\Ward;
use App\Support\Aggregates;
use App\Support\Audit;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo data for showing the app: fictional people (made-up names, 0800
 * numbers, which aren't mobile numbers), canvassing across all 13 LGAs,
 * the team, tasks, issues, events, a live survey, narratives, volunteers,
 * page posts, sample messages and a sent broadcast.
 *
 * Never any past election results: those would be invented real-world
 * figures, so zones rest on canvassing and surveys.
 *
 * Every row is listed in demo_records, and remove() deletes exactly those
 * rows (children first), so real work done meanwhile is untouched.
 */
class DemoData
{
    public const VOTERS = 10000;

    /** Removal order: children before parents. */
    private const TABLES = [
        'broadcast_messages', 'broadcasts', 'message_drafts', 'policy_documents',
        'survey_responses', 'survey_questions', 'surveys',
        'narrative_reports', 'narratives', 'news_items', 'news_feeds', 'page_posts',
        'task_reports', 'tasks', 'issues', 'events', 'influencers', 'rewards', 'volunteers',
        'voters', 'users',
    ];

    private const FIRST = [
        'Chinedu', 'Ngozi', 'Emeka', 'Chiamaka', 'Ifeanyi', 'Adaeze', 'Obinna', 'Nneka', 'Chukwuemeka', 'Amarachi',
        'Ikenna', 'Uchenna', 'Chidinma', 'Kelechi', 'Onyinye', 'Nnamdi', 'Ogechi', 'Chibuzor', 'Ifeoma', 'Okechukwu',
        'Ebere', 'Somtochukwu', 'Chioma', 'Tobechukwu', 'Nkechi', 'Ugochukwu', 'Adanna', 'Chinonso', 'Kosisochukwu', 'Onyekachi',
        'Ebuka', 'Nkiruka', 'Chijioke', 'Oluchi', 'Ejike', 'Uzoamaka', 'Kenechukwu', 'Ijeoma', 'Nwachukwu', 'Ogochukwu',
    ];

    private const LAST = [
        'Nwankwo', 'Okafor', 'Eze', 'Nwosu', 'Okeke', 'Nweke', 'Agu', 'Ogbonna', 'Uche', 'Onwe',
        'Nwibe', 'Aleke', 'Nwali', 'Igwe', 'Chukwu', 'Ede', 'Oko', 'Ogbu', 'Nnachi', 'Elom',
        'Nworie', 'Ali', 'Mbam', 'Nwaeze', 'Idenyi', 'Ekwe', 'Onu', 'Nwafor', 'Uzo', 'Ukpai',
    ];

    /**
     * A made-up leaning per LGA so the zone map has strongholds, swing and
     * weak areas to show. Illustrative only.
     *
     * @var array<string, array<int, int>> weights for strong, leaning, undecided, leaning_opponent, opponent
     */
    private const LEANING = [
        'strong' => [34, 28, 20, 10, 8],
        'swing' => [18, 22, 28, 18, 14],
        'weak' => [8, 12, 24, 26, 30],
    ];

    /** @var array<string, list<int>> */
    private array $created = [];

    /** How many voters to create (tests use fewer). */
    public int $voterCount = self::VOTERS;

    public static function loaded(): bool
    {
        return filled(Settings::get('demo.loaded_at'));
    }

    /**
     * @return array{password: string, pin: string, counts: array<string, int>}
     */
    public function load(User $by): array
    {
        if (self::loaded()) {
            throw new RuntimeException('Demo data is already loaded. Remove it first.');
        }
        if (Ward::query()->doesntExist()) {
            throw new RuntimeException('Load the polling unit register first (System).');
        }

        @set_time_limit(600);
        mt_srand(2027);
        $password = Str::password(14, symbols: false);
        $pin = (string) random_int(100000, 999999);
        $now = now();

        DB::transaction(function () use ($by, $password, $pin, $now) {
            $team = $this->team($password, $pin, $now);
            $voters = $this->voters($team, $now);
            $this->issues($team, $now);
            $this->tasks($team, $by, $now);
            $this->events($team, $by, $now);
            $this->influencers($team, $by, $now);
            $this->survey($team, $by, $now);
            $this->narratives($team, $by, $now);
            $this->volunteers($now);
            $this->media($by, $now);
            $this->messaging($by, $voters, $now);
            $this->rewards($team, $by, $now);

            foreach ($this->created as $table => $ids) {
                foreach (array_chunk($ids, 1000) as $chunk) {
                    DB::table('demo_records')->insert(array_map(fn ($id) => ['table_name' => $table, 'record_id' => $id], $chunk));
                }
            }
        });

        if (blank(Settings::get('brief.suggestion'))) {
            Settings::set('brief.suggestion', 'Demo suggestion: focus this week’s door-to-door on the swing wards of Abakaliki and Ikwo, where undecided voters are the largest group, and answer the market-levy rumour on local radio before it spreads.');
            Settings::set('brief.suggestion_at', now()->toIso8601String());
            Settings::set('demo.suggestion', '1');
        }
        Settings::set('demo.loaded_at', now()->toIso8601String());
        Settings::set('demo.password', Crypt::encryptString($password));
        Settings::set('demo.pin', Crypt::encryptString($pin));
        Aggregates::flush();

        $counts = collect($this->created)->map(fn ($ids) => count($ids))->all();
        Audit::record('system.demo', 'Loaded demo data', $counts, rows: array_sum($counts));

        return ['password' => $password, 'pin' => $pin, 'counts' => $counts];
    }

    /**
     * @return array<string, int> rows removed per table
     */
    public function remove(): array
    {
        @set_time_limit(600);
        $removed = [];

        DB::transaction(function () use (&$removed) {
            foreach (self::TABLES as $table) {
                $ids = DB::table('demo_records')->where('table_name', $table)->pluck('record_id');
                foreach ($ids->chunk(1000) as $chunk) {
                    $removed[$table] = ($removed[$table] ?? 0) + DB::table($table)->whereIn('id', $chunk->all())->delete();
                }
            }
            DB::table('demo_records')->delete();
        });

        if (Settings::get('demo.suggestion')) {
            Settings::set('brief.suggestion', null);
            Settings::set('brief.suggestion_at', null);
            Settings::set('demo.suggestion', null);
        }
        foreach (['demo.loaded_at', 'demo.password', 'demo.pin'] as $key) {
            Settings::set($key, null);
        }
        Aggregates::flush();
        Audit::record('system.demo', 'Removed demo data', $removed, rows: array_sum($removed));

        return $removed;
    }

    /**
     * Sign-ins for presenting, while demo data is loaded (admins only).
     *
     * @return array{password: string, pin: string, leader: ?string, coordinator: ?string, agent: ?string}|null
     */
    public static function logins(): ?array
    {
        if (! self::loaded()) {
            return null;
        }

        $ids = DB::table('demo_records')->where('table_name', 'users')->pluck('record_id');
        $users = User::query()->whereIn('id', $ids)->get();

        return [
            'password' => Crypt::decryptString((string) Settings::get('demo.password')),
            'pin' => Crypt::decryptString((string) Settings::get('demo.pin')),
            'leader' => $users->firstWhere('role.value', 'lga_leader')?->email,
            'coordinator' => $users->firstWhere('role.value', 'ward_coordinator')?->email,
            'agent' => ($agent = $users->firstWhere('role.value', 'agent')) ? Phone::local($agent->phone) : null,
        ];
    }

    // ---------------------------------------------------------------- builders

    /**
     * Four LGA leaders, a coordinator in 30 wards and 70 field agents.
     *
     * @return array{leaders: Collection, coordinators: Collection, agents: Collection}
     */
    private function team(string $password, string $pin, Carbon $now): array
    {
        $staffHash = Hash::make($password);
        $pinHash = Hash::make($pin);
        $wards = Ward::query()->orderBy('id')->get();
        $lgas = Lga::query()->orderBy('name')->get();
        $team = ['leaders' => collect(), 'coordinators' => collect(), 'agents' => collect()];

        foreach ($lgas->take(4) as $i => $lga) {
            $team['leaders']->push($this->user(['name' => $this->name(), 'email' => 'demo-leader-'.($i + 1).'@example.com', 'password' => $staffHash, 'role' => 'lga_leader', 'lga_id' => $lga->id], $now));
        }
        foreach ($wards->shuffle()->take(30)->values() as $i => $ward) {
            $team['coordinators']->push($this->user(['name' => $this->name(), 'email' => 'demo-coordinator-'.($i + 1).'@example.com', 'password' => $staffHash, 'role' => 'ward_coordinator', 'ward_id' => $ward->id, 'lga_id' => $ward->lga_id], $now));
        }
        foreach ($wards->shuffle()->take(70)->values() as $i => $ward) {
            $team['agents']->push($this->user(['name' => $this->name(), 'phone' => '+234800'.sprintf('%07d', 9000000 + $i), 'password' => $pinHash, 'role' => 'agent', 'ward_id' => $ward->id, 'lga_id' => $ward->lga_id], $now, $now->copy()->subHours(mt_rand(0, 60))));
        }

        return $team;
    }

    private function user(array $attributes, Carbon $now, ?Carbon $seen = null): User
    {
        $id = DB::table('users')->insertGetId([...$attributes, 'invite_accepted_at' => $now, 'last_login_at' => $seen, 'last_seen_at' => $seen, 'created_at' => $now->copy()->subDays(50), 'updated_at' => $now]);
        $this->created['users'][] = $id;

        return User::query()->findOrFail($id);
    }

    /**
     * Canvassed voters: more each week (a rising trend), today included.
     *
     * @return list<int>
     */
    private function voters(array $team, Carbon $now): array
    {
        $wards = Ward::query()->with('lga')->get()->keyBy('id');
        $units = PollingUnit::query()->get(['id', 'ward_id'])->groupBy('ward_id');
        $agentsByLga = $team['agents']->groupBy('lga_id');
        $leanings = $this->leanings();
        $levels = array_keys(config('canvass.support_levels'));
        $ages = array_keys(config('canvass.age_bands'));
        $jobs = ['farmer', 'farmer', 'farmer', 'trader', 'trader', 'civil_servant', 'student', 'artisan', 'transport', 'unemployed', 'other'];
        $issues = ['roads', 'roads', 'water', 'jobs', 'jobs', 'agriculture', 'agriculture', 'markets', 'security', 'health', 'education', 'electricity'];
        $verifiers = $team['coordinators'];
        $uuids = [];
        $rows = [];

        for ($i = 0; $i < $this->voterCount; $i++) {
            $ward = $wards->random();
            $agent = ($agentsByLga[$ward->lga_id] ?? $team['agents'])->random();
            $at = $this->recent($now, 45);
            $status = mt_rand(1, 100) <= 30 ? 'verified' : (mt_rand(1, 100) <= 3 ? 'invalid' : 'unverified');
            $hasPhone = mt_rand(1, 100) <= 72;
            $phone = '+234800'.sprintf('%07d', 1000000 + $i);
            $uuids[] = $uuid = (string) Str::uuid();

            $rows[] = [
                'uuid' => $uuid,
                'name' => $this->name(),
                'phone' => $hasPhone ? Crypt::encryptString($phone) : null,
                'phone_hash' => $hasPhone ? Phone::hash($phone) : null,
                'gender' => mt_rand(0, 1) ? 'female' : 'male',
                'age_band' => $ages[min(4, (int) floor(abs(mt_rand(0, 100) - mt_rand(0, 60)) / 22))],
                'occupation' => $jobs[array_rand($jobs)],
                'lga_id' => $ward->lga_id,
                'ward_id' => $ward->id,
                'polling_unit_id' => ($units[$ward->id] ?? collect())->random()?->id,
                'support_level' => $levels[$this->weighted(self::LEANING[$leanings[$ward->lga->name] ?? 'swing'])],
                'top_issue' => $issues[array_rand($issues)],
                'consent_at' => $at,
                'consent_version' => (string) config('canvass.consent_version'),
                'captured_by' => $agent->id,
                'captured_at' => $at,
                'status' => $status,
                'verified_by' => $status === 'unverified' ? null : $verifiers->random()->id,
                'verified_at' => $status === 'unverified' ? null : $at->copy()->addHours(mt_rand(2, 30))->min($now),
                'created_at' => $at,
                'updated_at' => $at,
            ];

            if (count($rows) === 500) {
                DB::table('voters')->insert($rows);
                $rows = [];
            }
        }
        $rows && DB::table('voters')->insert($rows);

        $ids = [];
        foreach (array_chunk($uuids, 1000) as $chunk) {
            array_push($ids, ...DB::table('voters')->whereIn('uuid', $chunk)->pluck('id')->all());
        }
        $this->created['voters'] = $ids;

        return $ids;
    }

    private function issues(array $team, Carbon $now): void
    {
        $examples = [
            'road' => ['The road to the market is cut by erosion; tricycles can’t pass after rain.', 'Potholes on the main road make the journey to the health centre very slow.'],
            'water' => ['The only borehole in the village has been broken for weeks.', 'Women walk more than an hour to fetch water from the stream.'],
            'electricity' => ['No light for over a month since the transformer blew.'],
            'health' => ['The health centre has no nurse on weekends.', 'No drugs at the primary health centre.'],
            'school' => ['The primary school roof leaks and pupils sit on the floor.'],
            'security' => ['Farmers report attacks on the way to their farms at night.'],
            'flooding' => ['Flooding destroys farmland near the river every rainy season.'],
            'market' => ['Traders complain of high levies and no toilets at the market.'],
        ];
        $statuses = ['new', 'new', 'new', 'noted', 'noted', 'used', 'addressed'];
        $severities = ['low', 'medium', 'medium', 'high', 'high', 'critical'];

        for ($i = 0; $i < 360; $i++) {
            $agent = $team['agents']->random();
            $category = array_rand($examples);
            $at = $this->recent($now, 56);
            $this->insert('issues', [
                'uuid' => (string) Str::uuid(),
                'category' => $category,
                'description' => $examples[$category][array_rand($examples[$category])],
                'severity' => $severities[array_rand($severities)],
                'people_affected' => mt_rand(0, 3) ? mt_rand(2, 80) * 10 : null,
                'lga_id' => $agent->lga_id,
                'ward_id' => $agent->ward_id,
                'reported_by' => $agent->id,
                'reported_at' => $at,
                'status' => $statuses[array_rand($statuses)],
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    private function tasks(array $team, User $by, Carbon $now): void
    {
        $titles = [
            'door_to_door' => ['Door-to-door in the market area', 'Visit every compound on the main street'],
            'community_meeting' => ['Meet the youth group after church', 'Town union meeting: introduce the campaign'],
            'market_storm' => ['Market storm on market day'],
            'flyers' => ['Share flyers at the motor park'],
            'follow_up' => ['Call back undecided voters from last week'],
        ];

        foreach ($team['agents']->shuffle()->take(45) as $agent) {
            $type = array_rand($titles);
            $due = $now->copy()->addDays(mt_rand(-10, 12))->startOfDay();
            $target = mt_rand(3, 10) * 10;
            $taskId = $this->insert('tasks', [
                'title' => $titles[$type][array_rand($titles[$type])],
                'type' => $type,
                'description' => 'Demo task.',
                'lga_id' => $agent->lga_id,
                'ward_id' => $agent->ward_id,
                'assignee_id' => mt_rand(0, 3) ? $agent->id : null,
                'due_on' => $due->toDateString(),
                'target' => $target,
                'target_unit' => $type === 'flyers' ? 'flyers' : 'households',
                'proof' => 'count',
                'status' => $due->lt($now->copy()->subDays(5)) ? 'closed' : 'open',
                'created_by' => $by->id,
                'created_at' => $due->copy()->subDays(7),
                'updated_at' => $now,
            ]);

            foreach (range(1, mt_rand(0, 3)) as $n) {
                $at = $due->copy()->subDays(4 - $n)->setTime(mt_rand(9, 18), mt_rand(0, 59));
                if ($at->gt($now)) {
                    break;
                }
                $this->insert('task_reports', [
                    'uuid' => (string) Str::uuid(),
                    'task_id' => $taskId,
                    'user_id' => $agent->id,
                    'count' => (int) round($target * $n / 3),
                    'done' => $n === 3,
                    'reported_at' => $at,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }
        }
    }

    private function events(array $team, User $by, Carbon $now): void
    {
        $types = ['meeting' => 'Ward meeting', 'town_hall' => 'Town hall', 'rally' => 'Rally', 'training' => 'Agent training', 'stakeholder' => 'Stakeholder visit'];

        foreach (range(1, 28) as $i) {
            $ward = Ward::query()->inRandomOrder()->first();
            $type = array_rand($types);
            $starts = $now->copy()->addDays(mt_rand(-30, 21))->setTime(mt_rand(9, 17), 0);
            $held = $starts->lt($now);
            $expected = mt_rand(3, 30) * 10;
            $eventId = $this->insert('events', [
                'title' => $types[$type].', '.$ward->name,
                'type' => $type,
                'lga_id' => $ward->lga_id,
                'ward_id' => $ward->id,
                'starts_at' => $starts,
                'venue' => 'Community hall',
                'expected' => $expected,
                'status' => $held ? 'held' : 'planned',
                'attendance' => $held ? (int) round($expected * mt_rand(60, 130) / 100) : null,
                'notes' => $held ? 'Demo event. Good turnout; questions about roads and water.' : null,
                'created_by' => $by->id,
                'created_at' => $starts->copy()->subDays(10)->min($now),
                'updated_at' => $now,
            ]);

            $attendees = $team['agents']->where('lga_id', $ward->lga_id)->take(4)->pluck('id');
            foreach ($attendees as $userId) {
                DB::table('event_user')->insert(['event_id' => $eventId, 'user_id' => $userId, 'created_at' => $now]);
            }
        }
    }

    private function influencers(array $team, User $by, Carbon $now): void
    {
        $kinds = ['traditional_ruler' => 'Traditional ruler of', 'church' => 'Parish in', 'town_union' => 'Town union of', 'market_association' => 'Market women of', 'youth_group' => 'Youth forum of', 'age_grade' => 'Age grade of'];
        $relationships = ['ally', 'friendly', 'friendly', 'neutral', 'neutral', 'unfriendly', 'unknown'];

        foreach (Ward::query()->inRandomOrder()->limit(55)->get() as $ward) {
            $kind = array_rand($kinds);
            $this->insert('influencers', [
                'ward_id' => $ward->id,
                'kind' => $kind,
                'name' => $kinds[$kind].' '.$ward->name,
                'contact_name' => $this->name(),
                'relationship' => $relationships[array_rand($relationships)],
                'notes' => 'Demo contact.',
                'created_by' => $by->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function survey(array $team, User $by, Carbon $now): void
    {
        $surveyId = $this->insert('surveys', [
            'title' => 'Ebonyi pulse (demo)',
            'intro' => 'A short survey on what matters most in your community.',
            'status' => 'live',
            'channels' => json_encode(['field', 'web', 'sms']),
            'web_token' => Str::random(32),
            'created_by' => $by->id,
            'created_at' => $now->copy()->subDays(21),
            'updated_at' => $now,
        ]);

        $questions = [];
        foreach ([
            ['intention', 'If the election were held today, who would you vote for?', null],
            ['issue', 'Which issue matters most to you?', null],
            ['rating', 'How well are roads in your area maintained? (1–5)', null],
            ['single', 'Where do you get most of your news?', ['Radio', 'WhatsApp', 'Facebook', 'Church or mosque', 'Market and friends']],
        ] as $position => [$type, $prompt, $options]) {
            $questions[$type] = $this->insert('survey_questions', [
                'survey_id' => $surveyId, 'position' => $position + 1, 'type' => $type, 'prompt' => $prompt,
                'options' => $options ? json_encode($options) : null, 'required' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $leanings = $this->leanings();
        $wards = Ward::query()->with('lga')->get();
        $intentions = array_keys(config('surveys.intention_options'));
        $issues = array_keys(config('canvass.issues'));
        $ages = array_keys(config('canvass.age_bands'));

        for ($i = 0; $i < 900; $i++) {
            $ward = $wards->random();
            $at = $this->recent($now, 20);
            $channel = ['field', 'field', 'field', 'web', 'sms'][mt_rand(0, 4)];
            $this->insert('survey_responses', [
                'uuid' => (string) Str::uuid(),
                'survey_id' => $surveyId,
                'channel' => $channel,
                'lga_id' => $ward->lga_id,
                'ward_id' => $ward->id,
                'gender' => mt_rand(0, 1) ? 'female' : 'male',
                'age_band' => $ages[mt_rand(0, 4)],
                'occupation' => ['farmer', 'trader', 'student', 'artisan', 'civil_servant'][mt_rand(0, 4)],
                'collected_by' => $channel === 'field' ? $team['agents']->random()->id : null,
                'answers' => json_encode([
                    (string) $questions['intention'] => $intentions[$this->weighted(self::LEANING[$leanings[$ward->lga->name] ?? 'swing'])],
                    (string) $questions['issue'] => $issues[mt_rand(0, count($issues) - 2)],
                    (string) $questions['rating'] => (string) mt_rand(1, 4),
                    (string) $questions['single'] => (string) mt_rand(1, 5),
                ]),
                'answered_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
        }
    }

    private function narratives(array $team, User $by, Carbon $now): void
    {
        $stories = [
            ['Rumour: the market levy will double', 'markets', 'negative', 'watching', 'People at the market say stall fees will double after the election.', 26],
            ['Praise for the borehole repairs pledge', 'water', 'positive', 'new', 'Women at the stream welcomed the promise to fix broken boreholes quickly.', 12],
            ['Claim that the candidate won’t campaign in the south', 'candidate', 'negative', 'responding', 'A caller on local radio said the candidate has never visited the southern LGAs.', 15],
            ['Youth excited about skills centres', 'jobs', 'positive', 'watching', 'Young people on WhatsApp are sharing the skills-centre plan.', 9],
            ['Fear of farm attacks at night', 'security', 'negative', 'new', 'Farmers say they are afraid to go to farms early because of attacks.', 11],
        ];
        $sources = array_keys(config('messaging.sources'));

        foreach ($stories as [$title, $topic, $tone, $status, $summary, $count]) {
            $narrativeId = $this->insert('narratives', ['title' => $title, 'summary' => $summary, 'topic' => $topic, 'tone' => $tone, 'status' => $status, 'created_by' => $by->id, 'created_at' => $now->copy()->subDays(14), 'updated_at' => $now]);
            for ($i = 0; $i < $count; $i++) {
                $this->narrativeReport($team, $narrativeId, $summary, $topic, $tone, $sources, $this->recent($now, 14));
            }
        }

        // A few waiting in the inbox, to show grouping.
        foreach (['A voice note says voter cards will be collected by agents', 'A post claims the new road will skip our village', 'People say the fertiliser plan is only for party members'] as $summary) {
            $this->narrativeReport($team, null, $summary, 'election', 'negative', $sources, $this->recent($now, 3));
        }
    }

    private function narrativeReport(array $team, ?int $narrativeId, string $summary, string $topic, string $tone, array $sources, Carbon $at): void
    {
        $agent = $team['agents']->random();
        $this->insert('narrative_reports', [
            'uuid' => (string) Str::uuid(), 'narrative_id' => $narrativeId, 'source' => $sources[array_rand($sources)],
            'summary' => $summary, 'topic' => $topic, 'tone' => $tone, 'lga_id' => $agent->lga_id, 'ward_id' => $agent->ward_id,
            'reported_by' => $agent->id, 'seen_at' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function volunteers(Carbon $now): void
    {
        $help = array_keys(config('volunteers.help'));
        $statuses = ['new', 'new', 'new', 'contacted', 'contacted', 'not_now'];

        foreach (Ward::query()->inRandomOrder()->limit(45)->get() as $i => $ward) {
            $phone = '+234800'.sprintf('%07d', 8000000 + $i);
            $at = $this->recent($now, 20);
            $this->insert('volunteers', [
                'name' => $this->name(), 'phone' => Crypt::encryptString($phone), 'phone_hash' => Phone::hash($phone),
                'lga_id' => $ward->lga_id, 'ward_id' => mt_rand(0, 3) ? $ward->id : null,
                'help' => json_encode(array_values(array_unique([$help[array_rand($help)], $help[array_rand($help)]]))),
                'source' => mt_rand(0, 1) ? 'website' : 'join page', 'consent_at' => $at,
                'consent_version' => (string) config('volunteers.consent_version'), 'status' => $statuses[array_rand($statuses)],
                'created_at' => $at, 'updated_at' => $at,
            ]);
        }
    }

    private function media(User $by, Carbon $now): void
    {
        $feedId = $this->insert('news_feeds', ['name' => 'Sample feed (demo)', 'url' => 'https://example.com/feed', 'active' => false, 'fetched_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([
            ['[Demo] Governorship race: candidates court rice farmers ahead of planting', ['Ebonyi', 'governorship']],
            ['[Demo] Residents ask for the erosion site on the main road to be fixed before the rains', ['Abakaliki']],
            ['[Demo] Opinion: what young people want from the 2027 elections', null],
            ['[Demo] Traders welcome new market stalls but worry about fees', null],
            ['[Demo] The issues that will decide the Ebonyi governorship race', ['Ebonyi', 'governorship']],
        ] as $i => [$title, $keywords]) {
            $this->insert('news_items', ['news_feed_id' => $feedId, 'guid_hash' => hash('sha256', 'demo|'.$title), 'title' => $title, 'link' => null, 'summary' => 'Sample headline for the demo; not a real story.', 'keywords' => $keywords ? json_encode($keywords) : null, 'published_at' => $now->copy()->subHours($i * 7 + 2), 'starred' => $i === 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        $posts = [['Town hall in Izzi: thank you for coming', 'candidate'], ['Our plan for rural roads', 'roads'], ['Meet the youth skills team', 'jobs'], ['Boreholes: a promise we will keep', 'water'], ['Farmers first: fertiliser in every ward', 'agriculture']];
        foreach (range(0, 17) as $i) {
            [$text, $topic] = $posts[$i % count($posts)];
            $this->insert('page_posts', [
                'platform' => $i % 4 ? 'facebook' : 'instagram', 'posted_at' => $now->copy()->subDays($i * 2 + 1)->setTime(18, 0), 'text' => $text.' (demo)', 'topic' => $topic,
                'reach' => mt_rand(20, 140) * 100, 'reactions' => mt_rand(80, 900), 'comments' => mt_rand(10, 160), 'shares' => mt_rand(5, 120), 'created_by' => $by->id, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function messaging(User $by, array $voterIds, Carbon $now): void
    {
        foreach ([
            ['agriculture', 'Sample: fertiliser and farm support', 'Sample policy text for the demo. Replace it with the campaign’s real position: what the candidate will do for farmers, where, and the first steps.'],
            ['roads', 'Sample: rural roads', 'Sample policy text for the demo. Replace it with the campaign’s real plan for feeder roads and erosion control.'],
            ['jobs', 'Sample: youth jobs and skills', 'Sample policy text for the demo. Replace it with the campaign’s real plan for skills centres and apprenticeships.'],
        ] as [$topic, $title, $body]) {
            $this->insert('policy_documents', ['topic' => $topic, 'title' => $title, 'body' => $body, 'active' => false, 'updated_by' => $by->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        $izzi = Lga::query()->where('name', 'Izzi')->value('id');
        $this->insert('message_drafts', [
            'filters' => json_encode(['occupation' => ['farmer'], 'lga_id' => [$izzi]]), 'audience' => 'Occupation: Farmer · LGA: Izzi', 'audience_size' => 612,
            'goal' => 'Invite Izzi farmers to Saturday’s town hall (demo)', 'channel' => 'sms', 'language' => 'en', 'tone' => 'hopeful', 'status' => 'ready',
            'variants' => json_encode([
                ['text' => 'Izzi farmers: come and tell us what your farms need. Town hall, Saturday 10am, community hall. Reply STOP to opt out', 'angle' => 'Listening first'],
                ['text' => 'Your farm feeds Ebonyi. Join us Saturday 10am at the community hall to plan better support for farmers. Reply STOP to opt out', 'angle' => 'Pride and an invitation'],
                ['text' => 'Farmers in Izzi told us about the roads to market. Hear the plan on Saturday, 10am, community hall. Reply STOP to opt out', 'angle' => 'Uses the field reports'],
            ]),
            'created_by' => $by->id, 'created_at' => $now->copy()->subHours(5), 'updated_at' => $now,
        ]);
        $draftId = $this->insert('message_drafts', [
            'filters' => json_encode(['age_band' => ['18-24', '25-34']]), 'audience' => 'Age: 18–24, 25–34', 'audience_size' => 3900,
            'goal' => 'Tell young people about skills training (demo)', 'channel' => 'sms', 'language' => 'en', 'tone' => 'practical', 'status' => 'approved',
            'variants' => json_encode([['text' => 'Young Ebonyi: skills training and apprenticeships are coming to your LGA. Register your interest with your ward coordinator. Reply STOP to opt out', 'angle' => 'Direct']]),
            'chosen' => 0, 'final_text' => 'Young Ebonyi: skills training and apprenticeships are coming to your LGA. Register your interest with your ward coordinator. Reply STOP to opt out',
            'created_by' => $by->id, 'approved_by' => $by->id, 'approved_at' => $now->copy()->subDays(2), 'created_at' => $now->copy()->subDays(2), 'updated_at' => $now,
        ]);

        $recipients = array_slice($voterIds, 0, 400);
        $broadcastId = $this->insert('broadcasts', [
            'title' => 'Youth skills (demo)', 'message' => 'Young Ebonyi: skills training and apprenticeships are coming to your LGA. Register your interest with your ward coordinator. Reply STOP to opt out',
            'audience' => json_encode(['type' => 'voters', 'filters' => ['age_band' => ['18-24', '25-34']]]), 'audience_label' => 'Voters: Age: 18–24, 25–34 (demo, not sent)',
            'message_draft_id' => $draftId, 'status' => 'sent', 'recipients' => count($recipients), 'parts' => 1, 'created_by' => $by->id, 'sent_by' => $by->id,
            'started_at' => $now->copy()->subDays(2), 'finished_at' => $now->copy()->subDays(2), 'created_at' => $now->copy()->subDays(2), 'updated_at' => $now,
        ]);
        foreach ($recipients as $i => $voterId) {
            $status = $i % 20 === 0 ? 'failed' : ($i % 9 === 0 ? 'sent' : 'delivered');
            $this->insert('broadcast_messages', [
                'broadcast_id' => $broadcastId, 'recipient_type' => 'voter', 'recipient_id' => $voterId, 'status' => $status,
                'failure_reason' => $status === 'failed' ? ($i % 40 === 0 ? 'AbsentSubscriber' : 'UserInBlacklist') : null,
                'sent_at' => $now->copy()->subDays(2), 'delivered_at' => $status === 'delivered' ? $now->copy()->subDays(2) : null, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function rewards(array $team, User $by, Carbon $now): void
    {
        foreach ($team['agents']->take(3) as $agent) {
            $this->insert('rewards', ['user_id' => $agent->id, 'week_of' => Points::weekStart()->subWeek()->toDateString(), 'kind' => 'airtime', 'description' => 'Demo reward: top registrations last week', 'given_by' => $by->id, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    // ----------------------------------------------------------------- helpers

    private function insert(string $table, array $row): int
    {
        $id = DB::table($table)->insertGetId($row);
        $this->created[$table][] = $id;

        return $id;
    }

    private function name(): string
    {
        return self::FIRST[mt_rand(0, count(self::FIRST) - 1)].' '.self::LAST[mt_rand(0, count(self::LAST) - 1)];
    }

    /** A time in the last $days days, more of them recent (a rising trend). */
    private function recent(Carbon $now, int $days): Carbon
    {
        $ago = (int) floor($days * (1 - sqrt(mt_rand(0, 1000) / 1000)));

        return $now->copy()->subDays($ago)->setTime(mt_rand(7, 19), mt_rand(0, 59))->min($now);
    }

    /** @param list<int> $weights */
    private function weighted(array $weights): int
    {
        $pick = mt_rand(1, array_sum($weights));
        foreach ($weights as $index => $weight) {
            if (($pick -= $weight) <= 0) {
                return $index;
            }
        }

        return count($weights) - 1;
    }

    /** @return array<string, string> LGA name → strong | swing | weak (made up for the demo) */
    private function leanings(): array
    {
        return [
            'Afikpo North' => 'strong', 'Ohaozara' => 'strong', 'Ivo' => 'strong', 'Onicha' => 'strong',
            'Abakaliki' => 'swing', 'Ebonyi' => 'swing', 'Ezza North' => 'swing', 'Ezza South' => 'swing', 'Ikwo' => 'swing',
            'Izzi' => 'weak', 'Ishielu' => 'weak', 'Ohaukwu' => 'weak', 'Afikpo South' => 'weak',
        ];
    }
}
