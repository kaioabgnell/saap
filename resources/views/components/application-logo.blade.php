@props(['variante' => 'escura', 'class' => 'h-8 w-auto'])

{{-- Logo do SAAP. Duas variantes do mesmo arquivo:
     'escura' — wordmark navy, para fundo claro
     'clara'  — wordmark branco, para fundo navy; o check mantém o gradiente
     Ver public/images/ e .claude/specs/03-design-system.md

     A dimensão livre (a que o chamador NÃO informa) fica com 'auto', para
     manter a proporção da imagem: um `class="h-9"` deixa a largura livre,
     um `class="w-[130px]"` deixa a altura livre. Por isso o padrão da prop
     já inclui os dois — 'w-auto' fixo aqui, como antes, teria cancelado
     qualquer largura fixa que o chamador tentasse passar. --}}
<img src="{{ asset($variante === 'clara' ? 'images/logo-saap-claro.png' : 'images/logo-saap.png') }}"
     alt="SAAP — Sistema de Avaliação de Aprendiz"
     {{ $attributes->merge(['class' => $class]) }}>
