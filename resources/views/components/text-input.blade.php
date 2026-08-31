@props(['disabled' => false])

@php
    // Deriva a associação do próprio campo: quem usa o componente não precisa
    // repetir o nome em aria-describedby, e nenhum campo fica sem associação
    // por esquecimento.
    //
    // Varre todas as sacolas de erro, não só a padrão: o perfil usa sacolas
    // nomeadas (updatePassword, updateProfileInformation) e o campo ficaria
    // sem associação se olhássemos apenas $errors->has().
    $campo = $attributes->get('name') ?? $attributes->get('id');
    $temErro = false;

    if ($campo !== null) {
        $temErro = $errors->has($campo);

        foreach ($errors->getBags() as $sacola) {
            $temErro = $temErro || $sacola->has($campo);
        }
    }
@endphp

<input @disabled($disabled)
       @if ($temErro) aria-invalid="true" aria-describedby="{{ $campo }}-erro" @endif
       {{ $attributes->merge(['class' => 'rounded-md border-line shadow-sm focus:border-primary focus:ring-primary']) }}>
