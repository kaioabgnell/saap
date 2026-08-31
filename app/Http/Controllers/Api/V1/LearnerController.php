<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Learner\CreateLearner;
use App\Application\Learner\UpdateLearner;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLearnerRequest;
use App\Http\Requests\UpdateLearnerRequest;
use App\Http\Resources\AssessmentResource;
use App\Http\Resources\LearnerResource;
use App\Models\Learner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LearnerController extends Controller
{
    public function __construct(
        private readonly CreateLearner $createLearner,
        private readonly UpdateLearner $updateLearner,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Learner::class);

        return LearnerResource::collection(
            $request->user()->learners()->orderBy('name')->paginate(20),
        );
    }

    public function store(StoreLearnerRequest $request): LearnerResource
    {
        $learner = $this->createLearner->handle(
            $request->user(),
            $request->safe()->except('photo'),
            $request->file('photo'),
        );

        return new LearnerResource($learner);
    }

    public function show(Learner $learner): LearnerResource
    {
        $this->authorize('view', $learner);

        return new LearnerResource($learner->load(['assessments.levels']));
    }

    public function update(UpdateLearnerRequest $request, Learner $learner): LearnerResource
    {
        return new LearnerResource($this->updateLearner->handle(
            $learner,
            $request->safe()->except('photo'),
            $request->file('photo'),
        ));
    }

    public function assessments(Learner $learner): AnonymousResourceCollection
    {
        $this->authorize('view', $learner);

        return AssessmentResource::collection(
            $learner->assessments()->with('levels', 'learner')->latest('applied_on')->get(),
        );
    }
}
