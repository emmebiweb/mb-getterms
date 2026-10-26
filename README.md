# mB GetTerms Consent

Plugin WordPress leggero per installare il CMP **GetTerms** senza il plugin ufficiale, con il banner come primo script dell'`<head>` e Google Tag Manager subito dopo. Pensato per Google Consent Mode v2 gestito in GTM.

## Cosa fa

- Stampa nell'head, prima di ogni altro script accodato da WordPress:
  1. preload + **blocker** GetTerms (sincrono)
  2. **widget** GetTerms (async)
  3. **Google Tag Manager** (opzionale), più il noscript dopo `<body>`
- Configurazione con **token GetTerms** (come il plugin ufficiale) oppure incollando l'**embed code**.
- Lingua fissa, oppure che segue la lingua della pagina (WPML, Polylang, TranslatePress), più il rilevamento automatico (`?auto=true`).
- Policy via shortcode: `[mb_getterms_policy type="privacy|cookie|terms" lang="it"]`, più gli shortcode compatibili con il plugin ufficiale (`[getterms_privacy_it]`...).
- Esclude banner e GTM dagli editor visuali (WPBakery, Elementor, Divi, Bricks...).
- Esclude gli script GetTerms dalle ottimizzazioni (WP Rocket, SiteGround Speed Optimizer, LiteSpeed, Autoptimize, Perfmatters, Cloudflare Rocket Loader).
- Mostra un avviso se ci sono altri CMP attivi (Complianz, Cookiebot, CookieYes, iubenda...) o plugin che potrebbero inserire GTM due volte.
- Ha il pulsante **Verifica homepage**, che controlla che il banner ci sia una sola volta e sia il primo script, che GTM ci sia una sola volta e venga caricato dopo il banner, e che non ci siano altri CMP.
- Si aggiorna automaticamente dalle release di questo repository.
- **Pulsante preferenze cookie** (quello fisso che riapre il banner): stile originale GetTerms oppure **personalizzato**, con forma quadrata o tonda, colori di sfondo, del passaggio del mouse e dell'icona, dimensioni, lato e distanze. Il widget GetTerms usa uno Shadow DOM che il CSS del tema non raggiunge: il plugin inserisce lo stile direttamente nel widget e lo ripristina quando il widget si ridisegna. Il pulsante resta quello originale, con la stessa funzione e la stessa accessibilità.
- **Link alle policy nel banner**: puoi scegliere le pagine del sito (Cookie Policy e Privacy Policy) a cui far puntare i link del banner, al posto delle pagine ospitate da GetTerms. I link si aprono nella stessa scheda.
- Pagina impostazioni a schede (Generale, Google Tag Manager, Aspetto, Policy, Verifica), con il selettore colori di WordPress e l'anteprima dal vivo del pulsante preferenze.
- **Distanza del banner dal basso**: alza il banner (card, pill o popup) per non coprire pulsanti fissi come WhatsApp o la chat.
- **Angoli dei pulsanti del banner**: raggio in px per i pulsanti Accetta / Rifiuta / Preferenze / Salva, sia nel banner sia nella finestra preferenze (vuoto = come impostato in GetTerms).
- **Liste prodotti per GTM4WP** (opzione nella scheda Google Tag Manager, spenta di default): molti temi (Impreza, XStore...) disegnano shop e categorie con una griglia propria che non chiama l'hook di WooCommerce usato da GTM4WP, quindi `view_item_list` non parte. Con l'opzione attiva, sulle pagine shop, categoria, tag e ricerca il plugin aggiunge i dati prodotto di GTM4WP che mancano (generati da GTM4WP stesso, senza doppioni) e l'evento torna a funzionare. Non copre caroselli o griglie in pagine normali, né i prodotti caricati con "carica altri".
- **Elenco dei cookie sotto la Cookie Policy** (scheda Policy, attivo di default, dalla 1.4.5): la Cookie Policy di GetTerms descrive solo le categorie, mentre l'elenco dei singoli cookie sta nel pannello preferenze del banner. Il plugin legge gli stessi dati che usa il banner (cookie e altro archivio del sito classificati in *Cookie Management*, con le intestazioni nella lingua del banner) e li mostra in una tabella sotto lo shortcode della Cookie Policy, divisi per categoria con nome, descrizione, piattaforma e durata, più il pulsante «Gestisci le preferenze sui cookie» che riapre il pannello del banner. Si aggiorna da solo a ogni scansione. Per metterlo altrove: `[mb_getterms_cookie_list]`. Le descrizioni dei cookie arrivano da GetTerms in inglese.
- **Resta attivo dopo gli aggiornamenti**: se WordPress lo disattiva durante l'aggiornamento, il plugin si riattiva da solo (dalla 1.1.0 in poi; l'aggiornamento *alla* 1.1.0 parte ancora dal codice 1.0.1).

Finché non spunti **Attiva sul sito**, il plugin non stampa nulla: puoi configurarlo in anticipo senza toccare il sito.

## Installazione su un sito

1. Scarica `mb-getterms.zip` dall'ultima [release](../../releases/latest), poi in WordPress vai su Plugin → Aggiungi nuovo → Carica.
2. In Impostazioni → GetTerms (mB), incolla il token GetTerms e premi **Collega / Sincronizza**.
3. Configura la lingua e, se serve, l'ID GTM. **Non attivare ancora.**
4. Prepara il container GTM (vedi sotto) **senza pubblicarlo**.
5. Se GTM era inserito da un altro plugin (Complianz, GTM4WP, Site Kit, WPCode...) o dal tema, toglilo da lì.
6. Disattiva il vecchio CMP, poi spunta **Attiva sul sito** e salva.
7. Pubblica il container GTM.
8. Svuota le cache (plugin, hosting, CDN) e premi **Verifica homepage**.
9. Controlla il Consent Mode con GTM Preview / Tag Assistant: senza scelta tutto *denied* tranne security storage, dopo l'accettazione tutto *granted*.
10. Sostituisci le pagine Cookie Policy e Privacy con gli shortcode delle policy GetTerms (sotto la Cookie Policy compaiono da soli l'elenco dei cookie e il pulsante preferenze).

## Configurazione GTM

- **Admin → Impostazioni container:** attiva *Consent Overview*.
- **Tag "GetTerms CMP"** (Community Template Gallery):
  - trigger *Consent Initialization – All Pages*;
  - **non** spuntare l'installazione del banner via GTM, perché lo installa già il plugin;
  - stato di consenso di default: lasciare quello predefinito (solo security storage *granted*).
- **Google tag / GA4 / Google Ads:**
  - *No additional consent required* (advanced consent mode);
  - trigger *Initialization – All Pages* più evento personalizzato `consentUpdate`;
  - *Tag firing options*: Once per page.
- **Tag non-Google** (Clarity, Meta Pixel, LinkedIn, Hotjar...): *Require additional consent for tag to fire*, con le stesse modalità di attivazione.

## Template GTM

Nella cartella [`gtm-templates`](gtm-templates) ci sono due container pronti da importare in GTM (Amministrazione → Importa container → *Unisci* → *Rinomina in conflitto*):

- `mB-GTM-Template-SitoWeb.json`: GetTerms Consent Mode, GA4, Google Ads, Meta, Gravity Forms (4 slot), click su telefono, email e WhatsApp;
- `mB-GTM-Template-Ecommerce.json`: come sopra, più l'e-commerce WooCommerce via GTM4WP (eventi GA4, conversione Ads Acquisto con conversioni avanzate, eventi Meta).

Si compilano solo le costanti della cartella **01 ⚙️ Configurazione**; `-` = spento. Dettagli in [`gtm-templates/LEGGIMI.md`](gtm-templates/LEGGIMI.md). Si scaricano da Impostazioni → GetTerms (mB) → scheda Google Tag Manager: il plugin prende sempre l'ultima versione da GitHub (ramo `main`), anche se il plugin non è aggiornato; senza connessione usa la copia inclusa.

## Rilasciare una nuova versione

1. Aggiorna la versione in `mb-getterms.php`, in **due** punti: l'header `Version:` e la costante `MB_GT_VERSION`.
2. Esegui commit e push.
3. Pubblica la release **dal sito GitHub**: Releases → *Draft a new release* → tag `v1.0.1` (nuovo) → *Publish release*.
   Non serve creare il tag a mano: la release lo crea da sola. La GitHub Action parte una sola volta alla pubblicazione.
   Se lo zip non compare, vai su Actions → Release → *Run workflow*, inserisci il tag e rilancia.
4. La GitHub Action controlla che le versioni coincidano, crea `mb-getterms.zip` e pubblica la release.
5. Entro 6 ore, oppure con *Bacheca → Aggiornamenti → Controlla di nuovo*, i siti mostrano l'aggiornamento.

Il repository deve restare **pubblico**. Se diventasse privato, servirebbe un token in `wp-config.php` (`MB_GT_GITHUB_TOKEN`) e il download degli asset andrebbe adattato.

## Note

- Gli script inseriti a mano nel `header.php` del tema **prima** di `wp_head()` restano sopra il banner: vanno spostati o eliminati.
- Content Security Policy rigida con nonce: il plugin non aggiunge nonce, quindi va usato l'embed manuale.
- Filtri disponibili:
  - `mb_gt_should_output` (bool) disattiva l'output su richieste specifiche;
  - `mb_gt_lang` (string) forza la lingua.
