# TODO — pubblicazione repo e slide

**Obiettivo:** due repository, un PDF pubblico.

| Repository | Visibilità | Contenuto |
|---|---|---|
| `laravel-telemetry-slides` | **privato** | solo il deck Slidev (`slides.md` + asset), CI che produce il PDF |
| `laravel-telemetry-talk` | **pubblico** | app demo, README, EXTRA.md, outline — **niente `slides/`** |

Il PDF vive su **leorossi.it** e il README pubblico ci punta. Terzo repo coinvolto, solo come destinazione: `github.com/leorossi/leorossi.it`.

**Deadline:** talk **mercoledì 18 novembre, 15:10**. Le pubblicazioni (fasi 2–5) si possono fare *dopo* il talk: prima serve solo il PDF (fase 0). Non invertire l'ordine.

> Nota: questo file è un documento di lavoro. Se non vuoi pubblicarlo, aggiungi `TODO.md` al `.gitignore` prima del primo push del repo pubblico.

---

## Fase 0 — Prima del talk (15 minuti, stasera o domani mattina)

Solo questo è bloccante. Il resto è pubblicazione.

- [ ] Esporta il PDF dal container:
      ```bash
      docker compose -f docker-compose.slides.yml up -d
      docker compose -f docker-compose.slides.yml exec slides npx playwright install --with-deps chromium
      docker compose -f docker-compose.slides.yml exec slides npm run export -- --output slides-export.pdf
      docker compose -f docker-compose.slides.yml cp slides:/slidev/slides-export.pdf ./slides-export.pdf
      ```
- [ ] Verifica che il PDF **non** contenga le note: apri una pagina a caso, deve esserci solo la slide.
- [ ] Rinomina in modo stabile: `laravel-al-microscopio-2026.pdf`.
- [ ] Copia il PDF in **due posti**: laptop (cartella del talk) e telefono. Il PDF è il backup del talk, non il contrario.
- [ ] Controlla che il PDF parta dalla slide giusta e finisca su **“Grazie” + glossario**.
- [ ] (Opzionale) esporta anche la variante chiara: `npm run export:light`.

---

## Fase 1 — Creare il repo privato delle slide

Conserva la storia del deck estraendo solo `slides/`.

- [ ] Assicurati che il working tree sia pulito: `git status`.
- [ ] Crea il branch con il solo deck:
      ```bash
      git subtree split --prefix slides -b slides-only
      ```
- [ ] Crea il nuovo repo e importa il branch:
      ```bash
      mkdir ../laravel-telemetry-slides && cd ../laravel-telemetry-slides
      git init -b main
      git remote add deck ../2026-10-14-laravel-telemetry-talk
      git fetch deck slides-only
      git checkout -b main deck/slides-only
      git remote remove deck
      ```
- [ ] Sposta qui anche `docker-compose.slides.yml`, **adattando i path** (dopo lo split il contesto non è più `./slides`):
      ```yaml
      build:
        context: .
      volumes:
        - .:/slidev          # era ./slides:/slidev
      ```
- [ ] Ripara i riferimenti relativi che ora puntano fuori: in `README.md` compaiono `../EXTRA.md` e `../docker-compose.slides.yml`; in `slides.md` i link a `docs/outline.md` se ci sono.
- [ ] Rimuovi il branch temporaneo dal repo vecchio: `git branch -D slides-only` (dopo aver verificato che il push sia andato).
- [ ] Crea il repo su GitHub come **privato**, `git push -u origin main`.
- [ ] Aggiungi al `.gitignore` del repo slide gli artefatti: `*.pdf`, `_site/`.

---

## Fase 2 — Ripulire e pubblicare il repo dell'app

Il punto delicato: **il repo pubblico non deve contenere le slide nemmeno nella storia**, altrimenti le note restano recuperabili per sempre.

### 2a. Preparare il repo

- [ ] Installa lo strumento se manca: `brew install git-filter-repo`.
- [ ] Clona in una cartella nuova (filter-repo pretende un clone fresco):
      ```bash
      git clone <percorso-o-url-del-repo-attuale> ../laravel-telemetry-talk-public
      cd ../laravel-telemetry-talk-public
      ```
- [ ] Elimina `slides/` da **tutta la storia**:
      ```bash
      git filter-repo --path slides --invert-paths
      ```
      *Fallback senza filter-repo, se non ti interessa la storia:*
      ```bash
      git checkout --orphan main-clean
      rm -rf slides && git add -A
      git commit -m "feat: Laravel telemetry demo app e materiale del talk"
      git branch -M main
      ```
- [ ] Verifica che non resti traccia: `git log --all --oneline -- slides` deve essere vuoto, e `git grep -i "non leggeteli"` non deve trovare nulla.
- [ ] Aggiungi `/slides` al `.gitignore`, così il deck non rientra per sbaglio.
- [ ] Metti la cartella `dist/` fuori dai piedi: non è tracciata, ma assicurati che `slides/` non esista proprio nella working tree.

### 2b. Controllo pre-pubblicazione

Verifiche già fatte su questo repo (esito: pulite):

- [x] `.env` non è tracciato e non è mai stato committato.
- [x] `.env.example` ha `APP_KEY=` e `NIGHTWATCH_TOKEN=` vuoti.
- [x] Nessun cookie/sessione tracciato (`.local-cookies/` è in `.gitignore`).
- [x] Nessun token hardcoded nei file tracciati.

Da fare a mano:

- [ ] Rileggi il `README.md` come lo leggerebbe uno sconosciuto: percorsi personali, IP, nomi di container, screenshot con dati.
- [ ] Controlla `EXTRA.md` e `docs/outline.md`: restano pubblici? **Consiglio: sì** — sono materiale utile per chi assiste. Ma `outline.md` è il copione: se preferisci tenerlo riservato, escludilo ora, prima del push.
- [ ] Decidi la licenza (per una demo: MIT, oppure nessuna licenza esplicita).
- [ ] Aggiungi descrizione e topics al repo su GitHub (`laravel`, `opentelemetry`, `observability`, `talk`).

### 2c. Pubblicare

- [ ] Crea il repo su GitHub come **pubblico** (senza README automatico: la storia la porti tu).
- [ ] `git remote add origin git@github.com:<utente>/laravel-telemetry-talk.git`
- [ ] `git push -u origin main`
- [ ] Ricontrolla dal browser, da sloggato: la ricerca `non leggeteli` nel repo deve dare zero risultati.

---

## Fase 3 — Portare il PDF su leorossi.it

Il PDF viaggia dal repo **privato** delle slide al repo del sito (`github.com/leorossi/leorossi.it`); da lì il deploy che usi già lo mette sul server. Un solo passaggio nuovo da costruire, il resto è la pipeline che hai già.

Il vincolo di partenza: **gli asset di una release di un repo privato richiedono il login**, quindi un URL di release del repo slide non è scaricabile dal pubblico. Per questo il PDF deve finire in un repo pubblico — quello del sito.

### 3a. Capire il deploy del sito (5 minuti, da fare per primo)

- [ ] Apri il repo del sito e guarda come si va da `git push` al server: c'è una Action, un webhook, o fai `git pull` a mano?
      - **Action/webhook:** il push del PDF nel repo è l'ultimo passaggio. Fine.
      - **Deploy manuale:** dopo il push va lanciato anche il deploy, altrimenti il PDF è su GitHub ma non su leorossi.it. È il passaggio che si dimentica.
- [ ] Guarda dove stanno già i file statici (PDF, immagini, allegati) e usa quella cartella. Non inventare una struttura nuova il giorno prima.

### 3b. Token

- [ ] Fine-grained PAT: accesso **solo** a `leorossi/leorossi.it`, permesso `Contents: Read and write`. Nient'altro.
- [ ] Salvalo come secret `SITE_TOKEN` nel repo **privato** delle slide, mai nel pubblico.
- [ ] Metti una scadenza lunga e segnatela in calendario: un token scaduto fa fallire il deploy in silenzio, e te ne accorgi quando il link è già morto.

### 3c. Workflow

In `.github/workflows/slides.yml`, nel repo privato:

```yaml
name: slides
on:
  push:
    branches: [main]
  workflow_dispatch:

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 22
          cache: npm
      - run: npm ci
      - run: npx playwright install --with-deps chromium
      - run: mkdir -p _site && npx slidev export --output _site/laravel-al-microscopio-2026.pdf
      - uses: actions/upload-artifact@v4
        with:
          name: slides-pdf
          path: _site/*.pdf

      - name: Publish to leorossi.it
        env:
          SITE_TOKEN: ${{ secrets.SITE_TOKEN }}
        run: |
          git clone --depth 1 https://x-access-token:$SITE_TOKEN@github.com/leorossi/leorossi.it.git site
          mkdir -p site/talks            # ← la cartella dei file statici, da confermare in 3a
          cp _site/laravel-al-microscopio-2026.pdf site/talks/
          cd site
          git config user.name "github-actions"
          git config user.email "actions@github.com"
          git add talks/laravel-al-microscopio-2026.pdf
          if git diff --cached --quiet; then echo "PDF identico, niente da pubblicare"; exit 0; fi
          git commit -m "docs: slide Laravel al microscopio (LaravelDay 2026)"
          git push
```

Tre dettagli che sembrano pignoleria e non lo sono:

- `git add` puntuale, non `-A`: nel repo del sito non vuoi committare file in lavorazione o roba di altri.
- `--depth 1`: il repo del sito può avere anni di storia, non serve tutta.
- il controllo `git diff --cached --quiet` evita il commit vuoto, che a ogni push identico farebbe fallire il job.

### 3d. Verifiche

- [ ] Prima esecuzione con **Run workflow** (`workflow_dispatch`), non aspettando un push.
- [ ] Apri l'URL da browser **in incognito**: `https://leorossi.it/talks/laravel-al-microscopio-2026.pdf`
- [ ] Controlla la cache: `curl -I https://leorossi.it/talks/laravel-al-microscopio-2026.pdf`. Se il server risponde con un `Cache-Control` lungo e in futuro riusi lo stesso nome file, il pubblico può vedere la versione vecchia. Due rimedi: nome file versionato (`...-2026-11-18.pdf`) oppure `no-cache` su quella cartella.
- [ ] Verifica ora, non al QR code, che il server serva senza problemi un PDF di qualche MB.

> Alternativa onesta: se non vuoi un PAT in circolazione, copi il PDF a mano nel repo del sito quando pubblichi. Una volta l'anno, trenta secondi. Meno automatismo, zero token da far scadere.

---

## Fase 4 — Collegare README e slide

- [ ] In cima al `README.md` del repo pubblico, una riga tipo (stesso path scelto in 3a):

      > 📄 **Slide del talk:** [Laravel al microscopio — LaravelDay 2026](https://leorossi.it/talks/laravel-al-microscopio-2026.pdf)

- [ ] Nel repo slide, un README che dica dove finisce il PDF e che il repo è privato per scelta (le note del presenter stanno qui).
- [ ] Se serve un QR code: generane uno **dopo** aver fissato l'URL definitivo, e mettilo nell'ultima slide. `qrencode -o qr.png "https://leorossi.it/talks/laravel-al-microscopio-2026.pdf"`.
- [ ] Attenzione all'ordine: se rigeneri il PDF con il QR dentro, l'URL deve già esistere. In caso di dubbio, metti il QR solo sul sito, non in slide.

---

## Fase 5 — Dopo il talk

- [ ] Push della versione definitiva delle slide (quella effettivamente presentata) e nuovo export.
- [ ] Nome del PDF con la data se vuoi conservare le versioni: `...-2026-11-18.pdf`.
- [ ] Sposta le slide "backup" del talk in una cartella archiviata, o tagga il commit: `git tag -a laravelday-2026 -m "Versione presentata"`.
- [ ] Aggiungi in `EXTRA.md` il link al PDF, così chi clona il repo pubblico trova il deck.
- [ ] Rileggi la Q&A in `EXTRA.md` e aggiungi le domande vere arrivate dal pubblico (inclusa quella su OTel vs APM, se è uscita).

---

## Da decidere (non blocca, ma serve una risposta)

1. **Il repo del sito ha un deploy automatico o manuale?** Determina se il push del PDF basta o serve un secondo comando. Prima cosa da guardare in fase 3a.
2. **In quale cartella del sito stanno i file statici?** Uso quella, non una nuova (il workflow ha `talks/` come segnaposto).
3. **`docs/outline.md` resta pubblico?** È il copione del talk, non le note del presenter. Il mio consiglio: sì, è materiale onesto e utile.
4. **Licenza del repo pubblico?** MIT o nessuna licenza esplicita.
5. **La storia del repo app ti interessa?** Se no, il fallback con `--orphan` in fase 2a è più veloce e più pulito.

---

## Definition of done

- [ ] Il PDF è su leorossi.it, in un URL stabile, verificato in incognito.
- [ ] Il repo pubblico non contiene `slides/` in nessun commit.
- [ ] Il README pubblico linka il PDF.
- [ ] Il repo privato ricostruisce il PDF da zero con un click su *Run workflow*.
- [ ] Esiste una copia offline del PDF su laptop e telefono.
