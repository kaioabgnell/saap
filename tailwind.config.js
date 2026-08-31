import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import franken from 'franken-ui';

/** Tokens do SAAP — ver .claude/specs/03-design-system.md
 *  Índigo nas ações; verde e âmbar reservados à semântica de pontuação. */
const saap = {
    primary: {
        DEFAULT: '#4338CA', // indigo-700
        hover: '#3730A3',
        soft: '#EEF2FF',
    },
    /* DEFAULT é a cor de PREENCHIMENTO — a mesma do gráfico de marcos, e a
       mesma do xlsx de referência. Como objeto gráfico, o mínimo AA é 3:1 e
       as duas passam (3,77 e 3,19 sobre branco).
       `ink` é a variante de TEXTO. Como texto, o mínimo é 4,5:1 e as cores de
       preenchimento reprovam — daí o par. Nunca use `text-success`/
       `text-warning` para texto; use `text-success-ink`/`text-warning-ink`. */
    success: { DEFAULT: '#059669', ink: '#047857', soft: '#ECFDF5' }, // 1 ponto
    warning: { DEFAULT: '#D97706', ink: '#B45309', soft: '#FFFBEB' }, // ½ ponto
    danger: { DEFAULT: '#BE123C', soft: '#FFF1F2' },
    ink: {
        DEFAULT: '#0F172A',
        muted: '#475569',
        // Era #94A3B8: 2,56:1 sobre branco, reprovado em AA. Aparece como
        // texto secundário em quase toda tela — idade do aprendiz, rótulo de
        // estímulo, "salvo às". Não é decoração.
        subtle: '#64748B',
    },
    surface: '#FFFFFF',
    canvas: '#F8FAFC',
    line: '#E2E8F0',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Http/Livewire/**/*.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            colors: saap,
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            fontVariantNumeric: {
                tabular: 'tabular-nums',
            },
        },
    },

    plugins: [
        forms,
        franken({
            preflight: false, // o preflight do Tailwind já roda; evitar dupla normalização
            layer: true,
        }),
    ],
};
