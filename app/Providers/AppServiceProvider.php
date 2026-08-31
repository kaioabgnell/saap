<?php

namespace App\Providers;

use App\Domain\Vbmapp\Catalog\CatalogMemo;
use App\Models\Assessment;
use App\Models\Learner;
use App\Policies\AssessmentPolicy;
use App\Policies\LearnerPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageManager::class, fn () => new ImageManager(new Driver));

        // 'scoped', não 'singleton': a memória do catálogo tem de zerar a cada
        // requisição. Ver CatalogMemo — é o que impede os 65 cartões da tela do
        // nível de consultarem o driver de cache um por um.
        $this->app->scoped(CatalogMemo::class);
    }

    /**
     * Bootstrap any application services.
     *
     * O Laravel 12 não gera AuthServiceProvider — as policies são registradas
     * aqui. Ver .claude/specs/fases/F2-perfil-e-aprendizes.md.
     */
    public function boot(): void
    {
        // Política mínima de senha, valendo em cadastro, troca e recuperação
        // — os três chamam Password::defaults().
        //
        // Sem `uncompromised()` de propósito: ele consulta o HaveIBeenPwned a
        // cada cadastro, o que põe uma dependência de rede externa no caminho
        // do login e manda o prefixo do hash da senha para fora. Numa clínica
        // com uma psicóloga, o custo não paga o ganho.
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        Gate::policy(Learner::class, LearnerPolicy::class);
        Gate::policy(Assessment::class, AssessmentPolicy::class);
    }
}
