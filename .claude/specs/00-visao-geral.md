# 00 — Visão geral

## Produto

O SAAP assiste um profissional de psicologia na aplicação do **VB-MAPP**
(*Verbal Behavior Milestones Assessment and Placement Program*) em aprendizes —
crianças em avaliação de comportamento verbal.

Hoje a aplicação é feita em papel: o psicólogo imprime os formulários de
registro, aplica o teste ao longo de semanas, marca pontuação à mão, e depois
transcreve tudo para uma planilha para gerar o gráfico de marcos. O SAAP
substitui esse percurso por um fluxo digital com salvamento contínuo,
pontuação calculada e relatório gerado.

## Escopo da v1

Sem custo de uso. Uma conta por psicólogo.

**Está dentro:**

- Cadastro e login (a primeira tela do sistema é `/login`)
- Perfil do psicólogo e dados da clínica
- Cadastro de aprendizes com foto e idade calculada
- Aplicação dos três níveis do VB-MAPP, 170 marcos
- Salvamento contínuo com indicador de último salvamento
- Progresso por avaliação, por nível e por área
- Filtro de itens não respondidos
- Imagens do material de aplicação do nível 1, com check por estímulo
- Impressão do formulário parcial em PDF, durante a aplicação
- Conclusão com travamento e relatório final com gráfico de marcos
- API REST versionada, preparada para o app móvel futuro

**Está fora** (ver `fases/F9` para o registro completo):

- Sugestão de programas e atividades a partir dos gaps do aprendiz — é o
  objetivo declarado da v2
- Comparação de até 4 aplicações no mesmo gráfico
- Avaliação de Barreiras (24 barreiras) e Avaliação de Transição
- Curadoria de imagens dos níveis 2 e 3
- Aplicativo móvel, cobrança, clínica com equipe

## Usuários

**Psicólogo aplicador.** Único perfil da v1. Cria aprendizes, aplica avaliações,
emite relatórios. Vê apenas os próprios dados.

O **aprendiz** não acessa o sistema. Ele aparece na tela durante a aplicação —
o tablet é virado para mostrar as imagens do material — mas não interage.

## Contexto de uso que molda o produto

**O iPad é o dispositivo real.** A aplicação acontece com a criança à frente, o
psicólogo segurando o tablet. O layout parte do iPad, não do desktop.

**A avaliação é longitudinal.** Boa parte dos marcos exige 30 ou 60 minutos de
observação — as áreas Brincar, Social e Vocal quase inteiras. Uma aplicação se
estende por dias ou semanas. Não existe sessão única, não existe expiração, e o
estado precisa sobreviver a qualquer intervalo.

**Perder registro é inaceitável.** O psicólogo está com a criança, com atenção
dividida. Se uma resposta se perder por queda de rede, ela não será refeita.

## Glossário

| Termo | Significado |
| --- | --- |
| **Aprendiz** | A criança avaliada. No código, `Learner`. |
| **Marco** | Um dos 170 itens pontuáveis. No código, `VbmappItem`. Vale 0, ½ ou 1. |
| **Área** | Um dos 16 domínios avaliados (Mando, Tato, Ouvinte…). |
| **Nível** | 1, 2 ou 3. Cada nível cobre 5 marcos de cada área presente nele. |
| **Estímulo** | Uma imagem individual do material de aplicação. |
| **Mando** | Pedido. A criança pede o que quer. |
| **Tato** | Nomeação. A criança nomeia o que vê. |
| **Ecóico** | Repetição vocal do que o adulto fala. |
| **Intraverbal** | Resposta verbal a estímulo verbal, sem apoio visual. |
| **VP-MTS** | Percepção Visual e Pareamento ao Modelo. No briefing, "Pareamento". |
| **LRFFC** | Resposta de Ouvinte por Função, Característica ou Classe. No briefing, "Resposta". |
| **EESA** | Subteste de avaliação de amostra ecóica. O grupo 1 tem 25 palavras. |

## Restrição de licenciamento

O VB-MAPP é obra protegida (Mark Sundberg / AVB Press) e os arquivos em `docs/`
trazem marca d'água de licença nominal.

A decisão para a v1 é **uso interno da licenciada**. Consequência arquitetural:
todo o conteúdo do instrumento vive isolado em tabelas com prefixo `vbmapp_` e
o usuário carrega `vbmapp_license_ref`. Restringir acesso, exigir licença por
usuário ou substituir o conteúdo depois **não pode** exigir refatorar o motor
de avaliação.

Antes de qualquer abertura pública de cadastro, consultar o detentor dos direitos.
