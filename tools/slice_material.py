#!/usr/bin/env python3
"""Recorta os estímulos do material de aplicação em imagens individuais.

Utilitário de build: roda uma vez por versão do material e não é dependência
de runtime. A saída vai para storage/app/vbmapp/ e é revisada na tela de
curadoria antes de virar acervo.

Trabalha sempre sobre a PÁGINA RENDERIZADA, nunca sobre os objetos de imagem
embutidos no PDF: parte das páginas é desenho vetorial puro (get_images()
devolve zero) e outras têm rasters sobrepostos, então extrair objeto por objeto
devolve fragmentos, não estímulos.

Uso:
    .venv-tools/bin/python tools/slice_material.py --level 1
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from dataclasses import dataclass, asdict
from pathlib import Path

import numpy as np
import pymupdf
from PIL import Image
from scipy import ndimage

RAIZ = Path(__file__).resolve().parent.parent

DPI = 200
RODAPE_PT = 40          # faixa da marca d'água de licença, em pontos
LADO_MINIMO = 60        # descarta ruído e resíduo de texto
OCUPACAO_MAXIMA = 0.80  # caixa quase do tamanho da página é bloco de texto, não figura
FATOR_UNIAO = 0.25      # une caixas cujo vão é pequeno PARA O TAMANHO delas
TETO_UNIAO = 160        # teto absoluto do vão, em px — impede o efeito bola de neve
UNIAO_MAXIMA = 0.55     # união que gere caixa maior que isso juntou estímulos distintos
FOLGA_UNIAO = 28        # caixas mais próximas que isso viram um estímulo só
MARGEM = 0.08           # 8% de folga em volta do recorte
LIMIAR_BRANCO = 244     # acima disso é fundo
ESCALA_DETECCAO = 4     # detecta em 1/4 da resolução; o recorte sai do original

# Cabeçalho da página -> (código da área, posição do marco).
# Extraído dos títulos das páginas de seção do próprio material.
CABECALHOS = [
    (re.compile(r"^TATO\s+1\s+e\s+2", re.I), [("tato", 1), ("tato", 2)]),
    (re.compile(r"^TATO\s+3", re.I), [("tato", 3)]),
    (re.compile(r"^TATO\s+5", re.I), [("tato", 5)]),
    (re.compile(r"^OUVINTE\s+3", re.I), [("ouvinte", 3)]),
    (re.compile(r"^OUVINTE\s+5", re.I), [("ouvinte", 5)]),
    (re.compile(r"^PERCEP[ÇC][ÃA]O\s+5", re.I), [("vpmts", 5)]),
]

MATERIAL = {
    1: RAIZ / "docs" / "material-aplicacao" / "nivel1" / "Material-Aplicacao-nivel-1.pdf",
}


@dataclass
class Recorte:
    id: str
    level: int
    marcos: list[dict]      # um recorte pode servir a mais de um marco
    source_page: int
    box: list[int]          # caixa na imagem renderizada: x0, y0, x1, y1
    image_path: str
    width: int
    height: int


def texto_util(pagina) -> list[str]:
    return [l.strip() for l in pagina.get_text().split("\n")
            if l.strip() and "licenciado" not in l.lower()]


def mapear_paginas(doc) -> dict[int, list[tuple[str, int]]]:
    """Propaga o cabeçalho de seção para as páginas seguintes até o próximo.

    A página do cabeçalho fica de fora: ela só tem o título da seção, e fatiar
    texto rende dezenas de fragmentos que não são estímulo nenhum.
    """
    mapa: dict[int, list[tuple[str, int]]] = {}
    atual: list[tuple[str, int]] = []

    for indice, pagina in enumerate(doc, start=1):
        e_cabecalho = False

        for linha in texto_util(pagina):
            for padrao, marcos in CABECALHOS:
                if padrao.match(linha):
                    atual = marcos
                    e_cabecalho = True
                    break

        mapa[indice] = [] if e_cabecalho else list(atual)

    return mapa


def renderizar(pagina) -> Image.Image:
    pix = pagina.get_pixmap(dpi=DPI)
    imagem = Image.frombytes("RGB", (pix.width, pix.height), pix.samples)

    # A faixa do rodapé traz a marca d'água de licença em toda página.
    corte = int(RODAPE_PT / 72 * DPI)

    return imagem.crop((0, 0, imagem.width, max(1, imagem.height - corte)))


def caixas_de_conteudo(imagem: Image.Image) -> list[tuple[int, int, int, int]]:
    """Acha as caixas de cada estímulo na página renderizada.

    A detecção roda em 1/4 da resolução e as caixas voltam multiplicadas: a
    dilatação numa página de 4000x2139 leva ~16 s, e em 1000x535 leva menos de
    um segundo, sem diferença no resultado — a folga de união é bem maior que
    o erro de arredondamento. O recorte final sai sempre da imagem original.
    """
    reduzida = imagem.resize(
        (imagem.width // ESCALA_DETECCAO, imagem.height // ESCALA_DETECCAO),
        Image.LANCZOS,
    )

    conteudo = np.array(reduzida.convert("L")) < LIMIAR_BRANCO

    if not conteudo.any():
        return []

    # Dilata antes de rotular: partes de um mesmo desenho (o olho do gato, o
    # cabo da colher) chegam separadas e precisam voltar a ser um estímulo só.
    # A dilatação de caixa é separável, o que é bem mais rápido que a 2D.
    raio = max(1, FOLGA_UNIAO // ESCALA_DETECCAO // 2)
    unido = ndimage.binary_dilation(conteudo, structure=np.ones((raio, 1), bool))
    unido = ndimage.binary_dilation(unido, structure=np.ones((1, raio), bool))

    rotulos, quantos = ndimage.label(unido)
    caixas = []

    for y, x in ndimage.find_objects(rotulos) if quantos else []:
        caixa = (x.start * ESCALA_DETECCAO, y.start * ESCALA_DETECCAO,
                 x.stop * ESCALA_DETECCAO, y.stop * ESCALA_DETECCAO)
        largura, altura = caixa[2] - caixa[0], caixa[3] - caixa[1]

        if largura < LADO_MINIMO or altura < LADO_MINIMO:
            continue

        # Uma caixa que cobre quase a página inteira é bloco de texto ou moldura
        # que a dilatação uniu — nunca uma figura.
        if (largura / imagem.width > OCUPACAO_MAXIMA
                and altura / imagem.height > OCUPACAO_MAXIMA):
            continue

        caixas.append(caixa)

    unidas = unir_proximas(caixas, imagem.width, imagem.height)

    return sorted(unidas, key=lambda c: (c[1] // 100, c[0]))  # linha, depois coluna


def unir_proximas(
    caixas: list[tuple[int, int, int, int]],
    largura_pagina: int,
    altura_pagina: int,
) -> list[tuple[int, int, int, int]]:
    """Une caixas separadas por um vão pequeno em relação ao tamanho delas.

    A dilatação de raio fixo não dá conta de figura cujas partes ficam longe
    entre si mas perto na escala do desenho — o caso típico é um sol, em que
    cada raio sai como um recorte solto. Um limiar proporcional une os raios ao
    disco sem colar os estímulos vizinhos, que na grade 2x2 estão a mais de uma
    figura de distância.
    """
    caixas = list(caixas)

    mudou = True
    while mudou:
        mudou = False

        for i in range(len(caixas)):
            for j in range(i + 1, len(caixas)):
                a, b = caixas[i], caixas[j]

                vao_x = max(0, max(a[0], b[0]) - min(a[2], b[2]))
                vao_y = max(0, max(a[1], b[1]) - min(a[3], b[3]))

                escala = max(a[2] - a[0], a[3] - a[1], b[2] - b[0], b[3] - b[1])
                # O teto absoluto é o que impede a bola de neve: sem ele, cada
                # união aumenta a escala, que aumenta o limite, que une mais.
                limite = min(escala * FATOR_UNIAO, TETO_UNIAO)

                if vao_x > limite or vao_y > limite:
                    continue

                unida = (min(a[0], b[0]), min(a[1], b[1]),
                         max(a[2], b[2]), max(a[3], b[3]))

                # Fotos com fundo quase branco geram caixas do tamanho da foto
                # inteira, que quase se tocam na grade 2x2. O material está
                # sempre em grade, então uma união que cruze a metade da página
                # em QUALQUER eixo juntou estímulos distintos — daí o "ou".
                if ((unida[2] - unida[0]) / largura_pagina > UNIAO_MAXIMA
                        or (unida[3] - unida[1]) / altura_pagina > UNIAO_MAXIMA):
                    continue

                caixas[i] = unida
                caixas.pop(j)
                mudou = True
                break

            if mudou:
                break

    return caixas


def exportar(imagem: Image.Image, caixa, destino: Path) -> tuple[int, int]:
    x0, y0, x1, y1 = caixa
    folga_x = int((x1 - x0) * MARGEM)
    folga_y = int((y1 - y0) * MARGEM)

    recorte = imagem.crop((
        max(0, x0 - folga_x), max(0, y0 - folga_y),
        min(imagem.width, x1 + folga_x), min(imagem.height, y1 + folga_y),
    ))

    # Normaliza para quadrado com fundo branco: a grade fica regular sem
    # distorcer nenhuma figura.
    lado = max(recorte.width, recorte.height)
    quadrado = Image.new("RGB", (lado, lado), "white")
    quadrado.paste(recorte, ((lado - recorte.width) // 2, (lado - recorte.height) // 2))

    destino.parent.mkdir(parents=True, exist_ok=True)
    quadrado.save(destino, "PNG", optimize=True)

    return recorte.width, recorte.height


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--level", type=int, default=1)
    args = parser.parse_args()

    origem = MATERIAL.get(args.level)
    if origem is None or not origem.exists():
        print(f"erro: material do nível {args.level} não encontrado", file=sys.stderr)
        return 1

    doc = pymupdf.open(origem)
    mapa = mapear_paginas(doc)

    dir_estimulos = RAIZ / "storage" / "app" / "public" / "vbmapp" / "estimulos" / f"nivel-{args.level}"
    dir_paginas = RAIZ / "storage" / "app" / "public" / "vbmapp" / "paginas" / f"nivel-{args.level}"

    recortes: list[Recorte] = []
    paginas: list[dict] = []

    for indice, pagina in enumerate(doc, start=1):
        imagem = renderizar(pagina)
        marcos = mapa.get(indice, [])

        # Guarda a página inteira: serve de conferência na curadoria e de
        # fallback quando o recorte não cobre o marco.
        caminho_pagina = dir_paginas / f"p{indice:03d}.png"
        caminho_pagina.parent.mkdir(parents=True, exist_ok=True)
        imagem.save(caminho_pagina, "PNG", optimize=True)
        paginas.append({
            "level": args.level,
            "page_number": indice,
            "image_path": str(caminho_pagina.relative_to(RAIZ / "storage" / "app" / "public")),
            "source_file": origem.name,
            "marcos": [{"area_code": a, "item_position": p} for a, p in marcos],
        })

        # Páginas de seção (só título) não têm estímulo.
        if not marcos:
            continue

        for ordem, caixa in enumerate(caixas_de_conteudo(imagem), start=1):
            identificador = f"n{args.level}-p{indice:03d}-{ordem:02d}"
            caminho = dir_estimulos / f"{identificador}.png"
            largura, altura = exportar(imagem, caixa, caminho)

            recortes.append(Recorte(
                id=identificador,
                level=args.level,
                # A página "TATO 1 e 2" serve aos dois marcos: o mesmo recorte
                # vira um estímulo em cada um deles.
                marcos=[{"area_code": a, "item_position": pos} for a, pos in marcos],
                source_page=indice,
                box=[int(v) for v in caixa],
                image_path=str(caminho.relative_to(RAIZ / "storage" / "app" / "public")),
                width=largura,
                height=altura,
            ))

    saida = RAIZ / "storage" / "app" / "vbmapp" / f"estimulos-nivel-{args.level}.json"
    saida.parent.mkdir(parents=True, exist_ok=True)
    saida.write_text(json.dumps({
        "level": args.level,
        "source": origem.name,
        "dpi": DPI,
        "paginas": paginas,
        "recortes": [asdict(r) for r in recortes],
    }, ensure_ascii=False, indent=2))

    print(f"{len(doc)} páginas renderizadas")
    print(f"{len(recortes)} candidatos a estímulo -> {saida.relative_to(RAIZ)}")

    por_marco: dict[str, int] = {}
    for r in recortes:
        for m in r.marcos:
            chave = f"{m['area_code']}:{m['item_position']}"
            por_marco[chave] = por_marco.get(chave, 0) + 1
    for chave, total in sorted(por_marco.items()):
        print(f"    {chave:<12} {total:>3}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
