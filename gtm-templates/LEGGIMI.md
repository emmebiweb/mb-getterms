# Template GTM mB – Guida rapida (v1.0)

Due file:
- **mB-GTM-Template-SitoWeb.json**: sito vetrina (form, click, GA4, Ads, Meta)
- **mB-GTM-Template-Ecommerce.json**: come sopra + e-commerce WooCommerce (richiede il plugin **GTM4WP** con l'integrazione WooCommerce attiva)

Il consenso è gestito da GetTerms. Il banner lo installa il plugin WordPress **mB GetTerms Consent**, quindi nel tag GTM non va inserito nessun ID.

## Import
1. GTM → Amministrazione → **Importa container** → scegli il file.
2. Area di lavoro: **nuova** (es. "Template mB").
3. Opzione: **Unisci** → **Rinomina tag, trigger e variabili in conflitto**.
   Su un container vuoto puoi usare **Sovrascrivi**.
4. Elimina dal container gli eventuali vecchi tag duplicati: GA4, Ads, Complianz, Meta.
5. Amministrazione → Impostazioni container → attiva **Panoramica consenso**.

## Configurazione: solo cartella "01 ⚙️ Configurazione"
Un trattino `-` spegne i tag che usano quella costante.

| Costante | Cosa inserire |
|---|---|
| CFG - GA4 ID | `G-XXXXXXX` |
| CFG - Ads ID | solo il numero, es. `16481231912` (anche `AW-…` va bene) |
| CFG - Ads label Telefono / Email / WhatsApp | etichetta conversione, oppure `-` |
| CFG - Ads label Form (predefinita) | etichetta usata dai form con valore `predefinita` |
| CFG - Form 1…4 ID | numero del form Gravity Forms, oppure `-` |
| CFG - Form 1…4 evento | nome evento GA4 (es. `form_contatti`, `form_preventivo`) |
| CFG - Form 1…4 Ads label | `predefinita`, un'etichetta propria, oppure `-` (nessuna conversione Ads) |
| CFG - Meta Pixel ID | ID pixel, oppure `-` |
| CFG - Ads label Acquisto *(e-commerce)* | etichetta conversione acquisto, oppure `-` |

## Cosa traccia
- **Form Gravity Forms**:
  - un solo trigger sul messaggio di conferma `#gform_confirmation_message_N`;
  - funziona con conferma di tipo **Testo** e invio AJAX, non con il reindirizzamento a una pagina;
  - i form non mappati inviano a GA4 l'evento `form_inviato` con il parametro `form_id`, senza conversione Ads.
- **Click**:
  - `click_telefono` (tel:);
  - `click_email` (mailto:);
  - `click_whatsapp` (wa.me, api/web.whatsapp.com, whatsapp://).
- **Google Ads**:
  - Google tag AW, con remarketing incluso;
  - linker conversioni;
  - conversioni per telefono, email, WhatsApp e form.
- **Meta**: PageView, Contact (sui click) e Lead (sui form). Partono solo con `ad_storage` concesso.
- **E-commerce**:
  - tutti gli eventi GA4 standard dal dataLayer di GTM4WP;
  - conversione Ads Acquisto con valore, valuta, ID ordine e conversioni avanzate (email e telefono hashati da GTM4WP);
  - Meta ViewContent, AddToCart, InitiateCheckout, AddPaymentInfo e Purchase.

## Consenso
- I tag Google partono in **modalità avanzata** di Consent Mode: senza consenso inviano ping anonimi per la modellazione e si aggiornano da soli quando l'utente accetta.
- Meta richiede `ad_storage`.

## In Google Ads (consigliato)
- **Conversioni principali**: Form e Telefono, più Acquisto per gli e-commerce.
- **Conversioni secondarie**: Email e WhatsApp.

## Verifica prima di pubblicare
Usa l'Anteprima (Tag Assistant):
1. **Senza scelta nel banner**: i tag Google partono con lo stato *denied* e i tag Meta risultano bloccati dal consenso.
2. **Dopo "Accetta"**: tutto è *granted*.
3. Invia ogni form mappato e verifica l'evento GA4 e la conversione Ads.
4. **Solo e-commerce**: aggiungi un prodotto al carrello e completa un ordine di test.

---

# Proprietà GetTerms demo – nuovo cliente

Nell'account GetTerms ci sono due proprietà modello da duplicare:
- **XXX-DEMO SITO WEB**: Cookie, Privacy, Acceptable Use e Termini di servizio.
- **XXX-DEMO ECOMMERCE**: in più ha i Termini di vendita (recesso 14 giorni, pagamenti, spedizioni) e la Politica di reso.

Entrambe includono:
- **Consent Mode** di Google attivo;
- la **configurazione consigliata**: opt-in UE/UK, blocco degli script, rifiuto in un clic, consensi per categoria;
- l'**elenco dei cookie** del template GTM: GA4, Google Ads, Meta e, solo nell'e-commerce, WooCommerce;
- la voce sull'**uso dell'intelligenza artificiale** per testi, immagini e video, nei Termini.

## Duplicare per un cliente
1. Dalla lista delle proprietà apri il menu ⋮ della demo e scegli **Duplicate Policy**.
2. Inserisci nome, sede e **URL reale del sito**. L'URL va scritto qui: dopo non si può più cambiare, salvo chiederlo al supporto GetTerms.
3. Nella proprietà nuova **riattiva il Consent Mode** (Cookie Banner → Google Consent Mode → On): la duplicazione lo spegne.
4. Apri **Cookie Banner → Configuration** e premi **Save** senza cambiare nulla. La duplicazione mostra le opzioni spuntate ma non le salva: senza questo passaggio mancano il pulsante preferenze, "Rifiuta tutto" e il blocco degli script.
5. In **Appearance** imposta i colori del brand: sfondo, testo, pulsanti, testo dei pulsanti e link. Forma e posizione sono già impostate nelle demo: banner *Card* in basso a sinistra, tutti i pulsanti primari, pulsante preferenze a destra.
6. Rifai l'**elenco dei cookie**, perché la duplicazione non lo copia. Lancia *Cookie Scan* sul sito reale oppure aggiungili a mano da *Cookie Management*.
7. In *Company Details* compila ragione sociale e nome visualizzato.
8. Sostituisci tutti i segnaposto: cerca **"XXX"** e **"xxx-"** in ogni policy.

## Segnaposto usati
| Segnaposto | Cosa inserire |
|---|---|
| `XXX-NOME-AZIENDA` / `XXX-RAGIONE-SOCIALE` | nome commerciale / ragione sociale |
| `XXX-REFERENTE-PRIVACY` / `XXX-REFERENTE` | referente privacy / contatto per i Termini |
| `https://xxx-dominio.it/contatti/` | pagina contatti |
| `https://xxx-dominio.it/cookie-policy/` | pagina Cookie Policy del sito (link nel banner, in *Cookie Banner → Content*) |
| `https://xxx-dominio.it/privacy-policy/` | pagina Privacy Policy (nei Termini) |
| `xxx-email@xxx-dominio.it` | email per i resi *(e-commerce)* |
| `XXX-REA`, `XXX-PIVA` | numero REA, partita IVA |
| `XXX-INDIRIZZO`, `XXX-CITTA`, `XXX-PROVINCIA`, `XXX-CAP` | sede legale |
| `XXX-HOSTING` | provider hosting (es. SiteGround, Aruba) |
| `XXX-SERVIZIO-NEWSLETTER` | servizio newsletter (es. Acumbamail) |
| `XXX-GATEWAY-PAGAMENTO`, `XXX-CORRIERE` | gateway di pagamento e corriere *(e-commerce)* |
| `XXX-DOMINIO` | dominio nei cookie (Cookie Management) |

## Adattare al cliente
- **Niente Meta o Google Ads**: toglili da Cookie Management e dai servizi di terze parti della Privacy.
- **Niente newsletter**: togli il servizio newsletter, la finalità "Email newsletters" e la voce GDPR "raccogliamo email per marketing".
- **Politica di reso**: GetTerms genera la frase "attendere *almeno* N giorni affinché il reso sia processato". Il Codice del Consumo invece prevede il rimborso **entro 14 giorni** dal recesso: correggi la frase in *Custom Content*.
- **Banner**: nel plugin mB, scheda **Policy**, scegli le pagine del sito per i link del banner.
- **Pulsante preferenze** (plugin mB, scheda Aspetto): *Personalizzato*, quadrato 40×40, colore del brand, lato destro, 90 px dal basso, così resta sopra la barra di navigazione su mobile.
- **Pulsante WhatsApp o chat in basso a sinistra**: alzalo sopra il banner con un CSS aggiuntivo (Aspetto → Personalizza → CSS aggiuntivo), ad es. `bottom:220px` su desktop e `250px` su mobile. In alternativa usa *Distanza dal basso* nel plugin per alzare il banner.
