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
9. Controlla il testo pubblicato dei **Termini**: non devono comparire codici tipo `mpkwali0-…` o `mp6oadoc-…`. Sono campi del questionario aggiunti da GetTerms dopo la creazione delle demo; le demo ora li hanno già compilati (*Edit Policy* → importo minimo di responsabilità **100 €**, periodo di risoluzione informale **30** giorni). Se ne compaiono altri, compilali in *Edit Policy*.
10. Controlla la **Cookie Policy**: nelle demo il primo blocco (*Custom Content* → Header Information) è personalizzato in tutte le lingue. Rimanda al banner per accettare/rifiutare e modificare la scelta, e al pannello preferenze per l'elenco dei cookie. Sostituisce il testo generato, che rimandava solo alle impostazioni del browser e diceva di non coprire i cookie di terze parti, cosa non conforme alle linee guida del Garante. Se nella proprietà duplicata il blocco non è personalizzato, ricopialo dalla demo lingua per lingua.
11. Dopo ogni scansione **disattiva** in *Cookie Management* i cookie dei vecchi CMP (es. `cmplz_*`), classifica gli *Unclassified* (`gtm_region_override` → Essential, `_gcl_ls` e `test_cookie` → Advertising/Marketing) e controlla che il pannello preferenze del banner sul sito li mostri aggiornati (la cache del banner dura qualche minuto).

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
- **Acceptable Use**: serve solo se gli utenti hanno un account, pubblicano contenuti o caricano file (area clienti, forum, piattaforma). Per un sito vetrina eliminala (*Delete Policy*) e poi premi **Collega / Sincronizza** nel plugin mB. Le regole d'uso essenziali sono già nei Termini.
- **Condizioni generali di vendita del cliente** (PDF o pagina propria): nei Termini aggiungi, con *Custom Content* → *Add new section* dopo "Accettazione dei presenti Termini", in ogni lingua, una sezione «Vendita dei prodotti e foro competente»: il sito è informativo/di catalogo, la vendita è regolata solo dalle Condizioni generali di vendita (link) che prevalgono sui Termini, e il foro competente è quello indicato nelle CGV (es. Padova), salvo fori inderogabili.
- **Elenco cookie sotto la Cookie Policy**: dal plugin mB 1.4.5 compare da solo sotto lo shortcode della Cookie Policy (tabella per categoria + pulsante «Gestisci le preferenze sui cookie»). Si spegne in scheda Policy; per metterlo altrove `[mb_getterms_cookie_list]`.
- **Termini di servizio**: pubblicali nella pagina o scheda "Note legali", sotto i dati aziendali, con lo shortcode `[mb_getterms_policy type="terms"]`. Contengono anche la clausola sull'intelligenza artificiale.
- **Banner**: nel plugin mB, scheda **Policy**, scegli le pagine del sito per i link del banner.
- **Pulsante preferenze** (plugin mB, scheda Aspetto): *Personalizzato*, quadrato 40×40, colore del brand, lato sinistro, 90 px dal basso, così resta sopra la barra di navigazione su mobile.
- **Pulsante preferenze solo in home** (plugin mB 1.4.6, scheda Aspetto → *Pagine*): se il cliente vuole il pulsante solo in home, attiva l'opzione e aggiungi al menu del footer un link personalizzato «Preferenze cookie» con URL `#preferenze-cookie` (oppure `[mb_getterms_preferences_link]`): da ogni pagina deve restare possibile cambiare la scelta.
- **Angoli del banner** (plugin mB 1.4.6, scheda Aspetto → *Banner*): 0 per un banner squadrato come i pulsanti.
- **Pulsante WhatsApp o chat in basso a sinistra**: alzalo sopra il banner con un CSS aggiuntivo (Aspetto → Personalizza → CSS aggiuntivo), ad es. `bottom:220px` su desktop e `250px` su mobile. In alternativa usa *Distanza dal basso* nel plugin per alzare il banner.

---

# Migrazione da Complianz – regole

1. **Analisi** (nessuna modifica): plugin e tema, dove viene inserito GTM (spesso da Complianz stesso), container GTM, pagine con shortcode `[cmplz-document]`, pagine regionali di Complianz, form e loro consenso, lingue del sito.
2. **Ok del cliente/utente**, poi: proprietà GetTerms dalla demo, workspace GTM nuovo (non pubblicato), plugin mB installato e configurato ma **spento**.
3. L'utente **disattiva ed elimina Complianz**. Subito dopo: plugin acceso, pubblicazione GTM, sostituzione degli shortcode Complianz (altrimenti restano visibili come testo).
4. **Pagine delle policy**: una sola pagina (o schede) con Privacy e Cookie GetTerms; i Termini in "Note legali". Le pagine regionali di Complianz (UE, UK, US, CA, AU, ZA, BR, opt-out) non servono: GetTerms adatta il banner per paese (UE/UK opt-in, USA/Canada opt-out, Australia nessun banner, resto del mondo opt-in) e la policy GDPR copre gli altri casi.
5. **Redirect 301** delle vecchie pagine verso la nuova, **una regola per lingua** (con TranslatePress gli URL tradotti sono diversi), poi pagine nel cestino. Anche se non risultano indicizzate: costano nulla e coprono link e preferiti vecchi.
6. **Siti multilingua**: plugin con lingua che segue la pagina. GetTerms ha le policy native in IT, EN, FR, DE, ES; per le altre lingue (RU, AR, CS, RO, PL…) il plugin userebbe la lingua predefinita (italiano): imposta l'inglese con il filtro `mb_gt_lang` nel functions.php del tema figlio. TranslatePress non traduce le policy GetTerms.
7. **Consenso nei form** (Gravity Forms ecc.), testo standard: «Confermo di aver letto l'[informativa sulla privacy](/pagina-policy/) e autorizzo il trattamento dei miei dati personali, nel rispetto della normativa vigente, per dare seguito alla mia richiesta.» (niente riferimenti alla legge 196/03, link relativo così segue la lingua).
8. **Pulsante preferenze** sopra barre fisse (CTA, torna su): usa il filtro `mb_gt_widget_css` nel functions.php del tema figlio (il widget è in Shadow DOM; le variabili CSS impostate sul body lo attraversano).
9. **Pulizia Complianz** solo dopo la verifica e con l'ok: tabelle `{prefix}cmplz_*`, opzioni `cmplz%`/`complianz%`/`%_cmplz_%`, postmeta `%cmplz%`, i 3 cron `cmplz_every_*`, cartella `uploads/complianz`, CSS del tema con `cmplz`, svuotamento cache. Attenzione: la query sui postmeta `%cmplz%` cancella anche eventuali copie di sicurezza con "cmplz" nel nome: chiamale in altro modo.
10. **Scansione GetTerms** e sistemazione dei cookie come al punto 11 di "Duplicare per un cliente".
