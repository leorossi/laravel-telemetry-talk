# Laravel al microscopio — EXTRA

Materiale di supporto al talk **“Laravel al microscopio — Dove sono finiti i quattro secondi?”** (LaravelDay 2026).

Qui finisce tutto ciò che non sta nelle slide: le risposte alle domande probabili, due approfondimenti sui vendor e su Grafana, e gli spunti che erano nell'appendice “Se c'è tempo”. È un documento, non un deck Slidev: serve a chi clona il repository, a chi vuole rileggere un passaggio dopo il talk e a chi deve preparare una risposta in Q&A.

---

## Indice

- [Domande e risposte](#domande-e-risposte)
  - [Pulse basta?](#pulse-basta)
  - [Che campionamento imposto?](#che-campionamento-imposto)
  - [OTel rallenta la mia app?](#otel-rallenta-la-mia-app)
  - [Posso usare Nightwatch e OTel insieme?](#posso-usare-nightwatch-e-otel-insieme)
  - [Che differenza c'è tra OTel e un APM?](#che-differenza-cè-tra-otel-e-un-apm)
  - [E Sentry / Datadog / New Relic?](#e-sentry--datadog--new-relic)
  - [Horizon o Pulse per le code?](#horizon-o-pulse-per-le-code)
  - [Datadog e New Relic supportano davvero OpenTelemetry?](#datadog-e-new-relic-supportano-davvero-opentelemetry)
  - [Grafana da dove prende i dati? Può leggere Nightwatch?](#grafana-da-dove-prende-i-dati-può-leggere-nightwatch)
- [Approfondimenti fuori dal talk](#approfondimenti-fuori-dal-talk)
  - [SQL Commenter](#sql-commenter)
  - [Un backend per i tre segnali](#un-backend-per-i-tre-segnali)
  - [Monitorare i buchi](#monitorare-i-buchi)
  - [Error tracking e APM](#error-tracking-e-apm)
  - [Cose Laravel da menzionare](#cose-laravel-da-menzionare)
- [Fonti](#fonti)

---

## Domande e risposte

Tenete le risposte sotto i 60 secondi. Se non sapete qualcosa, dirlo è meglio che improvvisare: il talk copre deliberatamente solo una parte del panorama.

### Pulse basta?

Pulse risponde a “la mia app è sana?”. Non risponde a “quale span di questa richiesta era lento”. Per quello servono le tracce.

### Che campionamento imposto?

100% in staging. Lo abbassate in produzione quando conoscete il vostro volume. La demo gira al 100%.

### OTel rallenta la mia app?

Un po' — ogni span è lavoro. In pratica è poco; il campionamento è la manopola.

### Posso usare Nightwatch e OTel insieme?

Sì. L'applicazione demo lo fa: Nightwatch guarda gli eventi Laravel, OTel produce le tracce per Jaeger. Sono due lenti sullo stesso traffico.

### Che differenza c'è tra OTel e un APM?

La versione breve:

> **OTel è lo standard su come *produrre e trasportare* la telemetria. Un APM è il prodotto che la *conserva, la mostra e ci ragiona sopra*.**

Sono due categorie diverse, non due concorrenti. OpenTelemetry è una specifica con SDK e Collector, progetto CNCF, vendor-neutral e gratuito: definisce il formato e il trasporto (OTLP) dei dati. Un APM — Datadog, New Relic, Dynatrace, Nightwatch — è un prodotto commerciale che aggiunge tutto quello che OTel deliberatamente non fa: storage, retention, UI, alerting, SLO, service map, correlazione, profiling, supporto.

| | OpenTelemetry | APM |
|---|---|---|
| Cos'è | Specifica + SDK + Collector (CNCF) | Prodotto commerciale, spesso hosted |
| Chi lo fa | Community, vendor-neutral | Un'azienda |
| Cosa dà | Il formato e il trasporto dei dati | Storage, UI, alert, SLO, correlazione, supporto |
| Costo | Gratis, open source | Licenza o consumo |
| Cosa non fa | Retention, alert, dashboard, supporto | Non dovrebbe obbligarvi a strumentare due volte |

L'analogia coerente con il talk: se OTel è **il PDF delle tracce**, l'APM è il programma che apre il PDF, lo indicizza e vi manda la notifica. Il formato è standard, il lettore no.

Il rovescio della medaglia: **Jaeger + Prometheus + Grafana, messi insieme, sono un APM open source**. Non è un prodotto unico, è un assemblaggio che qualcuno deve gestire — è esattamente l'asse verticale della slide “Tre lenti, due assi”.

#### “Supportiamo OpenTelemetry” non vuol dire “siamo intercambiabili”

- **OTLP è diventato la porta d'ingresso standard** verso i vendor: non dovete strumentare due volte per cambiare backend.
- **Il prodotto resta proprietario su come *capisce* i dati**: correlazioni, profiling, security, anomaly detection, regole di ingest. Datadog stesso documenta che con il solo SDK OTel alcune feature non funzionano (vedi [Datadog + OpenTelemetry compatibility](https://docs.datadoghq.com/opentelemetry/compatibility/)).
- **Nightwatch è l'altro capo dello spettro**: APM verticale su Laravel, agente proprio su `127.0.0.1:2407`, nessun endpoint OTLP. Non è arretrato: ha scelto l'integrazione profonda col framework invece della neutralità del protocollo.

Quindi la frase onesta: **OTel non sostituisce l'APM, commoditizza il livello dell'agente.** Il valore si sposta dal “come raccolgo” al “cosa ci faccio”.

Un'attenzione: se vi tirano in mezzo SigNoz, Grafana Cloud o Uptrace, la linea si è sfumata — sono backend OTel-native, cioè APM con l'ingresso standard aperto. Il criterio resta quello dei due assi, non l'etichetta.

#### La frase da usare

> “OpenTelemetry dice come i dati escono dalla vostra app e viaggiano; non vi dà dashboard, alert, retention né supporto. Un APM moderno accetta OTLP, quindi non strumentate due volte — ma continuate a pagare qualcuno per *capire* quei dati. La domanda vera non è ‘OTel o APM’, è: voglio possedere i dati e l'infrastruttura, o solo il problema?”

### E Sentry / Datadog / New Relic?

Datadog e New Relic hanno ingest OTLP nativo: potete mandare loro telemetria senza riscrivere la strumentazione. Attenzione però: “accettano OTLP” non significa che ogni feature del prodotto funzioni identica con la sola strumentazione OTel. Sentry è un discorso a parte: è error tracking, non un backend di tracce generico; non date per scontato che parli OTLP.

### Horizon o Pulse per le code?

Horizon è più profondo sulle code — retry, bilanciamento, gestione dei job falliti. Pulse vi dà il numero. Se le code diventano il vostro mondo, Horizon è il passo successivo.

### Datadog e New Relic supportano davvero OpenTelemetry?

Sì, ma la frase va maneggiata. La versione breve:

> **OTLP è diventato la porta d'ingresso standard. Il prodotto e l'agente proprietario non sono spariti.**

Prima del 2019 ogni vendor aveva il suo agente e il suo protocollo. Oggi i vendor accettano sempre più spesso OTLP, ma continuano a vendere un prodotto proprietario sopra quei dati: correlazioni, profiling, security, alerting, AIOps. “Supportano OTel” non vuol dire “sono intercambiabili”.

#### La storia in cinque tappe

| Anno | Cosa succede |
|---|---|
| 2016–2018 | **OpenTracing** (CNCF) e **OpenCensus** (Google): due tentativi paralleli di standardizzare l'instrumentation. I vendor li supportano *in aggiunta* ai propri agenti. |
| 2019 | OpenTracing e OpenCensus confluiscono in **OpenTelemetry**. Datadog contribuisce le sue librerie di tracing al progetto: è l'origine di molta auto-instrumentation OTel. |
| 2021 | **New Relic** rende GA l'ingest nativo OTLP (early access ad aprile, GA a settembre). Il **Datadog Agent** inizia a ricevere OTLP: tracce e metriche dalle versioni 6.32/7.32, log dalle 6.48/7.48. |
| 2023–2025 | OTLP si stabilizza e diventa il trasporto di default anche fuori dal mondo OTel. I vendor aggiungono endpoint OTLP, collector compatibili e distribuzioni proprie (per esempio il DDOT Collector di Datadog). |
| 2026 | OpenTelemetry ottiene la **graduation CNCF** (maggio). Datadog rende GA l'**ingest OTLP diretto**, senza Agent né Collector (luglio). |

Fonti: [Datadog, settembre 2019](https://www.datadoghq.com/blog/opentelemetry-instrumentation/) · [New Relic, settembre 2021](https://newrelic.com/blog/news/open-telemetry-support-ga) · [Datadog Agent OTLP](https://docs.datadoghq.com/opentelemetry/otlp_ingest_in_the_agent/) · [Datadog, luglio 2026](https://www.datadoghq.com/blog/native-otel-with-datadog/)

#### Cosa vuol dire “supportare OTel”

Ci sono almeno tre livelli, e i vendor non sono tutti allo stesso livello:

1. **Accettano OTLP** nel loro backend. È il livello minimo: cambiate endpoint e ci mandate tracce, metriche e log.
2. **Espongono l'API OTel** dentro il proprio SDK, così potete usare l'API standard ma il SDK del vendor.
3. **Sono OTel-native**: funzionano davvero bene anche con SDK e Collector upstream, senza componenti proprietari.

Il terzo livello è quello che conta per la portabilità. Datadog stesso documenta che, usando solo l'SDK OTel, alcune feature restano fuori: App & API Protection, Continuous Profiler, Ingestion Rules; altre richiedono comunque l'Agent. La tabella di compatibilità è esplicita: [Datadog + OpenTelemetry compatibility](https://docs.datadoghq.com/opentelemetry/compatibility/).

Quindi la frase onesta da palco è:

> “OTLP è diventato lo standard per *entrare* nei vendor. Il vendor resta proprietario per *capire* i dati.”

#### Il controesempio: Nightwatch

Nightwatch è l'altro capo dello spettro. È un prodotto ospitato, con un agente suo (`php artisan nightwatch:agent`, in ascolto su `127.0.0.1:2407`), nessun endpoint OTLP e nessuna versione self-hosted. Non è un “ritardatario”: è un prodotto verticale Laravel che sceglie l'integrazione profonda col framework invece della neutralità del protocollo. [Nightwatch FAQ](https://nightwatch.laravel.com/docs/guides/faqs.md)

---

### Grafana da dove prende i dati? Può leggere Nightwatch?

La versione breve:

> **Grafana non è un database di telemetria. È un livello di query e visualizzazione: ogni pannello interroga un data source, che i dati li conserva davvero.**

#### Nel progetto

Nel repository i datasource sono provisioned in `docker/grafana/provisioning/datasources/datasources.yml`:

- **Prometheus** → `http://prometheus:9090`, query PromQL;
- **Jaeger** → `http://jaeger:16686`, query delle tracce.

Prometheus **pull-a** `/metrics` dall'app ogni 5 secondi. Jaeger riceve OTLP sulla 4318 e Grafana interroga la sua API. Grafana non riceve OTLP e non conserva niente di tutto questo: il suo database interno serve per dashboard, utenti e stato degli alert.

#### Cosa può leggere

| Sorgente | Grafana la legge? | Come |
|---|---|---|
| Prometheus | Sì, core | PromQL |
| Jaeger | Sì, core | trace search |
| Tempo / Loki | Sì, core | TraceQL / LogQL |
| PostgreSQL, MySQL, MSSQL | Sì, core | SQL |
| CloudWatch, Azure Monitor, GCP | Sì, core | API cloud |
| Datadog | Sì, plugin ufficiale | API Datadog |
| New Relic | Sì, plugin ufficiale | NRQL / API New Relic |
| **Nightwatch** | **No** | solo webhook e MCP |
| endpoint OTLP | No, non direttamente | serve Tempo/Loki/Mimir/Alloy o Grafana Cloud |

Grafana è quindi anche il posto dove mettere side by side backend diversi — Prometheus, Jaeger, Datadog, New Relic — senza migrare i dati. È il senso della frase “Grafana non è un quarto strumento”: è il cruscotto, non il motore.

#### Nightwatch

Non esiste un datasource Grafana per Nightwatch, e Nightwatch non espone un'API di query pubblica né un endpoint OTLP. Le integrazioni programmatiche documentate sono due:

- **webhook** per eventi tipo `issue.opened`, `issue.resolved`, ecc. — utilissimi per alert e automazioni, ma non sono telemetria interrogabile;
- **MCP server** per assistenti AI, con OAuth, per listare applicazioni, leggere issue e stack trace.

Quindi la risposta è: **no, Grafana non può leggere Nightwatch out of the box**. Si potrebbe costruire un bridge custom — webhook → un servizio → Prometheus/Loki — oppure un plugin datasource, ma sarebbe un progetto a parte, non un'integrazione.

#### OTLP non entra in Grafana

Grafana (la UI) non è un ricevitore OTLP. Lo sono i backend che interroga: Tempo per le tracce, Loki per i log, Mimir per le metriche, oppure Grafana Cloud con il suo OTLP gateway. In self-hosted, il pezzo che riceve OTLP e lo smista è **Grafana Alloy** (o un OpenTelemetry Collector). Grafana resta il livello di query.

#### La frase da usare

> “Grafana non è un quarto strumento di monitoring: è il cruscotto che può stare sopra Prometheus, Jaeger, Datadog o New Relic. Nightwatch invece non è un data source: è un prodotto chiuso, e infatti non lo colleghi a Grafana.”

Fonti: [Grafana data sources](https://grafana.com/docs/grafana/latest/datasources/) · [Datasource Jaeger](https://grafana.com/docs/grafana/latest/datasources/jaeger/) · [Nightwatch MCP](https://nightwatch.laravel.com/docs/mcp-server) · [Nightwatch webhooks](https://nightwatch.laravel.com/docs/webhooks)

---

## Approfondimenti fuori dal talk

Questi erano gli spunti dell'appendice “Se c'è tempo”. Non fanno parte dei 40 minuti: servono se qualcuno li tira fuori in Q&A, o se volete lasciare al pubblico un'ultima idea.

### SQL Commenter

La stessa idea del `traceparent`, ma sul database: l'ORM appende un commento al testo SQL con il contesto della traccia, così anche il database riceve il `trace_id`.

```sql
select * from "orders" /*controller='SlowOrdersController', traceparent='00-…-01'*/
```

**Perché ci riguarda.** Chiude il cerchio della sezione sulle tracce: là “cosa esce dal vostro server” era un header HTTP, qui è dentro le query. È lo strumento del DBA.

**Chi lo legge.** PlanetScale Query Insights, pganalyze, Google Cloud SQL.

**Cugini.** Marginalia (Rails) e `application_name` di Postgres: la stessa idea, senza trace context.

**In Laravel.** `spatie/laravel-sql-commenter` è vivo, ma commenta l'*origine del codice*, non il traceparent. Il pacchetto che lo faceva è abbandonato dal 2022, e il repo OTel è **archiviato** da novembre 2025: il formato è vivo, la standardizzazione no.

**Due avvertenze.**

- **Plan cache.** Il `traceparent` cambia a ogni esecuzione, quindi ogni query diventa un testo diverso: niente riuso dei prepared statement. I commenti stabili (controller, route) sono cache-friendly; il traceparent no.
- **Privacy.** Il commento finisce nei log del database — e c'è un commenter che ci mette dentro l'id utente.

### Un backend per i tre segnali

Se cercate un posto solo per tracce, metriche e log, l'idea è mandare tutti e tre via OTLP allo stesso backend.

- **Grafana Cloud**: accetta tracce, metriche e log via OTLP. Cambiate l'endpoint e ci mandate tutto. È **composabile**: Loki per i log, Tempo per le tracce, Mimir per le metriche — più controllo, più pezzi da gestire.
- **SigNoz**: OTel-first, self-host o Cloud. Un solo datastore — ClickHouse — per tutti e tre i segnali. È **monolitico**: meno pezzi, meno controllo.

È la stessa scelta di “Tre strumenti, due assi”, spostata dal lato del backend. Serve un esempio concreto di collector? **Grafana Alloy** è la distribuzione che Grafana raccomanda.

### Monitorare i buchi

La telemetria che vive **dentro** l'app non può dirvi che l'applicazione è morta. Serve un occhio da fuori.

- **`spatie/laravel-schedule-monitor`** — una riga in tabella ogni volta che un task pianificato parte, finisce, fallisce o viene saltato.
- **`spatie/laravel-health`** — check di disco, database, cache, code, e un `ScheduleCheck` che verifica che lo scheduler sia vivo.
- **Oh Dear** — monitoring dall'**esterno**: uptime, certificati, link rotti, task pianificati. I due pacchetti sopra si integrano con lui.

### Error tracking e APM

**Error tracking** è una categoria che il talk tocca — `/fail`, gli errori raggruppati di Nightwatch — ma non nomina mai. **Sentry** ha un SDK Laravel first-class; **Flare**, di Spatie, è hosted e Laravel-native.

**APM**: Datadog, New Relic, Dynatrace. Il punto non è il prodotto: è che OTel può alimentare anche loro. Se vi serve un APM, non dovete strumentare due volte. È il senso della parola “standard” — e il motivo per cui la sezione su Jaeger non era un talk su Jaeger.

### Cose Laravel da menzionare

- **`Model::preventLazyLoading()`** — una riga e l'N+1 non arriva in produzione. Questa demo non ce l'ha, perché l'N+1 *è* la demo.
- **`php artisan pail`** — già installato qui. Tail dei log first-party, colorato e filtrabile: il posto giusto per vedere il `trace_id`.
- **Laravel Octane** — la risposta ai due problemi della sezione sulle metriche: il timer dei 5 secondi e le metriche non raccolte. Il pacchetto ha un `OctaneWorkerModeDetector` apposta.
- **La facade `Context`** — Laravel 11+. Contesto di richiesta che finisce nei log *e* viene serializzato nei job. È l'idea che OTel usa per il trace id, già dentro Laravel.

Bonus quasi gratis: **`DB::listen`**. «Il numero nel footer e gli span SQL in Jaeger sono lo *stesso* evento Laravel, ascoltato da due listener. Stessa applicazione, due strumenti.»

Tutte queste cose dicono la stessa cosa: **Laravel dà il punto di partenza, la telemetria è il passo dopo.**

---

## Fonti

- Datadog — *The future of tracing is open* (settembre 2019): <https://www.datadoghq.com/blog/opentelemetry-instrumentation/>
- New Relic — *Announcing general availability of New Relic's native OpenTelemetry support* (settembre 2021): <https://newrelic.com/blog/news/open-telemetry-support-ga>
- New Relic — *OTLP endpoint*: <https://docs.newrelic.com/docs/opentelemetry/best-practices/opentelemetry-otlp/>
- Datadog — *Set Up the Agent's OTLP Endpoint*: <https://docs.datadoghq.com/opentelemetry/otlp_ingest_in_the_agent/>
- Datadog — *OpenTelemetry compatibility*: <https://docs.datadoghq.com/opentelemetry/compatibility/>
- Datadog — *Use OpenTelemetry-native observability with Datadog* (luglio 2026): <https://www.datadoghq.com/blog/native-otel-with-datadog/>
- Grafana — *Data sources*: <https://grafana.com/docs/grafana/latest/datasources/>
- Grafana — *Jaeger data source*: <https://grafana.com/docs/grafana/latest/datasources/jaeger/>
- Grafana — *Datadog data source*: <https://grafana.com/docs/plugins/grafana-datadog-datasource/latest/>
- Grafana — *New Relic data source*: <https://grafana.com/docs/plugins/grafana-newrelic-datasource/latest/>
- Laravel Nightwatch — *Start guide* (agente su `127.0.0.1:2407`): <https://nightwatch.laravel.com/docs/start-guide>
- Laravel Nightwatch — *MCP Server*: <https://nightwatch.laravel.com/docs/mcp-server>
- Laravel Nightwatch — *Webhooks*: <https://nightwatch.laravel.com/docs/webhooks>
- Laravel Nightwatch — *FAQ* (prodotto fully-managed): <https://nightwatch.laravel.com/docs/guides/faqs>

---

## Per chi clona il repository

- Le slide sono in `slides/slides-export.pdf` (sorgente non incluso); il talk finisce su **“Grazie”** e sul **glossario**.
- Il run-of-show è in `docs/outline.md`.
- L'app demo e le istruzioni per avviarla sono nel `README.md` principale.
- Questo file è il posto giusto per aggiungere materiale che non deve stare nelle slide: nuove domande, dettagli su un vendor, esperimenti fatti dopo il talk.
