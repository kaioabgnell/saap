# F7 — Conclusão e relatório

> **Depende de:** F5, F6 · **Estimativa:** ~2 semanas · **Habilita:** F9
> **Modelo:** Opus 5 · effort `max` (`claude-opus-5`) — Imutabilidade, snapshot congelado e um gráfico que precisa renderizar igual em HTML e dompdf. Correção importa mais que custo.

> **Status: concluída.** Registro das decisões tomadas na execução, ao final.

## Objetivo

O psicólogo conclui a avaliação, ela se torna imutável, e o sistema gera o
relatório com o gráfico de marcos no padrão do VB-MAPP.

## Entregáveis

- `app/Application/Assessment/CompleteAssessment.php`
- `app/Domain/Vbmapp/Report/{MilestoneChart,ReportPayload}.php`
- `app/Jobs/GenerateReportPdf.php`
- Modal de confirmação de conclusão
- Tela de relatório em HTML e PDF
- Gráfico de marcos em tabela, renderizável nos dois meios
- Travamento efetivo

## Tarefas

### 1. Condição de conclusão

O botão **`Concluir avaliação`** só habilita quando **todos os níveis iniciados**
estão `completed`.

Níveis nunca iniciados **não** bloqueiam: um aprendiz avaliado só no nível 2 tem
avaliação concluível com 60 de 60. O total é sobre o iniciado, não sobre 170.

### 2. Modal de confirmação

Deixe a consequência explícita, sem eufemismo:

```
Concluir avaliação

Depois de concluída, nenhuma resposta pode ser alterada.
O relatório será gerado e ficará disponível permanentemente.

Aprendiz:  Kaleo — 4 anos e 7 meses
Aplicação: 08/07/2026
Níveis:    Nível 1 concluído · 45 de 45
Pontuação: 33,5 de 45

          [ Voltar ]   [ Concluir avaliação ]
```

`Concluir` sólido, `Voltar` fantasma — pesos nunca iguais (`03-design-system.md`).

### 3. Conclusão e travamento

`CompleteAssessment`, em transação:

1. Autoriza e revalida a condição no servidor — nunca confie no estado da tela
2. `status = completed`, `completed_at = now()`
3. **`locked_at = now()`**
4. Monta o `ReportPayload` e grava `report_snapshots` com `content_hash`
5. Enfileira `GenerateReportPdf`

A partir daí `AssessmentPolicy::update()` devolve `false`. A trava vive na
policy, não em `if` espalhado.

### 4. Snapshot congelado

`report_snapshots.payload` guarda tudo o que o laudo precisa, **sem depender de
join com o catálogo**:

```json
{
  "gerado_em": "2026-07-08T14:32:00-03:00",
  "aprendiz":  { "nome": "...", "nascimento": "...", "idade_na_aplicacao": "4 anos e 7 meses" },
  "aplicador": { "nome": "...", "registro": "...", "clinica": { } },
  "aplicacao": { "data": "2026-07-08", "iniciada_em": "...", "concluida_em": "..." },
  "niveis": [
    { "nivel": 1, "total": 45, "pontuacao": 33.5,
      "areas": [
        { "code": "mando", "short_name": "Mando", "pontuacao": 3.5,
          "marcos": [
            { "posicao": 1, "enunciado": "...", "score": 1.0,
              "sobrescrito": false, "exemplares": ["bola", "água"] }
          ] } ] } ],
  "observacoes": "..."
}
```

> **Por que congelar.** Se um enunciado ou limiar for corrigido meses depois, o
> laudo já emitido continua reproduzindo o que foi avaliado. É o que sustenta a
> promessa de imutabilidade — e o motivo de o payload duplicar o texto do
> catálogo em vez de referenciá-lo.

`content_hash` é o SHA-256 do payload, impresso no rodapé do PDF.

### 5. Gráfico de marcos

Reproduz `refs/Grafico VB-MAPP.xlsx`: uma coluna por área do nível, cinco células
empilhadas, o marco 1 embaixo.

| Pontuação | Célula |
| --- | --- |
| 1 ponto | Cheia, `#059669` |
| ½ ponto | Metade inferior, `#D97706` |
| 0 ponto | Vazia, com borda |
| Não respondido | Vazia, borda tracejada |

**Construa como `<table>`, com cada marco em duas linhas de meia altura.** A
inferior pintada de âmbar representa ½; as duas verdes representam 1. Sem
gradiente, sem pseudo-elemento, sem grid — é o único jeito de a mesma marcação
servir HTML e dompdf.

Um gráfico por nível avaliado. Níveis não avaliados não aparecem.

Acessibilidade: cor nunca é o único portador. Cada célula tem `aria-label`
(`Mando, marco 4: meio ponto`) e o relatório traz a tabela numérica equivalente.

### 6. Relatório em HTML

`/avaliacoes/{id}/relatorio`, somente leitura:

1. Identificação — aprendiz, idade, aplicação, aplicador, clínica
2. Resumo — pontuação por nível e total
3. Gráfico de marcos por nível
4. Tabela de pontuação por área
5. Detalhamento marco a marco, com exemplares
6. Marcos com pontuação sobrescrita, sinalizados e com o motivo
7. Observações do aplicador
8. Carimbo: data de geração e `content_hash`

**O relatório não fica disponível durante o preenchimento.** Antes da conclusão,
a rota devolve 404 e a interface não oferece o link.

### 7. PDF do relatório

Mesmo conteúdo, via `GenerateReportPdf`. Nome:

```
relatorio-vbmapp-{aprendiz-slug}-{AAAA-MM-DD}.pdf
```

Sem marca de rascunho — este é o documento definitivo.

### 8. Cancelamento

Cancelar exige motivo, grava `cancelled_at` e `cancel_reason`, e **não apaga
respostas**. Avaliação cancelada é somente leitura, sem relatório.

## Critérios de aceite

- [x] `Concluir avaliação` só habilita com todos os níveis iniciados completos
- [x] Avaliação só do nível 2 é concluível com 60 de 60
- [x] Modal mostra resumo e explicita a imutabilidade
- [x] Conclusão grava `completed_at`, `locked_at` e o snapshot
- [x] Após a conclusão, gravar resposta é recusado — **409 pela API** (via
      `SaveResponse`) e erro no cartão pela web; o **403** aparece quando a
      `AssessmentPolicy::update` barra antes, que é o caminho da API
- [x] Interface não oferece controle de edição em avaliação travada
- [x] Snapshot contém tudo, sem depender do catálogo
- [x] `content_hash` confere com o payload (`hashIsValid()`)
- [x] Relatório indisponível antes da conclusão — rota devolve 404
- [x] Gráfico reproduz o padrão do xlsx, com meia célula em âmbar
- [x] Gráfico idêntico em HTML e PDF — mesmo componente Blade, mesma folha de estilo
- [x] Só aparecem os níveis avaliados
- [x] Marcos sobrescritos sinalizados com o motivo
- [x] Cada célula tem `aria-label`; tabela numérica equivalente presente
- [x] PDF gerado em fila, com nome padronizado
- [x] Cancelar exige motivo e preserva as respostas
- [x] Alterar o catálogo depois **não** muda o relatório emitido — teste automatizado

### Testes obrigatórios

```php
it('impede conclusão com nível iniciado incompleto', ...);
it('conclui avaliação que usou apenas o nível 2', ...);
it('recusa gravação após o travamento', ...);
it('congela o snapshot com os dados do momento', ...);
it('mantém o relatório inalterado após mudança no catálogo', ...);
it('devolve 404 no relatório de avaliação em andamento', ...);
it('monta o gráfico com meia célula para meio ponto', ...);
it('preserva respostas ao cancelar', ...);
```

## Riscos e decisões

**Revalide a condição de conclusão no servidor.** Um botão habilitado por estado
de tela desatualizado não pode travar uma avaliação incompleta.

**O snapshot duplica dados de propósito.** Parece redundante; é o que garante
que o laudo sobreviva a correções no catálogo. Não "otimize" trocando por join.

**O gráfico em tabela parece antiquado.** É deliberado: é a única construção que
renderiza igual em navegador e dompdf. Um gráfico bonito em CSS grid sai em
branco no PDF.


---

## Registro de execução

Concluída. 221 testes na suíte ao fim da fase, 16 novos.

### O gráfico: um componente Blade para os dois meios

`MilestoneChart` (domínio) monta a matriz; `x-vbmapp.milestone-chart` (Blade)
renderiza; `_estilo.blade.php` traz o CSS. **Os três são compartilhados entre
a versão HTML e a do PDF** — a mesma marcação, o mesmo CSS 2.1.

Cada marco vira **duas linhas de meia altura** na tabela: a inferior pintada
de âmbar é ½ ponto, as duas verdes são 1 ponto. É o único jeito de o dompdf
(sem gradiente, sem pseudo-elemento, sem grid) produzir o mesmo desenho que o
navegador. Verificado ao vivo: 23 células cheias, 11 meias, 11 zeros no mesmo
caso de teste, nos dois meios.

### Duas armadilhas de plataforma

**Blade não reconhece diretiva colada a uma palavra.** Escrevi
`aplicador@if ($marco['motivo'])...@endif` e a view quebrou com "unexpected
token endif". A regex de diretivas do Blade usa `\B` antes do `@`, então
`aplicador@if` fica como **texto literal** — e aí o `@endif` seguinte vira
órfão. Trocado por interpolação ternária. O erro aparecia só ao renderizar,
não no `php -l`.

**Enfileirar dentro da transação é corrida garantida.** O `dispatch` do
`GenerateReportPdf` estava dentro do `DB::transaction`. Na fila `sync`
(testes) o job roda na hora e não enxerga o snapshot ainda não commitado; na
`database`, o worker pode pegar o job antes do commit. Movido para fora da
transação — é a mesma corrida nos dois modos, só que uma aparece no teste e a
outra em produção.

### O teste que mais importa

`it('mantém o relatório inalterado após mudança no catálogo')` conclui uma
avaliação, **altera o enunciado de um marco no catálogo**, invalida o cache e
confirma que o laudo emitido continua reproduzindo o texto original — com o
`content_hash` ainda válido.

É a prova de que o snapshot cumpre a promessa. E não é hipotético: a revisão
clínica pendente da F1 vai corrigir vários enunciados e limiares, e nenhum
laudo já emitido pode mudar por causa disso.

### Verificação ao vivo

```
Avaliação do nível 1 completa (45 marcos, pontuação 28,5):
  PDF: 3 páginas, gráfico com 9 colunas, paginação correta
  HTML: mesmo gráfico, aria-label por célula
    "Mando, marco 5: 1 ponto" · "Tato, marco 5: meio ponto"
  hash SHA-256 confere com o payload
```
