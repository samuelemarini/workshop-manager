#!/usr/bin/env bash
# Avvio del gestionale: migration, dati di base (ruoli, postazioni, lavorazioni tipo) e primo admin.
#
# L'entrypoint webdevops lo esegue da root a ogni avvio: fa qualcosa solo dove RUN_MIGRATIONS=1.
# Niente "exit" e niente "set -e": lo script puo' essere incluso con "source" dall'entrypoint.

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    console() { gosu application php /app/bin/console "$@"; }

    # il volume dei documenti nasce di root: l'app gira come "application"
    mkdir -p /app/uploads && chown -R application:application /app/uploads

    echo "[officina] migration del database..."
    for attempt in $(seq 1 30); do
        if console doctrine:migrations:migrate --no-interaction --allow-no-migration; then
            break
        fi
        echo "[officina] database non ancora pronto, riprovo tra 3 secondi ($attempt/30)"
        sleep 3
    done

    # idempotente: crea solo cio' che manca; l'admin solo se c'e' ADMIN_PASSWORD e non esiste gia'
    if [ -n "${ADMIN_PASSWORD:-}" ]; then
        console app:setup --password="$ADMIN_PASSWORD" --quiet && echo "[officina] dati di base e admin pronti"
    else
        console app:setup --no-admin --quiet && echo "[officina] dati di base pronti (nessun ADMIN_PASSWORD: admin non creato)"
    fi
fi
