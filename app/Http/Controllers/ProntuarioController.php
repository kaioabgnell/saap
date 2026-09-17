<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Learner;
use Illuminate\View\View;

/**
 * O prontuário do aprendiz — só leitura, sem escrita nenhuma.
 *
 * Não é tabela nem documento por sessão: é a tela que reúne o que já existe
 * espalhado — cadastro, avaliações e a linha do tempo de atendimentos. É o
 * sentido que a Resolução CFP 001/2009 dá à palavra.
 */
class ProntuarioController extends Controller
{
    public function show(Learner $learner): View
    {
        $this->authorize('view', $learner);

        $learner->load([
            'assessments' => fn ($q) => $q->latest('applied_on'),
            // Do mais recente ao mais antigo: quem abre o prontuário quer
            // saber o que aconteceu na última sessão, não na primeira.
            'appointments' => fn ($q) => $q->with('addenda')->orderByDesc('starts_at'),
        ]);

        return view('learners.prontuario', compact('learner'));
    }
}
