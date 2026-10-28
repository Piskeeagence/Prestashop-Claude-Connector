# Piskee per PrestaShop, documentazione

Piskee collega il Suo negozio PrestaShop a Claude: l'assistente IA del Suo back office. Lei indica cosa vuole fare («riscriva le meta description della categoria Abiti», «crei le taglie dalla S alla XL per questo prodotto», «quali prodotti sono esauriti?») e Claude agisce direttamente nel Suo negozio, con i Suoi permessi e sotto il Suo controllo.

Progettato e sviluppato a Chantilly (Francia) da Piskee, agenzia SEO e GEO specializzata in e-commerce.

## Prima di iniziare

- PrestaShop 1.7.6 o successivo (1.7, 8 e 9), PHP 7.2 o successivo.
- Il negozio in HTTPS, nella radice del dominio, con gli URL semplificati attivati.
- Nient'altro: il modulo include il proprio server MCP e il proprio server di autorizzazione OAuth 2.1.
- Un account Claude (claude.ai, l'app Claude o Claude Code).

## Installazione

1. Moduli, Gestione moduli, Carica un modulo: trascini il file zip del modulo.
2. Apra il modulo Piskee: la scheda Connessione verifica tutto. Clicchi su **Testa la connessione** per un test reale degli indirizzi pubblici.

## Collegare Claude

1. Copi l'indirizzo indicato nella scheda Connessione (termina con `/module/piskeeconnector/mcp`).
2. In Claude: Impostazioni, Connettori, Aggiungi, Aggiungi connettore personalizzato.
3. Lo chiami «Piskee», incolli l'indirizzo, mantenga le impostazioni rilevate (accesso immediato, registrazione automatica), clicchi su Aggiungi e poi su Connetti.
4. Il Suo back office si apre su una schermata di autorizzazione: controlli i permessi richiesti e clicchi su **Autorizza**.

Con Claude Code, un solo comando:

```
claude mcp add --transport http piskee https://il-suo-negozio.it/module/piskeeconnector/mcp
```

Poi digiti `/mcp` in Claude Code per accedere.

## Cosa può fare Claude

**Prodotti e categorie**: cercare, leggere e modificare un prodotto (prezzi, riferimenti, EAN, peso, stato, testi e SEO in tutte le lingue), creare un prodotto (disattivato per impostazione predefinita), gestire le giacenze con un valore esatto o tramite carichi e scarichi, assegnare le categorie, modificare una categoria.

**Combinazioni**: elencare e creare attributi (taglia, colore...) e i relativi valori, creare fino a 100 combinazioni in una volta con impatto sul prezzo, riferimento, EAN e giacenza, modificare una combinazione.

**Immagini**: aggiungere un'immagine da un indirizzo web, scrivere i testi alternativi, scegliere la copertina, ridurre gli originali troppo pesanti, centrarli su uno sfondo quadrato e rigenerare tutte le miniature.

**Arricchimento**: caratteristiche, marchi, prodotti correlati, promozioni (percentuale o importo, con date) e un audit completo del catalogo che elenca ciò che frena le Sue vendite e la Sua visibilità sui motori di ricerca.

**Vendita incrociata e carrello**: prodotti correlati (aggiungere, rimuovere, sostituire, fino a 50 prodotti in una volta), suggerimenti «spesso acquistati insieme» calcolati sui Suoi ordini reali, e il blocco nativo «i clienti hanno acquistato anche» nella scheda prodotto e nel carrello come acquisti aggiuntivi. Gli altri moduli di upsell vengono rilevati ed elencati, mai modificati.

**Ordini e tracciamento**: trovare un ordine (riferimento, cliente, numero di tracciamento), leggerlo con la cronologia e il link di tracciamento del corriere, cambiarne lo stato, inserire un numero di tracciamento.

**Pagine CMS e traduzioni**: leggere e modificare le pagine CMS, modificare fino a 50 prodotti in una volta (prezzi, stati, testi o traduzioni).

**Gestione quotidiana**: riepilogo delle vendite su un periodo, scorte basse, ricerca e scheda dei clienti, lettura dei messaggi del servizio clienti e risposta via e-mail (dopo la Sua approvazione), creazione di buoni sconto.

**Tema figlio**: creare un tema figlio in bozza, leggere e cercare nei file del tema, analizzare i titoli Hn e il breadcrumb di una pagina online, modificare i template, il CSS e il JavaScript della bozza, ripristinare un backup.

Nel menu di Claude sono disponibili anche tre guide pronte all'uso: Riepilogo del giorno, Ottimizzazione del catalogo, Sistemare gli Hn e aggiungere un breadcrumb.

## Cosa Claude non può fare

- Rimborsare un cliente o toccare un pagamento.
- Modificare il tema online o un tema padre.
- Mettere online un tema: è Lei a cliccare, nella scheda Tema figlio.
- Eliminare un prodotto, un cliente o un ordine.
- Ottenere più permessi del dipendente che lo ha collegato.

## Protezioni

- Ogni strumento che modifica il negozio è segnalato come sensibile: Claude Le chiede conferma prima di eseguirlo.
- I permessi sono quelli del profilo PrestaShop del dipendente che ha autorizzato la connessione, verificati a ogni chiamata.
- Ogni modifica al tema viene salvata in un backup. I file PHP e la configurazione del tema non possono essere modificati. I template vengono controllati prima del salvataggio: vengono rifiutati tag Smarty non bilanciati, funzioni PHP fuori da una breve lista di quelle consentite, chiamate a classi, costanti e letture di file.
- Può disattivare il server e scollegare ogni assistente in qualsiasi momento, nella scheda Connessione.
- Nessun dato viene inviato a Piskee.

## Mettere online un tema

1. Chieda a Claude di lavorare su un tema figlio in bozza.
2. Apra Piskee, scheda Tema figlio, e clicchi su **Metti online** accanto alla bozza.
3. Controlli la home page, una categoria e un prodotto. Se qualcosa non va, clicchi su **Torna al tema precedente**.

Cambia solo il tema: i Suoi moduli, le loro impostazioni, le posizioni degli hook, le dimensioni delle immagini e i layout delle pagine restano invariati. Una bozza può essere messa online solo al posto di un tema della stessa famiglia (stesso tema padre).

## Gratis o Pro

La versione gratuita consente 100 modifiche al mese per negozio; lettura, ricerche e audit sono illimitati. Il contatore si azzera il 1° del mese.

Piskee Pro (29 € + IVA al mese, oppure 290 € + IVA all'anno con 2 mesi gratis, prezzo di lancio garantito a vita per i primi 100 clienti, senza vincoli) elimina il limite e aggiunge la cronologia di ogni modifica con annullamento, i redirect 301 e il supporto prioritario. Una licenza Pro copre un negozio: in multinegozio, ogni negozio aggiuntivo costa 10 € + IVA al mese. È disponibile una sessione di avvio con l'agenzia a 150 € + IVA.

Abbonarsi: [mensile](https://buy.stripe.com/28EbJ1bO4121gcQ0kZ9EI00) · [annuale](https://buy.stripe.com/28EcN59FW5ihd0Ed7L9EI02) · [negozio aggiuntivo](https://buy.stripe.com/aFa6oH19qaCBgcQ2t79EI04) · [sessione di avvio](https://buy.stripe.com/3cI14nbO47qp7GkffT9EI03).

Incolli la chiave di licenza ricevuta via e-mail nella scheda Gratis / Pro.

## Risoluzione dei problemi

- **«Questa richiesta di connessione non è valida»** durante l'autorizzazione: rimuova il connettore in Claude, poi lo aggiunga di nuovo.
- **Errore 401 permanente**: alcuni hosting rimuovono l'intestazione Authorization. Aggiunga `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` all'inizio del file `.htaccess`.
- **Claude non vede i nuovi strumenti**: apra una nuova conversazione.
- **Errore all'apertura della schermata di autorizzazione**: verifichi che il negozio non sia in modalità manutenzione e che nessun firewall blocchi gli indirizzi `/.well-known/`.
- **Una modifica al tema non si vede**: è normale finché la bozza non è online.

## Ha bisogno di aiuto?

Il team Piskee risponde alle domande sul modulo e affianca i negozi che vogliono andare oltre: audit SEO e GEO, contenuti, Google Ads, sviluppi su misura.

- Contatti: [piskee.com/contact](https://www.piskee.com/contact/)
- E-mail: louisalexandre@piskee.com
- WhatsApp: +33 7 69 15 39 92
- Audit SEO e GEO gratuito in 48 h: [piskee.com](https://www.piskee.com/)

## Note legali

Piskee per PrestaShop è un modulo indipendente pubblicato da Piskee (Chantilly, Francia). Non è un prodotto ufficiale di PrestaShop SA né di Anthropic, PBC, che non lo hanno sviluppato, verificato né approvato, e che non se ne assumono alcuna responsabilità. PrestaShop è un marchio di PrestaShop SA; Claude è un marchio di Anthropic, PBC; questi nomi sono citati solo per indicare la compatibilità. Il modulo è fornito «così com'è», senza garanzia, nei limiti previsti dalla legge e dalla licenza AFL-3.0. Le azioni decise e approvate dall'esercente restano sotto la sua responsabilità: esegua backup regolari e controlli le modifiche prima di accettarle.
