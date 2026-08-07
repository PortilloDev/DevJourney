<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\ChallengeDifficulty;
use App\Enums\ChallengeTopic;
use App\Enums\EnglishLevel;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Services\SeoMetaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    public function index(Request $request, SeoMetaService $seo): View
    {
        $seo->set(
            title: 'Challenges',
            description: 'Solved technical challenges: system design, DDD, CQRS, hexagonal architecture, SOLID and more — think first, then reveal the solution.',
        );

        $challenges = Challenge::query()
            ->published()
            ->with('tags')
            ->when($request->string('topic')->toString(), fn ($q, $topic) => $q->where('topic', $topic))
            ->when($request->string('difficulty')->toString(), fn ($q, $d) => $q->where('difficulty', $d))
            ->when($request->string('level')->toString(), fn ($q, $l) => $q->where('english_level', $l))
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        // Stats sidebar.
        $published = Challenge::query()->where('status', PostStatus::Published->value);
        $byTopic = (clone $published)
            ->selectRaw('topic, count(*) as total')
            ->groupBy('topic')
            ->pluck('total', 'topic');
        $byDifficulty = (clone $published)
            ->selectRaw('difficulty, count(*) as total')
            ->groupBy('difficulty')
            ->pluck('total', 'difficulty');

        return view('public.challenges.index', [
            'challenges' => $challenges,
            'topics' => ChallengeTopic::cases(),
            'difficulties' => ChallengeDifficulty::cases(),
            'levels' => EnglishLevel::cases(),
            'totalSolved' => (clone $published)->count(),
            'byTopic' => $byTopic,
            'byDifficulty' => $byDifficulty,
            'filters' => $request->only(['topic', 'difficulty', 'level']),
        ]);
    }

    public function show(Challenge $challenge, SeoMetaService $seo): View
    {
        abort_unless(
            $challenge->status === PostStatus::Published
                && $challenge->published_at?->isPast(),
            404,
        );

        $challenge->load('tags');

        $seo->set(
            title: $challenge->title,
            description: str($challenge->question_md)->stripTags()->limit(155)->toString(),
            type: 'article',
            canonical: route('challenges.show', $challenge),
        );

        return view('public.challenges.show', [
            'challenge' => $challenge,
        ]);
    }
}
