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
- **Angoli dei pulsanti del banner**: raggio in px per i pulsanti Accetta / Rifiuta / Preferenze / Salva, sia nel banner sia nella finestra preferenze (vuoto = come impostato in GetTerms).
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
10. Sostituisci le pagine Cookie Policy e Privacy con gli shortcode delle policy GetTerms.

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
