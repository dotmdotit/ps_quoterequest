<p align="center"><img src="logo.svg" width="96" alt="dotmquoterequest"></p>

# dotmquoterequest — Richiesta preventivo per PrestaShop

Modulo PrestaShop che aggiunge al negozio un **carrello dei preventivi**: il cliente aggiunge i prodotti con il pulsante «Aggiungi al preventivo», li rivede in una pagina dedicata come in un normale carrello e invia la richiesta. Il negozio la esamina nel back office, applica gli sconti e la approva: il cliente riceve un'email con il preventivo in PDF e un link che lo porta al checkout con i prodotti e lo sconto già applicati.

> *English summary: PrestaShop module that adds a **quote cart**. Registered customers add products with an "Add to quote" button, review them on a cart-like page and send a quote request. The shop reviews it in the back office, sets per-line discounts and approves it: the customer gets an email with a PDF quote and a secure link that opens the checkout with the products and the agreed discount already applied. Front office, PDF and emails in Italian and English (other languages fall back to English); back office in Italian.*

## Funzionalità

- **Pulsante «Aggiungi al preventivo»** nella pagina prodotto (hook `displayProductActions`), con la variante e la quantità scelte. Per i visitatori il pulsante diventa «Accedi per richiedere un preventivo».
- **Carrello preventivi**, pagina pubblica all'indirizzo `/preventivo` (configurabile):
  - righe con immagine, link al prodotto, variante, riferimento, prezzo unitario e totale;
  - quantità con pulsanti +/− (salvataggio automatico), rimozione delle righe, «Svuota il preventivo»;
  - riepilogo con totale di listino, note per il negozio e pulsante «Invia la richiesta di preventivo»;
  - importo minimo facoltativo, con barra di avanzamento e messaggio su quanto manca;
  - i prezzi seguono il listino (e i prezzi riservati al cliente) finché la richiesta non è inviata;
  - chi non ha fatto l'accesso vede l'invito ad accedere o registrarsi, con ritorno alla pagina.
- Dopo «Aggiungi al preventivo» il cliente va al carrello preventivi, oppure (a scelta) resta sulla pagina prodotto con un messaggio e il link.
- **Link «Preventivo» nell'header** con il numero di prodotti (hook `displayNav2`), utilizzabile anche altrove come widget: `{widget name='dotmquoterequest'}`.
- **Area clienti**: «Il mio preventivo» e «I miei preventivi» (`/i-miei-preventivi`), con stato, dettaglio, sconto concordato, PDF e pulsante «Completa l'acquisto».
- **Back office** in *Clienti → Preventivi*: indicatori (ricevuti, da gestire, mandati, in attesa di pagamento, pagati), elenco con filtri, dettaglio con modifica delle righe (quantità, prodotti aggiunti o tolti) e **sconto per riga** in percentuale o importo, oppure uno sconto sul totale ripartito sulle righe. Si può anche **creare un preventivo per conto di un cliente**.
- **Approvazione**: crea il carrello del cliente e uno sconto riservato a lui (con la stessa scadenza del preventivo), numera il preventivo (es. `P-10001`) e manda l'email con il **PDF** e il link. La scadenza si può prorogare; il preventivo si può annullare (il cliente riceve un'email con la nota).
- **Checkout del preventivo**: prodotti e quantità bloccati, niente altri buoni sconto, solo i metodi di pagamento scelti per i preventivi; il cliente può annullare il preventivo e ritrova il suo carrello. Tornando a navigare il negozio esce dal carrello del preventivo, che resta riapribile dal link.
- **Stati** seguiti in automatico: da gestire → mandato al cliente → in attesa di pagamento → pagamento accettato (oppure scaduto/annullato).
- **Notifica al negozio** via email (uno o più indirizzi) con l'elenco dei prodotti, «rispondi a» impostato sul cliente.
- **Aspetto neutro**, adatto a qualsiasi tema: colori grigi, un solo colore configurabile per pulsanti ed email, variabili CSS ridefinibili dal tema.

## Requisiti

- PrestaShop **1.7.7** o successivo (pensato per il tema *classic* e i temi derivati)
- PHP 7.2 o successivo
- URL semplificati consigliati (per gli indirizzi `/preventivo` e `/i-miei-preventivi`); senza, le pagine restano raggiungibili su `index.php?fc=module&module=dotmquoterequest&controller=quote` e `…&controller=quotelist`

## Installazione

1. Scarica la repo (*Code → Download ZIP*, oppure una release).
2. La cartella del modulo **deve chiamarsi `dotmquoterequest`**. Lo ZIP di GitHub contiene la cartella `ps_quoterequest-main`: rinominala e ricrea lo ZIP:
   ```sh
   unzip ps_quoterequest-main.zip && mv ps_quoterequest-main dotmquoterequest
   zip -r dotmquoterequest.zip dotmquoterequest -x '*.git*' -x '*/CLAUDE.md'
   ```
3. In back office: *Moduli → Gestione moduli → Carica un modulo* e scegli `dotmquoterequest.zip`.
   In alternativa copia la cartella in `modules/dotmquoterequest/` sul server e installa il modulo dal Gestore moduli.
4. Apri *Configura* e controlla le impostazioni (vedi sotto).
5. Svuota la cache in *Parametri avanzati → Prestazioni*, così gli indirizzi delle pagine vengono registrati.

L'installazione crea le tabelle `ps_dotmquoterequest_quote` e `ps_dotmquoterequest_detail`, la voce *Clienti → Preventivi* e aggancia il modulo a `moduleRoutes`, `displayHeader`, `displayNav2`, `displayProductActions`, `displayCustomerAccount`, `displayPaymentTop`, `actionFrontControllerInitAfter`, `actionPresentCart`, `actionPresentPaymentOptions`, `actionValidateOrder`, `actionOrderStatusPostUpdate`.

## Configurazione

| Impostazione | Predefinito | Note |
|---|---|---|
| Validità del preventivo approvato | 15 giorni | Durata del link e dello sconto. Dal back office si può prorogare il singolo preventivo. |
| Importo minimo | 0 (nessuno) | Totale IVA inclusa a prezzi di listino, nella valuta predefinita (convertito per le altre valute). |
| Email per le notifiche | email del negozio | Uno o più indirizzi separati da virgola. |
| Prefisso del numero | `P-` | I preventivi inviati sono numerati da 10001 in avanti. |
| Dopo «Aggiungi al preventivo» | apri il carrello preventivi | In alternativa: resta sulla pagina prodotto con un messaggio e il link. |
| Link nell'header | sì | Link con il numero di prodotti in `displayNav2`, per i clienti collegati. |
| Pagamenti ammessi per i preventivi | tutti | Ad esempio solo il bonifico. Se tra i pagamenti c'è *ps_wirepayment*, il PDF riporta le coordinate bancarie. |
| Indirizzo del carrello preventivi | `preventivo` | Lettere minuscole, numeri e trattini. Dopo la modifica svuota la cache. |
| Indirizzo di «I miei preventivi» | `i-miei-preventivi` | Come sopra. |
| Colore pulsanti | vuoto (`#2b2b2b`) | Formato `#rrggbb`, ad esempio il colore principale del tema. Usato anche nelle email. |

La pagina di configurazione mostra anche gli indirizzi completi delle due pagine. Aprirla salva la cartella del back office, usata per il link «Apri il preventivo» nelle email di notifica.

## Dopo l'installazione

- Controlla che il pulsante compaia nella pagina prodotto: se il tema non usa l'hook `displayProductActions`, aggiungi `{hook h='displayProductActions' product=$product}` nel template `catalog/_partials/product-add-to-cart.tpl` del tema.
- Se il tema non ha l'hook `displayNav2`, inserisci il link dove preferisci con `{widget name='dotmquoterequest'}`.
- Collega il carrello preventivi (`https://tuonegozio.it/preventivo`) dove serve, ad esempio nel menu o nel footer.
- Fai una prova completa: aggiunta dei prodotti, invio della richiesta, approvazione con sconto dal back office, email con PDF, acquisto dal link e cambio di stato dell'ordine a «Pagamento accettato».

## Personalizzazione

Il modulo non contiene nulla di specifico per un negozio: tutto passa dalla configurazione o dagli override del tema.

- **Template**: copia i file da `views/templates/` in `themes/<tuo-tema>/modules/dotmquoterequest/views/templates/` e modificali lì (vale anche per i template del PDF in `views/templates/pdf/`).
- **Stile**: il CSS usa solo variabili `--dqr-*` (`--dqr-accent`, `--dqr-accent-ink`, `--dqr-ink`, `--dqr-muted`, `--dqr-line`, `--dqr-soft`, `--dqr-card`, `--dqr-radius`, `--dqr-font-display`, …) definite su `.dqr`, `.dqr-nav` e `.dqr-lock`. Ridefiniscile nel CSS del tema, ad esempio:
  ```css
  .dqr, .dqr-nav { --dqr-radius: 0; --dqr-font-display: "Playfair Display", serif; }
  ```
- **Testi** (front office, PDF, oggetti delle email): sono in `lang/it.php` (riferimento) e `lang/en.php`. Per un'altra lingua aggiungi `lang/<iso>.php` con le stesse chiavi (basta tradurre quelle che vuoi: le mancanti arrivano dall'inglese). Le lingue senza file usano l'inglese.
- **Email**: `mails/<iso>/dotmquoterequest_admin.{html,txt}` (notifica al negozio), `dotmquoterequest_approved` (preventivo pronto, con PDF) e `dotmquoterequest_cancelled` (annullamento). Le lingue senza cartella usano l'inglese. Il colore è nella variabile `{dqr_color}`.

## Struttura

```
dotmquoterequest.php                          classe del modulo: installazione, configurazione, hook, checkout del preventivo
classes/DotmQuoteRequestQuote.php             preventivo (tabella ps_dotmquoterequest_quote)
classes/DotmQuoteRequestDetail.php            righe (tabella ps_dotmquoterequest_detail)
classes/DotmQuoteRequestManager.php           approvazione, proroga, annullamento, email
classes/DotmQuoteRequestPdf.php               PDF del preventivo
controllers/front/quote.php                   carrello preventivi (/preventivo)
controllers/front/addtoquote.php              «Aggiungi al preventivo» (AJAX)
controllers/front/quotelist.php               I miei preventivi (/i-miei-preventivi), dettaglio e PDF
controllers/front/activate.php                link del preventivo approvato → checkout; annullamento dal cliente
controllers/admin/AdminDotmQuoteRequestController.php   Clienti → Preventivi
lang/                                         testi (it, en)
mails/                                        email (it, en)
views/templates/{front,hook,admin,pdf}/       template Smarty
views/css/front.css, views/js/front.js        stile e comportamento del front office
views/js/admin.js                             ricerca clienti/prodotti e sconti per riga nel back office
```

## Aggiornamento e disinstallazione

- Per aggiornare, sostituisci i file del modulo e avvia l'aggiornamento dal Gestore moduli: gli script in `upgrade/` (dalle versioni successive alla 1.0.0) aggiornano database e configurazione. Dopo modifiche ai template svuota la cache.
- La disinstallazione toglie la voce di menu, gli hook e la configurazione, ma **lascia le tabelle con i preventivi**: reinstallando il modulo li ritrovi. Per eliminarli del tutto cancella a mano `ps_dotmquoterequest_quote` e `ps_dotmquoterequest_detail` (gli sconti creati per i preventivi restano in *Catalogo → Sconti*).

## Licenza

Rilasciato con licenza [MIT](LICENSE).

## Autore

Sviluppato da [dotm.it](https://dotm.it).
