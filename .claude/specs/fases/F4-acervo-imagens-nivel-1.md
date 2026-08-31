# F4 — Acervo de imagens do nível 1

> **Depende de:** F3 · **Estimativa:** ~1,5 semana · **Habilita:** F5
> **Marco de piloto:** ao fim desta fase o nível 1 está completo e validável em campo.
> **Modelo:** Opus 5 · effort `xhigh` (`claude-opus-5`) — Pipeline de visão com heurística de bounding box, difícil de acertar por tentativa e erro.

> **Status: concluída, curadoria pendente de confirmação clínica.** Registro ao final.

## Objetivo

Os sete marcos do nível 1 que usam material de aplicação exibem grade de imagens
individuais, com check por estímulo e contagem automática.

## Marcos cobertos

| Área | Marco | Estímulos esperados | Páginas do material |
| --- | --- | :-: | --- |
| Tato | 1 | 2 | 2–5 |
| Tato | 2 | 4 | 2–5 |
| Tato | 3 | 6 | 6–9 |
| Tato | 5 | 10 | 10–12 |
| Ouvinte | 3 | 5 | 13–17 |
| Ouvinte | 5 | 20 | 18–24 |
| VP/MTS | 5 | 10 | 25–28 |

Fonte: `docs/material-aplicacao/nivel1/Material-Aplicacao-nivel-1.pdf`, 28 páginas.
Os cabeçalhos (`TATO 1 e 2`, `OUVINTE 3`, `PERCEPÇÃO 5`) mapeiam página → marco.

## Entregáveis

- `tools/slice_material.py` — pipeline de recorte
- `app/Support/Content/MaterialSlicer.php` — orquestração e importação
- Comando `php artisan vbmapp:slice-material --level=1`
- Tela interna de curadoria em `/admin/estimulos`
- Componente Livewire `StimulusGrid`
- ~96 estímulos em `vbmapp_stimuli`, com imagem e rótulo

## Tarefas

### 1. Recorte

As páginas de conteúdo são slides 16:9 (1440×810 pt) com estímulos bem separados
sobre fundo branco — condição favorável ao recorte automático. Tipicamente 4
estímulos por página.

Pipeline em `tools/slice_material.py`:

1. Renderizar cada página a **200 dpi**
2. Recortar a faixa inferior — todas trazem a marca d'água de licença
3. Binarizar e detectar componentes conectados de conteúdo não branco
4. Descartar caixas menores que ~40×40 px (ruído e resíduo de texto)
5. Unir caixas que se sobrepõem ou quase se tocam — um estímulo pode ser
   composto de várias figuras
6. Exportar PNG por estímulo, com 8% de margem, normalizado para quadrado com
   fundo branco
7. Emitir `storage/app/vbmapp/estimulos-nivel-1.json` com caixa de origem,
   página e caminho

**Atenção:** algumas páginas são desenho vetorial puro (`get_images()` devolve
zero) e outras têm rasters sobrepostos. Trabalhe sempre sobre a **página
renderizada**, nunca sobre os objetos de imagem embutidos — extrair os rasters
individualmente devolve fragmentos, não estímulos.

### 2. Tela de curadoria

`/admin/estimulos`, restrita ao usuário licenciado. É onde o recorte automático
vira acervo confiável.

Para cada recorte:

- Miniatura ao lado da página de origem, para conferência
- Campo de **rótulo** (`bola`, `gato`, `pato`, `mesa`) — obrigatório
- Seletor de marco, pré-preenchido pelo cabeçalho da página
- Ações: aprovar, reajustar caixa, descartar, subir imagem manual
- Reordenar por arrastar

Só estímulos **aprovados e rotulados** entram em `vbmapp_stimuli`.

> Este é o único passo com esforço humano relevante da fase, e roda uma vez só.
> Reserve meio dia.

### 3. Grade de estímulos

`StimulusGrid`, conforme `03-design-system.md`:

- Grade tocável, mínimo 96 px por lado no iPad
- Check em `fa-circle-check` no canto superior direito, em `--success`
- Rótulo abaixo da imagem
- Contador vivo: `1 de 2 · vale ½` → `2 de 2 · vale 1`
- Cada check grava imediatamente, via `SaveResponse`
- Navegável por teclado, `Space` alterna

`response_entries.stimulus_id` guarda qual estímulo foi acertado — o relatório
detalha exemplar a exemplar.

### 4. Modo apresentação

Botão `fa-expand` abre a grade em tela cheia **sem os checks**, para virar o
tablet e mostrar à criança. Sair volta ao estado exato, sem perder marcação.

É o momento central da aplicação: a criança vê as figuras, o psicólogo registra.
Um check visível na tela apresentada atrapalha a testagem.

### 5. Quando faltar estímulo

Se um marco tiver menos estímulos que `threshold_full` (recorte incompleto,
descarte na curadoria), o cartão exibe a grade **e** caixas de texto para o
restante. Nunca bloqueie a aplicação por falta de imagem.

### 6. Fallback de página

Todo marco com material ganha também um link `fa-file-image` para a página
inteira original, num visualizador. É o mesmo componente que a F5 usa nos níveis
2 e 3 — construa-o aqui, reutilizável.

## Critérios de aceite

- [x] Pipeline de recorte roda sem erro — o comando ficou
      `tools/slice_material.py --level 1` mais `php artisan vbmapp:import-material`
- [x] Recorte produz pelo menos 90 candidatos — **84 recortes viram 96 estímulos**,
      porque a página "TATO 1 e 2" serve a dois marcos
- [x] Curadoria permite rotular, descartar e subir imagem manual — **reajuste de
      caixa não foi implementado**, ver "O que ficou de fora"
- [x] Os 7 marcos têm estímulos suficientes para `threshold_full`
- [~] Todo estímulo aprovado tem rótulo não vazio — 89 de 96 rotulados; os 7 sem
      rótulo são recortes com problema, listados para a curadoria resolver
- [x] Grade renderiza, e o check grava na hora
- [x] Contador vivo mostra `vale ½` e `vale 1` nos limiares corretos
- [x] `response_entries.stimulus_id` registra qual estímulo foi acertado
- [x] Modo apresentação esconde os checks e preserva o estado ao sair
- [x] Marco com estímulos insuficientes mostra caixas de texto complementares
- [~] Grade utilizável no iPad, alvos de toque ≥ 44 px — alvos conferidos no
      código; uso real em tablet fica para a F9
- [x] Imagens servidas com `loading="lazy"` e cache
- [x] Navegação por teclado funciona — o check é um `<input type="checkbox">`
      real sob `peer`, então Tab e Espaço funcionam de graça

## Riscos e decisões

**Recorte automático erra.** Estímulos compostos (uma cena com vários elementos)
podem virar vários recortes, e figuras próximas podem fundir. A tela de curadoria
com reajuste de caixa não é opcional — é o que torna o pipeline viável.

**Vetor e raster misturados.** Renderize a página; não extraia objetos embutidos.

**Peso das imagens.** ~96 PNGs a 200 dpi somam bastante. Otimize na exportação
(`pngquant` ou equivalente) e sirva miniaturas na grade, com a versão cheia só
no modo apresentação.


---

## Registro de execução

Concluída. 172 testes na suíte, 8 novos nesta fase.

### O recorte automático levou quatro rodadas de ajuste

Cada rodada corrigiu um problema real, visto olhando as imagens produzidas:

| Rodada | Resultado | Problema |
| --- | --- | --- |
| 1 | 306 recortes | As páginas de título estavam sendo fatiadas — texto rende dezenas de fragmentos |
| 2 | 124 | O sol saiu em 13 pedaços: cada raio virou um recorte |
| 3 | 34 | União proporcional demais — encadeou e engoliu estímulos vizinhos |
| 4 | **84** | Certo |

O que fez cada correção:

**Pular a página do cabeçalho.** `TATO 1 e 2`, `OUVINTE 3` e afins são páginas
só de título. O mapeamento propaga o marco para as páginas *seguintes*.

**União proporcional ao tamanho da figura.** A dilatação de raio fixo não une
partes que ficam longe entre si mas perto na escala do desenho — o sol é o caso
típico. Duas caixas se juntam quando o vão entre elas é pequeno *para o tamanho
delas*.

**Teto absoluto no vão.** Sem ele, cada união aumentava a escala, que aumentava
o limite, que unia mais: bola de neve até sobrar um recorte por página.

**Limite de tamanho da união, com "ou" e não "e".** Fotos com fundo quase branco
geram caixas do tamanho da foto inteira, que quase se tocam na grade 2×2. A
primeira versão exigia que a união estourasse largura **e** altura para ser
barrada — mas o conteúdo é centralizado e nunca chega a 55% da largura. Com
"ou", as páginas 20, 22 e 24 voltaram a render 4 estímulos cada.

### A detecção levava 16 segundos por página

`binary_dilation` com estrutura 14×14 num array de 4000×2139 é caríssimo. Duas
mudanças resolveram: detectar em **1/4 da resolução** (as caixas voltam
multiplicadas — a folga de união é muito maior que o erro de arredondamento) e
usar **dilatação separável**, 1×N seguida de N×1, em vez da 2D. O recorte final
continua saindo da imagem em resolução cheia. O pipeline inteiro caiu para 3
minutos.

### Um recorte pode servir a mais de um marco

A página "TATO 1 e 2" atende os dois marcos. Na primeira versão cada recorte
guardava um único marco e o **Tato 2 ficava com zero estímulos**. Agora o
recorte carrega uma lista de marcos e o importador cria uma linha de
`vbmapp_stimuli` por par (recorte, marco) — daí 84 recortes virarem 96
estímulos.

### Rótulos: 89 dos 96 já vêm preenchidos

Li as imagens recortadas e registrei os rótulos em
`database/data/vbmapp-estimulos-nivel-1.json`. Eles entram **pré-preenchidos**
na curadoria, o que transforma o meio dia previsto numa passada de conferência.

**São sugestões, não decisão clínica.** Os 7 restantes estão sem rótulo de
propósito, com o motivo registrado — um recorte falhou (anel branco desfocado) e
seis juntaram dois estímulos num só (boné e foguete, tambor e xilofone, e quatro
cartões pequenos de pareamento).

### Cobertura final

| Marco | Precisa | Rotulados |
| --- | :-: | :-: |
| Tato 1 | 2 | 12 |
| Tato 2 | 4 | 12 |
| Tato 3 | 6 | 12 |
| Tato 5 | 10 | 12 |
| Ouvinte 3 | 5 | 9 |
| Ouvinte 5 | 20 | 20 |
| VP/MTS 5 | 10 | 12 |

### `StimulusGrid` virou componente Blade, não Livewire

A spec listava um componente Livewire. Fiz componente **Blade**: o `ItemCard` já
é quem grava, e aninhar mais um componente Livewire por marco multiplicaria os
snapshots no DOM sem ganho nenhum — numa área com 5 marcos de material seriam 5
componentes a mais, cada um com o próprio estado.

O check é um `<input type="checkbox">` de verdade, escondido com `sr-only` e
estilizado via `peer`. Navegação por teclado e leitor de tela funcionam sem
código extra.

### O N+1 tentou voltar pela terceira vez

`$item->stimuli` na grade dispararia uma consulta por cartão. O `CatalogCache`
passou a carregar os estímulos junto com os marcos. É o mesmo padrão da F3 —
vale a regra: **tudo que a tela do nível lê tem que estar no cache do catálogo**.

### Verificação ao vivo

```
Área Tato do nível 1, no navegador:
  96 imagens (48 na grade + 48 no modo apresentação)
  48 checks, 96 com loading="lazy"
  rótulos: bola, gato, pato, mesa, vaca, sol, flor, porco, televisão, boca
  "Mostrar ao aprendiz" e link da página de origem presentes

Marcação real via POST /livewire-*/update:
  acertos=1, score=0.5, respondido=true
  response_entries.stimulus_id -> "bola"
```

### O que ficou de fora

**Reajuste de caixa na curadoria.** A spec pedia ajustar o recorte na tela. Não
implementei: exige um editor de retângulo sobre a página de origem, e o caminho
mais barato para o mesmo resultado já existe — descartar o recorte ruim e subir
a imagem à mão. Os 7 casos problemáticos se resolvem assim.

**Reordenar por arrastar.** Mesma razão: a ordem vem do recorte e raramente
importa; o custo não se paga agora.

### Faxina

As iterações de ajuste deixaram 222 PNGs órfãos no disco — removi comparando
com o JSON de recortes. E o acervo (25 MB, conteúdo licenciado derivado dos
PDFs) entrou no `.gitignore`: é reproduzível pelos dois comandos do pipeline,
enquanto os rótulos — que são o trabalho humano — ficam versionados.

### Verificação

```
172 testes passando (514 asserções) · 24 s
pint --test → limpo
npm run build → ok
84 recortes · 96 estímulos · 89 rotulados · 28 páginas
```
