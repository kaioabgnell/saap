#!/usr/bin/env python3
"""Captura telas em viewport de verdade, via CDP.

Existe porque o headless do Chrome no macOS **trava a largura mínima do
viewport em ~500px**: `--window-size=390` só recorta a imagem, e o layout
continua sendo calculado a 500px. Uma captura assim mente — mostra texto
cortado onde não há corte, e esconde quebra real abaixo de 500px.

Emular por CCDP (`Emulation.setDeviceMetricsOverride`) dá o viewport de
verdade, e de quebra devolve `scrollWidth`, que é o que responde à regra da
F9: "a página nunca rola de lado".

Uso:
    .venv-tools/bin/python tools/captura.py URL SAIDA.png [--largura 390] [--altura 844]
"""

from __future__ import annotations

import argparse
import base64
import json
import subprocess
import time
import urllib.request

import websocket

CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
PORTA = 9222


class Navegador:
    def __init__(self, porta: int = PORTA):
        self.porta = porta
        self.processo = subprocess.Popen(
            # --remote-allow-origins: sem isso o Chrome recusa o handshake do
            # websocket com 403, mesmo vindo de 127.0.0.1.
            [CHROME, "--headless=new", "--disable-gpu", f"--remote-debugging-port={porta}",
             f"--remote-allow-origins=http://127.0.0.1:{porta}",
             "--no-first-run", "--user-data-dir=/tmp/chrome-captura-saap", "about:blank"],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        )
        self.ws = websocket.create_connection(self._alvo(), timeout=30)
        self.id = 0

    def _alvo(self) -> str:
        for _ in range(60):
            try:
                alvos = json.load(urllib.request.urlopen(f"http://127.0.0.1:{self.porta}/json"))
                for a in alvos:
                    if a["type"] == "page":
                        return a["webSocketDebuggerUrl"]
            except Exception:
                pass
            time.sleep(0.5)
        raise RuntimeError("Chrome não abriu a porta de depuração")

    def cmd(self, metodo: str, **params):
        self.id += 1
        self.ws.send(json.dumps({"id": self.id, "method": metodo, "params": params}))
        while True:
            msg = json.loads(self.ws.recv())
            if msg.get("id") == self.id:
                if "error" in msg:
                    raise RuntimeError(f"{metodo}: {msg['error']}")
                return msg.get("result", {})

    def fechar(self):
        try:
            self.ws.close()
        finally:
            self.processo.terminate()


def capturar(url: str, saida: str, largura: int, altura: int, escala: int = 2) -> dict:
    nav = Navegador()
    try:
        nav.cmd("Emulation.setDeviceMetricsOverride",
                width=largura, height=altura, deviceScaleFactor=escala,
                mobile=largura < 768)
        nav.cmd("Page.enable")
        nav.cmd("Page.navigate", url=url)
        time.sleep(3.5)  # fontes, imagens e Alpine

        medidas = nav.cmd("Runtime.evaluate", returnByValue=True, expression="""
            ({
              viewport: window.innerWidth,
              scrollWidth: document.documentElement.scrollWidth,
              scrollHeight: document.documentElement.scrollHeight,
              rolaDeLado: document.documentElement.scrollWidth > window.innerWidth + 1
            })
        """)["result"]["value"]

        dados = nav.cmd("Page.captureScreenshot", format="png", captureBeyondViewport=False)
        with open(saida, "wb") as f:
            f.write(base64.b64decode(dados["data"]))

        return medidas
    finally:
        nav.fechar()


if __name__ == "__main__":
    p = argparse.ArgumentParser()
    p.add_argument("url")
    p.add_argument("saida")
    p.add_argument("--largura", type=int, default=390)
    p.add_argument("--altura", type=int, default=844)
    args = p.parse_args()

    m = capturar(args.url, args.saida, args.largura, args.altura)
    print(f"  viewport {m['viewport']}px · conteúdo {m['scrollWidth']}×{m['scrollHeight']}px "
          f"· rola de lado: {'SIM ⚠' if m['rolaDeLado'] else 'não'}")
