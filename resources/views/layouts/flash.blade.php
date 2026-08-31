{{-- Mensagens de sessão — sucesso (status) e erro (erro). --}}
@php
    $mensagensDeStatus = [
        'profile-updated' => 'Perfil atualizado.',
        'aprendiz-criado' => 'Aprendiz cadastrado.',
        'aprendiz-atualizado' => 'Aprendiz atualizado.',
        'aprendiz-excluido' => 'Aprendiz excluído.',
    ];
@endphp

@if (session('status'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        <div class="flex items-center gap-3 rounded-lg border border-success/20 bg-success-soft px-4 py-3 text-sm text-success-ink">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span>{{ $mensagensDeStatus[session('status')] ?? session('status') }}</span>
        </div>
    </div>
@endif

@if (session('erro'))
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        <div class="flex items-center gap-3 rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>{{ session('erro') }}</span>
        </div>
    </div>
@endif
