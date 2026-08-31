#!/usr/bin/env python3
"""Renderiza e mapeia as páginas do material de aplicação dos níveis 2 e 3.

Ao contrário do nível 1 (F4), aqui não há recorte em estímulos individuais —
a curadoria em imagens dos níveis 2 e 3 está fora do escopo da v1. Cada página
vira um PNG inteiro, servido pelo visualizador como conferência e fallback.

Uso:
    .venv-tools/bin/python tools/import_material_pages.py
"""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

import pymupdf
from PIL import Image

RAIZ = Path(__file__).resolve().parent.parent
DPI = 150  # 233 páginas a 200 dpi somam centenas de MB; 150 basta para leitura

ARQUIVOS = [
    (2, RAIZ / "docs/material-aplicacao/nivel2/Material-Aplicacao-Nivel-2-pt1.pdf"),
    (2, RAIZ / "docs/material-aplicacao/nivel2/Material-Aplicacao-Nivel-2-pt2.pdf"),
    (2, RAIZ / "docs/material-aplicacao/nivel2/Material-Aplicacao-Nivel-2-pt3.pdf"),
    (3, RAIZ / "docs/material-aplicacao/nivel3/Material-Aplicacao-Nivel-3.pdf"),
]

# "PERCEPÇÃO" no material corresponde à área vpmts no catálogo.
AREA_POR_ROTULO = {
    "TATO": "tato",
    "OUVINTE": "ouvinte",
    "PERCEPCAO": "vpmts",
    "PERCEPÇÃO": "vpmts",
    "LRFFC": "lrffc",
    "LEITURA": "leitura",
    "ESCRITA": "escrita",
    "MATEMATICA": "matematica",
    "MATEMÁTICA": "matematica",
}

CABECALHO = re.compile(
    r"^(TATO|OUVINTE|PERCEP[ÇC][ÃA]O|LRFFC|LEITURA|ESCRITA|MATEM[ÁA]TICA)\s+"
    r"(\d+)(?:\s*[Ee]\s*(\d+))?",
)


def texto_util(pagina) -> list[str]:
    return [l.strip() for l in pagina.get_text().split("\n")
            if l.strip() and "licenciado" not in l.lower()]


def marcos_do_cabecalho(linha: str) -> list[int] | None:
    m = CABECALHO.match(linha)
    if not m:
        return None

    posicoes = [int(m.group(2))]
    if m.group(3):
        posicoes.append(int(m.group(3)))

    return posicoes


def main() -> int:
    todas_as_paginas: list[dict] = []

    for level, caminho in ARQUIVOS:
        if not caminho.exists():
            print(f"aviso: {caminho} não encontrado, pulando", file=sys.stderr)
            continue

        doc = pymupdf.open(caminho)
        destino = RAIZ / "storage/app/public/vbmapp/paginas" / f"nivel-{level}"
        destino.mkdir(parents=True, exist_ok=True)

        atual_area: str | None = None
        atual_posicoes: list[int] = []

        for indice, pagina in enumerate(doc, start=1):
            e_cabecalho = False

            for linha in texto_util(pagina):
                for rotulo, area in AREA_POR_ROTULO.items():
                    if linha.upper().startswith(rotulo):
                        posicoes = marcos_do_cabecalho(linha)
                        if posicoes:
                            atual_area = area
                            atual_posicoes = posicoes
                            e_cabecalho = True
                        break
                if e_cabecalho:
                    break

            # JPEG em vez de PNG: são páginas fotográficas do material, e o
            # ganho é enorme — a maior página caiu de 12,3 MB para 1,4 MB.
            # É para leitura de conferência, não edição; a perda não importa.
            pix = pagina.get_pixmap(dpi=DPI)
            nome = f"{caminho.stem}-p{indice:03d}.jpg"
            caminho_png = destino / nome
            imagem = Image.frombytes("RGB", (pix.width, pix.height), pix.samples)
            imagem.save(caminho_png, "JPEG", quality=85, optimize=True)

            # A página do próprio cabeçalho já pertence ao marco que anuncia —
            # ao contrário do recorte da F4, aqui a página inteira é o conteúdo.
            marcos = [] if atual_area is None else [
                {"area_code": atual_area, "item_position": p} for p in atual_posicoes
            ]

            todas_as_paginas.append({
                "level": level,
                "page_number": indice,
                "image_path": str(caminho_png.relative_to(RAIZ / "storage/app/public")),
                "source_file": caminho.name,
                "marcos": marcos,
            })

        print(f"{caminho.name}: {len(doc)} páginas renderizadas")

    saida = RAIZ / "storage/app/vbmapp/paginas-niveis-2-3.json"
    saida.parent.mkdir(parents=True, exist_ok=True)
    saida.write_text(json.dumps({"paginas": todas_as_paginas}, ensure_ascii=False, indent=2))

    sem_marco = sum(1 for p in todas_as_paginas if not p["marcos"])
    print(f"\n{len(todas_as_paginas)} páginas -> {saida.relative_to(RAIZ)}")
    print(f"{sem_marco} páginas sem marco identificado (capa e afins)")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
