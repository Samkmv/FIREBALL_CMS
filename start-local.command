#!/bin/zsh
cd -- "${0:A:h}" || exit 1
python3 bin/local-server.py start || exit 1
open http://127.0.0.1:8080
