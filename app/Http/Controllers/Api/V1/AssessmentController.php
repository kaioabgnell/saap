<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Assessment\CancelAssessment;
use App\Application\Assessment\CompleteAssessment;
use App\Application\Assessment\CompleteLevel;
use App\Application\Assessment\OpenAssessment;
use App\Application\Assessment\SaveResponse;
use App\Application\Assessment\SaveResponseCommand;
use App\Application\Assessment\StartLevel;
use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\ResponseResource;
use App\Models\Assessment;
use App\Models\AssessmentLevel;
use App\Models\Learner;
use App\Models\ReportAccessLog;
use App\Models\Response as ResponseModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A API não reimplementa nada: cada endpoint de escrita chama o mesmo caso de
 * uso que o componente Livewire chama. Se algum método aqui tiver regra de
 * pontuação, está errado.
 */
class AssessmentController extends Controller
{
    public function store(Request $request): AssessmentResource
    {
        $dados = $request->validate([
            'learner_id' => ['required', 'integer', 'exists:learners,id'],
            'applied_on' => ['nullable', 'date'],
        ]);

        $learner = Learner::findOrFail($dados['learner_id']);
        $this->authorize('view', $learner);

        $assessment = app(OpenAssessment::class)->handle(
            $learner, $request->user(), $dados['applied_on'] ?? null,
        );

        return new AssessmentResource($assessment->load('levels', 'learner'));
    }

    public function show(Assessment $assessment): AssessmentResource
    {
        $this->authorize('view', $assessment);

        return new AssessmentResource($assessment->load('levels', 'learner', 'reportSnapshot'));
    }

    public function startLevel(Assessment $assessment, int $level): AssessmentResource
    {
        $this->authorize('applyGuided', $assessment);

        app(StartLevel::class)->handle($assessment, $level);

        return new AssessmentResource($assessment->refresh()->load('levels', 'learner'));
    }

    /** Os marcos do nível mais as respostas já gravadas. */
    public function levelItems(Assessment $assessment, int $level): JsonResponse
    {
        $this->authorize('view', $assessment);

        $itens = CatalogCache::level($level)->flatMap(fn ($area) => $area->items);

        $respostas = ResponseModel::with('entries')
            ->where('assessment_id', $assessment->id)
            ->whereIn('item_id', $itens->pluck('id'))
            ->get();

        return response()->json([
            'level' => $level,
            'areas' => CatalogCache::level($level)->map(fn ($area) => [
                'code' => $area->code,
                'name' => $area->name,
                'short_name' => $area->short_name,
                'items' => ItemResource::collection($area->items)->resolve(),
            ])->values(),
            'responses' => ResponseResource::collection($respostas)->resolve(),
        ]);
    }

    /**
     * Gravação idempotente — o índice único (assessment_id, item_id) faz o
     * updateOrCreate convergir. Reenviar o mesmo corpo produz o mesmo estado,
     * que é o que permite ao app offline esvaziar a fila sem medo.
     */
    public function saveResponse(Request $request, Assessment $assessment, int $itemId): JsonResponse
    {
        $this->authorize('applyGuided', $assessment);

        $dados = $request->validate([
            'entries' => ['array'],
            'entries.*.position' => ['required', 'integer', 'min:0'],
            'entries.*.stimulus_id' => ['nullable', 'integer'],
            'entries.*.list_key' => ['nullable', 'string', 'max:80'],
            'entries.*.column_key' => ['nullable', 'string', 'max:80'],
            'entries.*.text_value' => ['nullable', 'string', 'max:255'],
            'entries.*.is_checked' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'override' => ['nullable', 'array'],
            'override.score' => ['required_with:override', 'numeric', 'in:0,0.5,1'],
            'override.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $resultado = app(SaveResponse::class)->handle(new SaveResponseCommand(
            assessmentId: $assessment->id,
            itemId: $itemId,
            entries: $dados['entries'] ?? [],
            notes: $dados['notes'] ?? null,
            explicitScore: isset($dados['override']) ? (float) $dados['override']['score'] : null,
            overrideReason: $dados['override']['reason'] ?? null,
        ));

        return response()->json([
            'response' => [
                'item_id' => $itemId,
                'score' => $resultado->score->toFloat(),
                'computed_score' => $resultado->computedScore->toFloat(),
                'is_overridden' => $resultado->isOverridden,
                'answered' => $resultado->isAnswered,
                'needs_confirmation' => $resultado->needsConfirmation,
                'tally' => $resultado->tally,
            ],
            'progress' => [
                'area' => ['answered' => $resultado->areaProgress->answered, 'total' => $resultado->areaProgress->total],
                'level' => ['answered' => $resultado->levelProgress->answered, 'total' => $resultado->levelProgress->total],
                'assessment' => ['answered' => $resultado->assessmentProgress->answered, 'total' => $resultado->assessmentProgress->total],
            ],
        ]);
    }

    public function completeLevel(Assessment $assessment, int $level): AssessmentResource
    {
        $this->authorize('applyGuided', $assessment);

        $nivel = AssessmentLevel::where('assessment_id', $assessment->id)
            ->where('level', $level)
            ->firstOrFail();

        app(CompleteLevel::class)->handle($nivel);

        return new AssessmentResource($assessment->refresh()->load('levels', 'learner'));
    }

    public function complete(Assessment $assessment): JsonResponse
    {
        $this->authorize('update', $assessment);

        $snapshot = app(CompleteAssessment::class)->handle($assessment);

        return response()->json([
            'assessment' => (new AssessmentResource($assessment->refresh()->load('levels', 'learner')))->resolve(),
            'report' => ['content_hash' => $snapshot->content_hash, 'generated_at' => $snapshot->generated_at->toIso8601String()],
        ]);
    }

    public function cancel(Request $request, Assessment $assessment): AssessmentResource
    {
        $this->authorize('update', $assessment);

        $dados = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        app(CancelAssessment::class)->handle($assessment, $dados['reason']);

        return new AssessmentResource($assessment->refresh()->load('levels', 'learner'));
    }

    public function report(Assessment $assessment, Request $request): JsonResponse
    {
        $this->authorize('view', $assessment);

        $snapshot = $assessment->reportSnapshot;

        abort_if($snapshot === null, 404);

        // Mesma exigência da web: laudo lido é laudo registrado. O caminho da
        // API não pode ser a porta sem registro.
        ReportAccessLog::registrar($snapshot, $request->user(), ReportAccessLog::PELA_API, $request);

        return response()->json([
            'content_hash' => $snapshot->content_hash,
            'generated_at' => $snapshot->generated_at->toIso8601String(),
            'payload' => $snapshot->payload,
        ]);
    }
}
