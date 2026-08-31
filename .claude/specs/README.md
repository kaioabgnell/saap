# Specs do SAAP

Documentação de implementação da v1. Cada fase é uma spec autocontida com
objetivo, pré-requisitos, entregáveis, tarefas e critérios de aceite.

## Como usar

1. Leia os documentos base (`00` a `04`) uma vez, antes de começar.
2. Ao iniciar uma fase, leia a spec dela inteira antes de escrever código.
3. Só marque a fase como concluída quando **todos** os critérios de aceite
   passarem — inclusive os testes automatizados.
4. Se a implementação divergir da spec, atualize a spec no mesmo commit.

## Documentos base

| # | Documento | Quando consultar |
| --- | --- | --- |
| 00 | [Visão geral](00-visao-geral.md) | Contexto de produto, escopo, glossário |
| 01 | [Arquitetura](01-arquitetura.md) | Onde cada classe mora, convenções |
| 02 | [Modelo de dados](02-modelo-de-dados.md) | Qualquer migration ou query |
| 03 | [Design system](03-design-system.md) | Qualquer tela ou componente |
| 04 | [Catálogo VB-MAPP](04-catalogo-vbmapp.md) | Áreas, marcos, tipos de item, pontuação |

## Ordem de execução

```
F0 ─┬─> F1 ─┬─> F3 ──> F4 ──> F5 ─┬─> F7 ──> F9
    │       │           │         │
    └─> F2 ─┘           └──> F6 ──┘
                        │
                        └──> F8
```

| Fase | Título | Depende de | Modelo | Estimativa |
| --- | --- | --- | --- | --- |
| [F0](fases/F0-fundacao.md) | Fundação | — | Sonnet 5 | ~1 semana · ✅ concluída |
| [F1](fases/F1-catalogo-vbmapp.md) | Catálogo VB-MAPP | F0 | **Opus 5** `max` | ~2 semanas · ⏳ revisão clínica pendente |
| [F2](fases/F2-perfil-e-aprendizes.md) | Perfil, aprendizes e painel | F0 | Sonnet 5 | ~1,5 semana · ✅ concluída |
| [F3](fases/F3-motor-avaliacao-nivel-1.md) | Motor de avaliação — nível 1 | F1, F2 | **Opus 5** `xhigh` | ~2,5 semanas · ✅ concluída |
| [F4](fases/F4-acervo-imagens-nivel-1.md) | Acervo de imagens do nível 1 | F3 | **Opus 5** `xhigh` | ~1,5 semana · ✅ concluída |
| [F5](fases/F5-niveis-2-e-3.md) | Níveis 2 e 3 | F3, F4 | Sonnet 5 | ~2 semanas · ✅ concluída |
| [F6](fases/F6-impressao-formulario.md) | Impressão do formulário | F3 | Sonnet 5 | ~1 semana · ✅ concluída |
| [F7](fases/F7-conclusao-e-relatorio.md) | Conclusão e relatório | F5, F6 | **Opus 5** `max` | ~2 semanas · ✅ concluída |
| [F8](fases/F8-api-rest.md) | API REST | F3 | Sonnet 5 | ~1,5 semana · ✅ concluída |
| [F9](fases/F9-acabamento-e-validacao.md) | Acabamento e validação | todas | **Opus 5** `xhigh` | ~1,5 semana · ⚠️ código concluído, validação em campo aberta |

**Paralelizações possíveis:** F2 roda junto com F1. F8 roda junto com F5–F7.
F6 roda junto com F5.

**Marco de piloto:** ao fim de **F4** o sistema já aplica o nível 1 completo.
Vale validar com aprendizes reais antes de investir em F5.

## Modelo por fase

A coluna **Modelo** acima é vinculante, não sugestão. A regra:

- **Opus 5** nas fases que decidem regra de domínio — F1, F3, F4, F7, F9.
  Erro nelas é silencioso e se propaga para todo laudo emitido.
- **Sonnet 5** nas fases que aplicam padrões já estabelecidos — F0, F2, F5, F6, F8.
  Entrega o mesmo resultado a 40% do custo quando a spec é detalhada.
- **Fable 5** (`claude-fable-5`) em um ponto só: a transcrição dos ~18 marcos que o
  parser da F1 não reconhecer. Raciocínio longo sobre texto ambíguo. Não vale o
  preço no resto do projeto.

Trocar de modelo no Claude Code é `/model sonnet` ou `/model opus`. **Troque na
fronteira entre fases, em sessão limpa** — nunca no meio de uma fase.

Duas exceções dentro de fases Sonnet, onde vale elevar para Opus só naquele arquivo:

| Fase | Tarefa | Por quê |
| --- | --- | --- |
| F2 | `app/Domain/Learner/Age.php` | Casos de borda de data alimentam todo laudo |
| F5 | Critério de pontuação da `matrix` | É decisão de domínio, não extensão de padrão |

## Como iniciar uma fase

Sessão limpa, modelo correto, e um único pedido:

```
leia .claude/specs/fases/F3-motor-avaliacao-nivel-1.md e implemente
```

A spec completa dada de antemão vale mais que um modelo maior com pedido vago.
Não fatie a fase em pedidos parciais — o percurso inteiro está no arquivo.

## Estrutura de uma spec de fase

```
# Fx — Título
## Objetivo          o que a fase entrega, em uma frase
## Pré-requisitos    fases e artefatos necessários
## Entregáveis       lista de arquivos e funcionalidades
## Tarefas           passo a passo de implementação
## Critérios de aceite   checklist verificável, com testes
## Riscos e decisões     armadilhas conhecidas desta fase
```
