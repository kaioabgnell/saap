<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Assessment\OpenAssessment;
use App\Domain\Assessment\AssessmentAlreadyOpenException;
use App\Models\Assessment;
use App\Models\Learner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(private readonly OpenAssessment $openAssessment) {}

    public function store(Request $request, Learner $learner): RedirectResponse
    {
        $this->authorize('view', $learner);

        $data = $request->validate([
            'applied_on' => ['nullable', 'date'],
        ]);

        try {
            $assessment = $this->openAssessment->handle($learner, $request->user(), $data['applied_on'] ?? null);
        } catch (AssessmentAlreadyOpenException $e) {
            return redirect()->route('aprendizes.show', $learner)->with('erro', $e->getMessage());
        }

        return redirect()->route('avaliacoes.show', $assessment);
    }

    /**
     * Detalhe da avaliação: escolha do nível a aplicar.
     *
     * Os três níveis são independentes — começar pelo 2 ou pelo 3 é caminho
     * normal, então todos ficam disponíveis o tempo todo.
     */
    public function show(Assessment $assessment): View
    {
        $this->authorize('view', $assessment);

        $assessment->load('learner', 'user', 'levels');

        return view('assessments.show', ['assessment' => $assessment]);
    }
}
