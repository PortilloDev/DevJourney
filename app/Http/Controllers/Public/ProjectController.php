<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\SeoMetaService;
use Illuminate\Contracts\View\View;

class ProjectController extends Controller
{
    public function index(SeoMetaService $seo): View
    {
        $seo->set(
            title: 'Projects',
            description: 'A portfolio of side projects with tech stacks, architecture notes and lessons learned.',
        );

        $projects = Project::query()
            ->published()
            ->ordered()
            ->get();

        return view('public.projects.index', compact('projects'));
    }

    public function show(Project $project, SeoMetaService $seo): View
    {
        abort_unless($project->status === PostStatus::Published, 404);

        $seo->set(
            title: $project->title,
            description: $project->description,
            image: $project->featured_image ? asset('storage/'.$project->featured_image) : null,
            type: 'article',
            canonical: route('projects.show', $project),
        );

        return view('public.projects.show', compact('project'));
    }
}
