<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Manutenção. Tudo aqui é limpeza de artefato temporário — nada toca resposta,
// pontuação ou relatório emitido. Ver docs/operacao.md.

// PDFs de formulário parcial: o rascunho de ontem não serve hoje.
Schedule::command('vbmapp:prune-form-pdfs')->hourly();

// Fotos sem dono no banco. Fotos de aprendiz são dado sensível de criança:
// guardar o que já não pertence a ninguém é retenção sem base legal.
Schedule::command('saap:prune-orphan-uploads')->dailyAt('03:20');

// Lotes de fila já finalizados, e os jobs que falharam há mais de uma semana.
// Sem isso, `job_batches` e `failed_jobs` só crescem.
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=168')->weekly();
