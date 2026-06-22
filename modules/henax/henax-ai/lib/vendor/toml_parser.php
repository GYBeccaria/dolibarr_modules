<?php
/* Copyright (C) 2026 Henaxis srl — GPL v3+
 *
 * TOML 1.0 sub-set parser for PHP — single file, no dependencies, vendored.
 *
 * Supported features (sufficient for architect.toml manifest use case):
 *   - Comments (# until EOL)
 *   - Bare keys (a-zA-Z0-9_-)
 *   - Quoted strings ("..." with basic escapes \n \t \" \\ \uXXXX)
 *   - Literal strings ('...')
 *   - Multi-line strings ("""...""", '''...''')
 *   - Integers, floats, booleans (true/false), datetime (passed as string)
 *   - Inline arrays [1, 2, "a"] including nested
 *   - Inline tables { key = "value", k2 = 42 }
 *   - Standard tables [section]  → top-level key
 *   - Sub-tables [a.b.c]
 *   - Array of tables [[a]]
 *
 * NOT supported (advanced TOML 1.0):
 *   - Mixed array types (technically allowed in 1.0 but rare)
 *   - Datetime parsing (returned as-is string, not DateTime objects)
 *   - Some edge cases of dotted keys inside inline tables
 *
 * Public function:
 *   henaxai_parse_toml(string $toml) : array
 *
 * Throws Exception on unrecoverable syntax errors.
 */

if (!function_exists('henaxai_parse_toml')) {

function henaxai_parse_toml($toml) {
    if (!is_string($toml)) throw new InvalidArgumentException("TOML input must be string");
    // Normalize line endings
    $toml = str_replace(array("\r\n", "\r"), "\n", $toml);
    $lines = explode("\n", $toml);

    $result = array();
    $current_table = &$result; // reference to current table being filled
    $current_path = array();   // for [a.b.c] tracking
    $arrays_of_tables_paths = array(); // for [[x]] disambiguation

    $i = 0;
    $n = count($lines);
    while ($i < $n) {
        $line = trim($lines[$i]);
        $i++;

        // Skip empty + comments
        if ($line === '' || $line[0] === '#') continue;

        // Array of tables: [[a.b]]
        if (preg_match('/^\[\[\s*([^\]]+)\s*\]\]$/', $line, $m)) {
            $path_str = trim($m[1]);
            $path = _toml_parse_key_path($path_str);
            $current_path = $path;
            $arrays_of_tables_paths[$path_str] = true;

            // Walk to parent, append new entry to array
            $ref = &$result;
            for ($j = 0; $j < count($path) - 1; $j++) {
                $key = $path[$j];
                if (!isset($ref[$key]) || !is_array($ref[$key])) $ref[$key] = array();
                $ref = &$ref[$key];
            }
            $last_key = $path[count($path) - 1];
            if (!isset($ref[$last_key]) || !is_array($ref[$last_key])) $ref[$last_key] = array();
            $ref[$last_key][] = array();
            $current_table = &$ref[$last_key][count($ref[$last_key]) - 1];
            unset($ref);
            continue;
        }

        // Standard table: [a.b.c]
        if (preg_match('/^\[\s*([^\]]+)\s*\]$/', $line, $m)) {
            $path_str = trim($m[1]);
            $path = _toml_parse_key_path($path_str);
            $current_path = $path;

            $ref = &$result;
            foreach ($path as $key) {
                if (!isset($ref[$key]) || !is_array($ref[$key])) $ref[$key] = array();
                $ref = &$ref[$key];
            }
            $current_table = &$ref;
            unset($ref);
            continue;
        }

        // Key = value
        if (strpos($line, '=') === false) {
            throw new RuntimeException("TOML parse error at line ".($i)." — expected key=value: ".substr($line, 0, 80));
        }

        $eq_pos = _toml_find_kv_separator($line);
        if ($eq_pos === false) throw new RuntimeException("TOML parse error at line ".($i)." — bad key=value");
        $key_raw = trim(substr($line, 0, $eq_pos));
        $val_raw = trim(substr($line, $eq_pos + 1));

        $key_path = _toml_parse_key_path($key_raw);

        // Value can be multi-line array [...] o inline table {...} — collect remaining lines
        // Conta brackets/braces aperte vs chiuse (skip dentro string)
        if ($val_raw !== '') {
            $first = $val_raw[0];
            if ($first === '[' || $first === '{') {
                $open_char  = $first;
                $close_char = $first === '[' ? ']' : '}';
                $balance = _toml_bracket_balance($val_raw, $open_char, $close_char);
                while ($balance > 0 && $i < $n) {
                    $next = $lines[$i];
                    $i++;
                    $val_raw .= "\n".$next;
                    $balance = _toml_bracket_balance($val_raw, $open_char, $close_char);
                }
            }
        }

        // Value can be multi-line ("""...""" or '''...''') — collect remaining lines
        if (substr($val_raw, 0, 3) === '"""' || substr($val_raw, 0, 3) === "'''") {
            $delim = substr($val_raw, 0, 3);
            $accum = substr($val_raw, 3);
            // Closing on same line?
            $close_pos = strpos($accum, $delim);
            if ($close_pos !== false) {
                $val_raw = $delim.substr($accum, 0, $close_pos).$delim;
            } else {
                // Collect until closing delim
                $accum_full = $accum;
                $found = false;
                while ($i < $n) {
                    $next = $lines[$i];
                    $i++;
                    $cp = strpos($next, $delim);
                    if ($cp !== false) {
                        $accum_full .= "\n".substr($next, 0, $cp);
                        $found = true;
                        break;
                    }
                    $accum_full .= "\n".$next;
                }
                if (!$found) throw new RuntimeException("TOML parse error: unterminated multi-line string");
                $val_raw = $delim.$accum_full.$delim;
            }
        }

        $value = _toml_parse_value($val_raw);

        // Set value at key_path within current_table
        $ref = &$current_table;
        for ($j = 0; $j < count($key_path) - 1; $j++) {
            $k = $key_path[$j];
            if (!isset($ref[$k]) || !is_array($ref[$k])) $ref[$k] = array();
            $ref = &$ref[$k];
        }
        $ref[$key_path[count($key_path) - 1]] = $value;
        unset($ref);
    }

    return $result;
}

/**
 * Parse a key path: "a.b.c" or "a.\"quoted key\".c" → ['a', 'b', 'c']
 */
function _toml_parse_key_path($keystr) {
    $parts = array();
    $buf = '';
    $in_quote = false;
    $quote_char = null;
    $len = strlen($keystr);
    for ($i = 0; $i < $len; $i++) {
        $c = $keystr[$i];
        if ($in_quote) {
            if ($c === $quote_char) { $in_quote = false; }
            else $buf .= $c;
            continue;
        }
        if ($c === '"' || $c === "'") { $in_quote = true; $quote_char = $c; continue; }
        if ($c === '.') { $parts[] = trim($buf); $buf = ''; continue; }
        $buf .= $c;
    }
    if ($buf !== '') $parts[] = trim($buf);
    return $parts;
}

/**
 * Conta apertura - chiusura di un certo char tra opening e closing, skipping dentro string literals.
 * Ritorna delta (>0 = ancora aperti, 0 = chiuso, <0 = errore).
 */
function _toml_bracket_balance($s, $open_char, $close_char) {
    $len = strlen($s);
    $balance = 0;
    $in_quote = false;
    $quote_char = null;
    for ($i = 0; $i < $len; $i++) {
        $c = $s[$i];
        if ($in_quote) {
            if ($c === '\\' && $i + 1 < $len) { $i++; continue; }
            if ($c === $quote_char) $in_quote = false;
            continue;
        }
        if ($c === '"' || $c === "'") { $in_quote = true; $quote_char = $c; continue; }
        if ($c === '#') break; // comment
        if ($c === $open_char)  $balance++;
        if ($c === $close_char) $balance--;
    }
    return $balance;
}

/**
 * Find position of the first '=' that is NOT inside a string literal.
 */
function _toml_find_kv_separator($line) {
    $len = strlen($line);
    $in_quote = false;
    $quote_char = null;
    for ($i = 0; $i < $len; $i++) {
        $c = $line[$i];
        if ($in_quote) {
            if ($c === '\\') { $i++; continue; }
            if ($c === $quote_char) { $in_quote = false; }
            continue;
        }
        if ($c === '"' || $c === "'") { $in_quote = true; $quote_char = $c; continue; }
        if ($c === '=') return $i;
        if ($c === '#') return false; // comment before any =
    }
    return false;
}

/**
 * Parse a TOML scalar/array/inline-table value.
 */
function _toml_parse_value($v) {
    $v = trim($v);
    if ($v === '') return '';

    // Strip trailing comment (only if not inside string)
    $v_stripped = _toml_strip_trailing_comment($v);
    $v = trim($v_stripped);

    // Multi-line basic string
    if (substr($v, 0, 3) === '"""' && substr($v, -3) === '"""' && strlen($v) >= 6) {
        $body = substr($v, 3, -3);
        // Trim leading newline (TOML spec)
        if (substr($body, 0, 1) === "\n") $body = substr($body, 1);
        return _toml_unescape_basic($body);
    }
    // Multi-line literal string
    if (substr($v, 0, 3) === "'''" && substr($v, -3) === "'''" && strlen($v) >= 6) {
        $body = substr($v, 3, -3);
        if (substr($body, 0, 1) === "\n") $body = substr($body, 1);
        return $body;
    }
    // Basic string
    if ($v[0] === '"' && substr($v, -1) === '"') {
        return _toml_unescape_basic(substr($v, 1, -1));
    }
    // Literal string
    if ($v[0] === "'" && substr($v, -1) === "'") {
        return substr($v, 1, -1);
    }
    // Booleans
    if ($v === 'true') return true;
    if ($v === 'false') return false;
    // Inline array
    if ($v[0] === '[' && substr($v, -1) === ']') {
        return _toml_parse_inline_array($v);
    }
    // Inline table
    if ($v[0] === '{' && substr($v, -1) === '}') {
        return _toml_parse_inline_table($v);
    }
    // Integer (incluso underscore)
    if (preg_match('/^[+-]?[0-9_]+$/', $v)) {
        return (int)str_replace('_', '', $v);
    }
    // Float
    if (preg_match('/^[+-]?[0-9_]*\.?[0-9_]+([eE][+-]?[0-9]+)?$/', $v)) {
        return (float)str_replace('_', '', $v);
    }
    // Datetime / date / time — passa come stringa
    if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}/', $v)) {
        return $v;
    }
    // Bare value fallback
    return $v;
}

function _toml_strip_trailing_comment($v) {
    $len = strlen($v);
    $in_quote = false;
    $quote_char = null;
    $depth = 0; // for [...] / {...}
    for ($i = 0; $i < $len; $i++) {
        $c = $v[$i];
        if ($in_quote) {
            if ($c === '\\') { $i++; continue; }
            if ($c === $quote_char) $in_quote = false;
            continue;
        }
        if ($c === '"' || $c === "'") { $in_quote = true; $quote_char = $c; continue; }
        if ($c === '[' || $c === '{') $depth++;
        if ($c === ']' || $c === '}') $depth--;
        if ($c === '#' && $depth === 0) return rtrim(substr($v, 0, $i));
    }
    return $v;
}

function _toml_unescape_basic($s) {
    // Basic escapes
    $r = '';
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $c = $s[$i];
        if ($c === '\\' && $i + 1 < $len) {
            $next = $s[$i + 1];
            switch ($next) {
                case 'n': $r .= "\n"; $i++; break;
                case 't': $r .= "\t"; $i++; break;
                case 'r': $r .= "\r"; $i++; break;
                case '"': $r .= '"';  $i++; break;
                case '\\': $r .= '\\'; $i++; break;
                case '/': $r .= '/';  $i++; break;
                case 'b': $r .= "\x08"; $i++; break;
                case 'f': $r .= "\x0C"; $i++; break;
                case 'u':
                    if ($i + 5 < $len) {
                        $hex = substr($s, $i + 2, 4);
                        if (preg_match('/^[0-9a-fA-F]{4}$/', $hex)) {
                            $cp = hexdec($hex);
                            $r .= mb_chr($cp, 'UTF-8');
                            $i += 5;
                            break;
                        }
                    }
                    $r .= $c;
                    break;
                default:
                    $r .= $c;
            }
        } else {
            $r .= $c;
        }
    }
    return $r;
}

function _toml_parse_inline_array($v) {
    // Strip newlines and inline comments inside array body
    $body = trim(substr($v, 1, -1));
    if ($body === '') return array();
    // Rimuovi commenti #... fino a newline (TOML supporta commenti dentro array multi-line)
    $body = preg_replace('/(^|\n)\s*#[^\n]*/', '', $body);
    $items = _toml_split_top_level($body, ',');
    $out = array();
    foreach ($items as $item) {
        $item = trim($item);
        if ($item === '') continue; // gestisce trailing comma
        $out[] = _toml_parse_value($item);
    }
    return $out;
}

function _toml_parse_inline_table($v) {
    $body = trim(substr($v, 1, -1));
    if ($body === '') return array();
    $items = _toml_split_top_level($body, ',');
    $out = array();
    foreach ($items as $item) {
        $item = trim($item);
        if ($item === '') continue;
        $eq = _toml_find_kv_separator($item);
        if ($eq === false) continue;
        $k = trim(substr($item, 0, $eq));
        $val = _toml_parse_value(trim(substr($item, $eq + 1)));
        $path = _toml_parse_key_path($k);
        $ref = &$out;
        for ($j = 0; $j < count($path) - 1; $j++) {
            if (!isset($ref[$path[$j]]) || !is_array($ref[$path[$j]])) $ref[$path[$j]] = array();
            $ref = &$ref[$path[$j]];
        }
        $ref[$path[count($path) - 1]] = $val;
        unset($ref);
    }
    return $out;
}

/**
 * Split string by separator, respecting nested brackets and string literals.
 */
function _toml_split_top_level($s, $sep) {
    $parts = array();
    $buf = '';
    $depth = 0;
    $in_quote = false;
    $quote_char = null;
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $c = $s[$i];
        if ($in_quote) {
            if ($c === '\\' && $i + 1 < $len) { $buf .= $c.$s[$i+1]; $i++; continue; }
            if ($c === $quote_char) $in_quote = false;
            $buf .= $c;
            continue;
        }
        if ($c === '"' || $c === "'") { $in_quote = true; $quote_char = $c; $buf .= $c; continue; }
        if ($c === '[' || $c === '{') $depth++;
        if ($c === ']' || $c === '}') $depth--;
        if ($c === $sep && $depth === 0) { $parts[] = $buf; $buf = ''; continue; }
        $buf .= $c;
    }
    if ($buf !== '') $parts[] = $buf;
    return $parts;
}

} // end if (!function_exists)
