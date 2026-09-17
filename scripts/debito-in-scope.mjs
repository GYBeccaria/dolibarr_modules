#!/usr/bin/env node
// debito-in-scope.mjs — LA GUARDIA ANTIPIGRIZIA.
//
// IL DIFETTO, misurato su di me il 2026-07-28, quattro volte in una giornata. La forma è sempre la
// stessa: chiudo un lavoro, dichiaro un debito nel commit, e il debito riguarda un file che avevo
// APERTO IN QUEL MOMENTO. Non è una svista — è una scelta economica presa da sola: dichiarare costa
// una riga, chiudere costa un'ora percepita. E la stima è quasi sempre sbagliata: l'ultimo caso —
// «resta controprova.sh solo in italiano» — è costato 100 righe su un file che stavo già editando.
//
// PERCHÉ NON BASTA UNA REGOLA. «Non dichiarare debito se puoi chiuderlo» è un'istruzione che chiede
// un giudizio nel momento in cui il giudizio è più compromesso: chi ha appena finito è la persona
// meno adatta a stimare quanto costi ancora un pezzo. E l'effetto orso bianco dice che ricordarsela
// non basta. Quindi non si chiede: si MISURA una condizione osservabile.
//
// LA CONDIZIONE, e perché è quella giusta:
//
//   debito dichiarato ∩ file toccati dal commit ≠ ∅
//
// Se il file era nel diff, l'avevi in mano. Il costo marginale di chiudere era il più basso che
// potesse mai essere — e sarà più alto per chiunque altro, in qualsiasi momento futuro, perché
// dovrà ricostruire il contesto che tu avevi già. È l'EVITABILITÀ di ABLLM applicata a sé stessa.
//
// COSA QUESTA GUARDIA NON FA, e va detto forte: **non punisce il debito dichiarato**. Dichiararlo è
// onesto ed è richiesto. Punirlo insegnerebbe a NON dichiararlo — la stessa inversione per cui
// `measure green:false` non è addebitata: chi punisce la misura ottiene meno misure, non meno
// difetti. Qui scatta solo l'intersezione con lo scope: debito su un file che NON hai toccato passa
// senza una parola, perché lì la stima «costa di più di quanto renda» può essere vera.
//
// USCITA: 0 se pulito o se il debito è fuori scope · 1 se dichiarato in-scope (il hook decide se
// bloccare o avvisare) · 2 se non riesce a leggere (e allora non giudica: un guasto d'arnese non è
// una colpa di nessuno).
//
// Uso: node tools/debito-in-scope.mjs <file-messaggio-commit> [file-toccati...]
//      (senza file-toccati li chiede a `git diff --cached --name-only`)

import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { basename } from 'node:path';

const IN_ITALIANO = String(process.env.CODEMERIS_LOCALE || process.env.LC_ALL || process.env.LANG || 'it').slice(0, 2) !== 'en';
const msg = (it, en) => (IN_ITALIANO ? it : en);

/** La riga «Debito tracciato:» del blocco ANTI-DRIFT, con le sue eventuali continuazioni. */
export function debitoDichiarato(testo) {
  const righe = testo.split('\n');
  const i = righe.findIndex((r) => /^-\s*Debito tracciato/i.test(r));
  if (i < 0) return null;
  const out = [righe[i]];
  // Le continuazioni sono righe indentate che non aprono un'altra voce dell'elenco.
  for (let j = i + 1; j < righe.length && /^\s+\S/.test(righe[j]) && !/^-\s/.test(righe[j].trim()); j++) out.push(righe[j]);
  const s = out.join(' ').replace(/^-\s*Debito tracciato[^:]*:\s*/i, '').trim();
  return /^(nessuno|nessun|none|no)\b/i.test(s) ? null : s;
}

/**
 * I file NOMINATI nel testo del debito. Si cercano percorsi con un'estensione o una barra: un
 * riferimento generico («la duplicazione it/en») non è un file e non deve far scattare nulla —
 * la guardia serve a prendere l'omissione precisa, non a discutere la prosa.
 */
export function fileNominati(testo) {
  if (!testo) return [];
  const out = new Set();
  for (const m2 of testo.matchAll(/[\w./-]*[\w-]+\.(?:mjs|js|ts|json|sh|md|py|yml|yaml|php)\b/g)) out.add(m2[0]);
  for (const m2 of testo.matchAll(/\b(?:src|tools|test|docs|lib|bin|selfhost)\/[\w./-]+/g)) out.add(m2[0]);
  return [...out];
}

/** L'intersezione: un nome del debito che corrisponde a un file del diff. */
export function inScope(nomi, toccati) {
  const hit = [];
  for (const n of nomi) {
    const base = basename(n);
    const t = toccati.find((f) => f === n || f.endsWith(`/${n}`) || basename(f) === base);
    if (t) hit.push({ nominato: n, toccato: t });
  }
  return hit;
}

if (import.meta.url === `file://${process.argv[1]}`) {
  const [msgFile, ...espliciti] = process.argv.slice(2);
  let testo; let toccati = espliciti;
  try {
    testo = readFileSync(msgFile, 'utf8');
    if (!toccati.length) {
      toccati = execFileSync('git', ['diff', '--cached', '--name-only'], { encoding: 'utf8' })
        .split('\n').map((x) => x.trim()).filter(Boolean);
    }
  } catch {
    // Non giudica ciò che non ha potuto leggere: un guasto d'arnese, per costruzione, non è di nessuno.
    process.exit(2);
  }
  const debito = debitoDichiarato(testo);
  if (!debito) process.exit(0);
  const hit = inScope(fileNominati(debito), toccati);
  if (!hit.length) process.exit(0);

  process.stderr.write(`\n\x1b[33m⚠ ${msg('DEBITO DICHIARATO SU UN FILE CHE STAI GIÀ TOCCANDO', 'DEBT DECLARED ON A FILE YOU ARE ALREADY TOUCHING')}\x1b[0m\n`);
  for (const h of hit) {
    process.stderr.write(`   · ${h.nominato} → ${msg('nel diff di questo commit', 'in this commit\'s diff')}: \x1b[1m${h.toccato}\x1b[0m\n`);
  }
  process.stderr.write(`\n   ${msg(
    'Il costo marginale di chiuderlo è il più basso che possa mai essere: il file è in mano tua ADESSO.',
    'The marginal cost of closing it is the lowest it will ever be: the file is in your hands NOW.')}\n`);
  process.stderr.write(`   ${msg(
    'Per chiunque altro, in qualsiasi momento futuro, costerà di più — dovrà ricostruire il contesto che hai già.',
    'For anyone else, at any future moment, it will cost more — they must rebuild the context you already hold.')}\n`);
  process.stderr.write(`   ${msg(
    'Chiudilo, oppure spiega nel commit perché NON era chiudibile qui (una ragione, non una stima).',
    'Close it, or explain in the commit why it was NOT closeable here (a reason, not an estimate).')}\n\n`);
  process.stderr.write(`   \x1b[2m${msg(
    'Il debito su file che NON hai toccato non fa scattare nulla: lì «costa più di quanto renda» può essere vero.',
    'Debt on files you did NOT touch triggers nothing: there, «it costs more than it yields» may be true.')}\x1b[0m\n`);
  process.exit(1);
}
