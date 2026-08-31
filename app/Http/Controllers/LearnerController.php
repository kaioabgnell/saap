<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Learner\CreateLearner;
use App\Application\Learner\UpdateLearner;
use App\Http\Requests\StoreLearnerRequest;
use App\Http\Requests\UpdateLearnerRequest;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearnerController extends Controller
{
    public function __construct(
        private readonly CreateLearner $createLearner,
        private readonly UpdateLearner $updateLearner,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Learner::class);

        $learners = $request->user()->learners()
            ->with(['assessments' => fn ($q) => $q->latest('applied_on')])
            ->orderBy('name')
            ->paginate(20);

        return view('learners.index', compact('learners'));
    }

    public function create(): View
    {
        $this->authorize('create', Learner::class);

        return view('learners.create');
    }

    public function store(StoreLearnerRequest $request): RedirectResponse
    {
        $learner = $this->createLearner->handle(
            $request->user(),
            $request->safe()->except('photo'),
            $request->file('photo'),
        );

        return redirect()->route('aprendizes.show', $learner)->with('status', 'aprendiz-criado');
    }

    public function show(Learner $learner): View
    {
        $this->authorize('view', $learner);

        $learner->load(['assessments' => fn ($q) => $q->latest('applied_on')]);

        return view('learners.show', compact('learner'));
    }

    public function edit(Learner $learner): View
    {
        $this->authorize('update', $learner);

        return view('learners.edit', compact('learner'));
    }

    public function update(UpdateLearnerRequest $request, Learner $learner): RedirectResponse
    {
        $this->updateLearner->handle(
            $learner,
            $request->safe()->except('photo'),
            $request->file('photo'),
        );

        return redirect()->route('aprendizes.show', $learner)->with('status', 'aprendiz-atualizado');
    }

    public function destroy(Learner $learner): RedirectResponse
    {
        $this->authorize('delete', $learner);

        // O laudo emitido precisa continuar rastreável ao aprendiz — ver
        // .claude/specs/fases/F2-perfil-e-aprendizes.md.
        if ($learner->hasCompletedAssessment()) {
            return redirect()->route('aprendizes.show', $learner)
                ->with('erro', 'Este aprendiz tem uma avaliação concluída e não pode ser excluído.');
        }

        $learner->delete();

        return redirect()->route('aprendizes.index')->with('status', 'aprendiz-excluido');
    }
}
