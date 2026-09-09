<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Assessment\OpenChartAssessment;
use App\Models\Assessment;
use App\Models\Learner;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Abertura de um lançamento retroativo: a avaliação em papel que já foi
 * aplicada e agora vai para o gráfico.
 *
 * Fica separado do `AssessmentController` porque o que se decide aqui é outra
 * coisa. Lá se abre uma aplicação que vai começar; aqui se declara uma que
 * terminou — com data no passado e níveis já conhecidos.
 */
class ChartEntryController extends Controller
{
    public function __construct(private readonly OpenChartAssessment $abrir) {}

    public function create(Learner $learner): View
    {
        $this->authorize('view', $learner);

        return view('assessments.lancamento', ['learner' => $learner]);
    }

    public function store(Request $request, Learner $learner): RedirectResponse
    {
        $this->authorize('view', $learner);

        $dados = $request->validate([
            'applied_on' => [
                'required', 'date', 'before_or_equal:today',
                'after_or_equal:'.$learner->birth_date->toDateString(),
            ],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*' => ['integer', 'in:1,2,3'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'confirmado' => ['nullable', 'boolean'],
        ], [
            'applied_on.before_or_equal' => 'A aplicação em papel não pode ser em data futura.',
            'applied_on.after_or_equal' => 'A aplicação não pode ser anterior ao nascimento do aprendiz.',
            'levels.required' => 'Escolha ao menos um nível para lançar.',
        ]);

        // Aviso, não bloqueio: reavaliar o mesmo aprendiz no mesmo dia é
        // improvável, mas não é proibido — e quem sabe se houve é o
        // psicólogo, não o sistema.
        $duplicada = Assessment::where('learner_id', $learner->id)
            ->whereDate('applied_on', $dados['applied_on'])
            ->exists();

        if ($duplicada && ! ($dados['confirmado'] ?? false)) {
            return back()
                ->withInput()
                ->with('aviso', 'Já existe avaliação deste aprendiz aplicada em '
                    .Carbon::parse($dados['applied_on'])->format('d/m/Y')
                    .'. Confirme para lançar outra.');
        }

        $assessment = $this->abrir->handle(
            $learner,
            $request->user(),
            $dados['applied_on'],
            $dados['levels'],
            $dados['observations'] ?? null,
        );

        return redirect()->route('avaliacoes.lancamento', $assessment);
    }
}
