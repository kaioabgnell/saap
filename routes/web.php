<?php

use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\LearnerPhotoController;
use App\Http\Controllers\PainelController;
use App\Http\Controllers\PrintFormController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Livewire\Admin\StimulusCuration;
use App\Livewire\Assessment\LevelBoard;
use Illuminate\Support\Facades\Route;

// A primeira tela do sistema é o login. Visitante não autenticado cai aqui;
// autenticado é levado ao painel pelo middleware 'guest' do fluxo de auth.
Route::get('/', fn () => redirect()->route('login'));

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/painel', [PainelController::class, 'index'])->name('painel');

    // Corrige o singular automático de 'aprendizes' ('aprendize'), que
    // quebraria o route model binding implícito de Learner $learner.
    Route::resource('aprendizes', LearnerController::class)
        ->parameters(['aprendizes' => 'learner']);
    Route::get('/aprendizes/{learner}/foto/{tamanho}', [LearnerPhotoController::class, 'show'])
        ->name('aprendizes.foto')
        ->middleware('signed');

    // Curadoria do acervo — trabalho de manutenção do catálogo, feito uma vez.
    Route::get('/admin/estimulos', StimulusCuration::class)->name('admin.estimulos');

    Route::post('/aprendizes/{learner}/avaliacoes', [AssessmentController::class, 'store'])
        ->name('avaliacoes.store');
    Route::get('/avaliacoes/{assessment}', [AssessmentController::class, 'show'])
        ->name('avaliacoes.show');
    Route::get('/avaliacoes/{assessment}/nivel/{level}', LevelBoard::class)
        ->whereIn('level', ['1', '2', '3'])
        ->name('avaliacoes.nivel');
    Route::get('/avaliacoes/{assessment}/formulario/{token}', [PrintFormController::class, 'show'])
        ->name('avaliacoes.formulario');

    Route::get('/avaliacoes/{assessment}/relatorio', [ReportController::class, 'show'])
        ->name('avaliacoes.relatorio');
    Route::get('/avaliacoes/{assessment}/relatorio.pdf', [ReportController::class, 'download'])
        ->name('avaliacoes.relatorio.pdf');
});

Route::middleware('auth')->group(function () {
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/perfil', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
