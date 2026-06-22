# dolibarr_modules — registro di razionalizzazione moduli Henax / Domicare

Questo repo **non** è più uno stash di moduli: è il **registro di governo** della piattaforma.

## Documenti
- **[REGISTRY.md](REGISTRY.md)** — fonte unica di verità: ogni modulo, stato attuale → target, ID, dipendenze, conflitti.
- **[NAMING.md](NAMING.md)** — convenzione di naming e namespace (`henax-*`, `domicare-*`, `henaxis-*`, `vendor/*`).
- **[INTEROP.md](INTEROP.md)** — contratto di interoperabilità: livelli L0/L1/L2, hook+manifest, confini dati.

## Struttura
```
vendor/            moduli di terzi (arubasdi, efattita, tawkto, dolibarrassistant)
modules/henax/     moduli orizzontali riusabili (popolamento progressivo)
modules/domicare/  verticale Domicare
<top-level>        moduli nostri ancora da ricollocare (b2border, b2cstore, helpchat, diagnosi_digitale, industria40)
```

## Stato
Bootstrap iniziale: classificazione + 3 documenti di governo. La migrazione effettiva dei moduli avviene per passi, guidata da REGISTRY.md (vedi §"azioni immediate").
