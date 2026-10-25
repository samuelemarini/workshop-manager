# Gestionale officina – gestione lavori

Gestionale web per l'officina Officina: ordini di lavoro (veicolo in officina con le sue lavorazioni), planning dei meccanici, bacheca a colonne, tempi registrati dal tablet in officina, ricambi e ore per meccanico.

Stack: Symfony 8.1 + Doctrine, MariaDB 11, Twig + Bootstrap 5 compilati con Webpack Encore, container `webdevops/php-apache:8.4`. Lo scheletro (layout, utenti e ruoli, allegati, storico, produzione) viene da Carpin; grafica dal mockup in rosso e nero.

## Avvio

| | |
|---|---|
| Web | http://localhost:20036 |
| DB MariaDB | localhost:30036 (utente `user`, password `DB_PASSWORD` del file `.env`) |
| Container | `officina-web-1`, `officina-database-1` |

```sh
cp .env.example .env                   # e imposta DB_PASSWORD
./start_stack.sh                       # build + avvio
docker exec -w /app officina-web-1 composer install
docker exec -w /app officina-web-1 yarn install
./yarn_encore_dev.sh                   # asset (oppure yarn_encore_dev_watch.sh)
docker exec -u application -w /app officina-web-1 php bin/console doctrine:migrations:migrate -n
docker exec -u application -w /app officina-web-1 php bin/console app:setup --password=<pwd>
docker exec -u application -w /app officina-web-1 php bin/console app:demo     # facoltativo
```

- La console va lanciata come `application` (`-u application`): da root la cache diventa illeggibile per Apache.
- `app:setup` è idempotente: ruoli, utente `admin`, e solo se le tabelle sono vuote lavorazioni tipo (tagliando, pastiglie, gomme, pre-revisione...) e i meccanici Antonio e Riccardo.
- `app:demo` carica una mattina d'officina come nel mockup, con orari relativi a adesso, più gli utenti `ufficio` e `officina` (password `demo`). `--reset` cancella ordini, veicoli e clienti: **mai su dati veri**.

## Produzione

Come Carpin: stack Portainer da Git con `docker-compose.prod.yml` e tunnel Cloudflare (`http://web:80`). Variabili in `.env.example`; all'avvio `docker/entrypoint.d/60-officina.sh` lancia migration e `app:setup`. Volumi da salvare: `db_data` e `uploads` (foto e documenti).

## Moduli

- **Dashboard**: veicoli in officina, cosa sta facendo adesso ogni meccanico (cronometro, stima, prossimo lavoro), consegne di oggi, cose da gestire (OK del cliente, ritardi, lavorazioni senza meccanico, ricambi da ordinare o in arrivo), ore della settimana.
- **Planning**: giornata a quarti d'ora per meccanico, pausa pranzo, "adesso", spazi liberi, lavorazioni da pianificare; trascinando si cambia orario e meccanico. **Settimana** (meccanici × giorni con carico e consegne, trascinabile) e **mese** (ore per meccanico e consegne di ogni giorno).
- **Bacheca lavori**: colonne Da iniziare / In lavorazione / In attesa / Pronta / Consegnate; trascinando si cambia stato (in "In attesa" chiede il motivo). Filtro per meccanico e ricerca.
- **Ordini di lavoro**: accettazione (veicolo e cliente dall'anagrafica o nuovi al volo, lavorazioni tipo da spuntare), scheda con lavorazioni, registro tempi, ricambi e totali, foto, storico, stampa della scheda di lavoro.
- **Tablet officina** (`/tablet`): il meccanico tocca il suo nome (PIN facoltativo), vede lavoro in corso, prossimi e lavori liberi; avvia, mette in pausa con il motivo, termina; spunta i controlli, registra ricambi (anche "manca: da ordinare"), km, note e foto; segnala lavori extra o problemi di sicurezza, che l'ufficio approva o rifiuta.
- **Clienti**, **Veicoli** (con storico interventi), **Meccanici e ore** (ore per giorno, stimato contro effettivo), **Utenti**, **Impostazioni** (orari, pausa, tariffa oraria, lavorazioni tipo).

## Regole

- Un meccanico lavora su una cosa alla volta: avviarne un'altra mette in pausa quella in corso ("Passato a un altro lavoro").
- La prima lavorazione avviata porta l'ordine "In lavorazione"; una pausa per ricambi o OK del cliente, senza altro in corso, lo porta "In attesa".
- Quando tutte le lavorazioni sono completate l'ordine diventa "Pronta"; se ci sono lavori extra senza risposta del cliente resta "In attesa · OK del cliente".
- Le segnalazioni dal tablet sono lavorazioni "Da autorizzare": non contano per avanzamento e totali finché l'ufficio non le approva.
- Il tempo effettivo è la somma delle registrazioni (`TimeEntry`), una per ogni avvio/ripresa; i tempi restano sul server, quindi il tablet può tornare alla scelta del meccanico (lo fa da solo dopo 3 minuti fermo) senza fermare il cronometro.

## Ruoli

| Ruolo | Può |
|---|---|
| Amministratore | tutto, più utenti e impostazioni |
| Ufficio | accettazione, ordini, planning, bacheca, clienti, veicoli, meccanici; può aprire anche il tablet |
| Tablet officina | solo il tablet: è l'utente con cui si collega il tablet in officina |
