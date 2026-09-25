<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Lga;
use App\Models\User;
use App\Models\Ward;
use App\Support\Icons;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The command palette's search: people, wards and LGAs, within the user's
 * area only.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = trim((string) $request->query('q'));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $results = [];

        foreach (Lga::query()->visibleTo($user)->where('name', 'like', $like)->orderBy('name')->limit(4)->get() as $lga) {
            $results[] = ['label' => $lga->name, 'detail' => "LGA · {$lga->wards_count} wards", 'url' => route('areas.lga', $lga), 'icon' => Icons::paths('map'), 'group' => 'LGAs'];
        }

        foreach (Ward::query()->visibleTo($user)->with('lga')->where('wards.name', 'like', $like)->orderBy('name')->limit(6)->get() as $ward) {
            $results[] = ['label' => $ward->name, 'detail' => "Ward · {$ward->lga->name}", 'url' => route('areas.ward', [$ward->lga, $ward->slug]), 'icon' => Icons::paths('map-pin'), 'group' => 'Wards'];
        }

        $digits = Phone::searchDigits($q);
        $people = $user->limitToArea(User::query(), 'users.ward_id')
            ->where(function ($query) use ($like, $digits) {
                $query->where('name', 'like', $like)->orWhere('email', 'like', $like);
                if (strlen($digits) >= 4) {
                    $query->orWhere('phone', 'like', '%'.$digits.'%');
                }
            })
            ->with('ward.lga', 'lga')->orderBy('name')->limit(6)->get();

        foreach ($people as $person) {
            $results[] = [
                'label' => $person->name,
                'detail' => $person->role->label().' · '.$person->areaLabel(),
                'url' => $this->personUrl($user, $person),
                'icon' => Icons::paths('user'),
                'group' => 'People',
            ];
        }

        return response()->json(['results' => $results]);
    }

    private function personUrl(User $viewer, User $person): string
    {
        return match (true) {
            Route::has('people.show') => route('people.show', $person),
            $viewer->isAdmin() => route('users', ['q' => $person->name]),
            $person->ward !== null => route('areas.ward', [$person->ward->lga, $person->ward->slug]),
            default => route('dashboard'),
        };
    }
}
