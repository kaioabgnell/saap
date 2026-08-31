{{-- Botão primário do sistema. Vinha do Breeze em cinza-800, caixa alta e
     32 px de altura: fora dos tokens do design system e abaixo do alvo de
     toque de 44 px exigido na F9. --}}
<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
