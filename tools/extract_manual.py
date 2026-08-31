#!/usr/bin/env python3
"""Extrai o texto do manual VB-MAPP traduzido para consumo do ManualImporter.

Utilitário de build: roda uma vez por versão do manual e não é dependência de
runtime. O PDF é digital, com texto embutido — não há OCR envolvido.

Uso:
    .venv-tools/bin/python tools/extract_manual.py
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

import pymupdf

RAIZ = Path(__file__).resolve().parent.parent
ORIGEM = RAIZ / "docs" / "vmmapp" / "Vb-mapp traduzido .pdf"
DESTINO = RAIZ / "storage" / "app" / "vbmapp" / "manual.txt"

# Ruído de rodapé e marca d'água. Lista explícita — nada de regex agressivo,
# que poderia comer conteúdo de critério.
RUIDO = [
    re.compile(r"^Comercialização.?proibida.?pelo.?autor.*$", re.I),
    re.compile(r"^Conteúdo licenciado para .*$", re.I),
    re.compile(r"^Adaptação.?de.?Martone.?e.?Goyos.*$", re.I),
    re.compile(r"^!+$"),
    re.compile(r"^\d{1,3}$"),  # número de página solto
]


def limpar(linha: str) -> str | None:
    # O PDF usa "!" como separador tipográfico em várias linhas de rodapé.
    texto = linha.replace("\xa0", " ").rstrip()
    nu = texto.strip()
    if not nu:
        return ""
    for padrao in RUIDO:
        if padrao.match(nu):
            return None
    return texto


def main() -> int:
    if not ORIGEM.exists():
        print(f"erro: manual não encontrado em {ORIGEM}", file=sys.stderr)
        return 1

    doc = pymupdf.open(ORIGEM)
    DESTINO.parent.mkdir(parents=True, exist_ok=True)

    descartadas = 0
    with DESTINO.open("w", encoding="utf-8") as saida:
        for indice, pagina in enumerate(doc, start=1):
            saida.write(f"\n===== PAGE {indice}/{len(doc)} =====\n")
            for linha in pagina.get_text().split("\n"):
                limpa = limpar(linha)
                if limpa is None:
                    descartadas += 1
                    continue
                saida.write(limpa + "\n")

    print(f"{len(doc)} páginas -> {DESTINO.relative_to(RAIZ)}")
    print(f"{descartadas} linhas de ruído descartadas")
    print(f"{DESTINO.stat().st_size:,} bytes")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
