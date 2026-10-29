<?php
/**
 * Notes as GitHub-flavoured Markdown plus a small allow-list of HTML.
 * Everything shown is rebuilt from tokens, so notes cannot inject tags or attributes.
 *
 * Per-item switch: an item with notes_format = 'markdown' uses this renderer; others keep the
 * older inline-HTML renderer until the migration is approved.
 */

/** What an item with no notes_format uses. Stays false until Carl approves migrating old notes. */
function project_notes_markdown_default(): bool {
    return false;
}

function project_notes_use_markdown(array $item): bool {
    $format = (string)($item['notes_format'] ?? '');
    if ($format === 'markdown') return true;
    if ($format === 'html') return false;
    return project_notes_markdown_default();
}

function project_md_inline(string $line): string {
    $line = preg_replace('/[\x00\x01]/', '', $line) ?? $line;
    $tokens = [];
    $keep = function (string $html) use (&$tokens): string {
        $tokens[] = $html;
        return "\x00" . (count($tokens) - 1) . "\x01";
    };
    // Backslash escapes first, so \* and \_ and \--- stay literal.
    $line = preg_replace_callback('/\\\\([\\\\`*_~#\-.+!\[\](){}>|])/', fn($m) => $keep(h($m[1])), $line) ?? $line;
    // Code spans: contents are plain text.
    $line = preg_replace_callback('/`([^`]+)`/', fn($m) => $keep('<code>' . h(project_decode_note_text_entities($m[1])) . '</code>'), $line) ?? $line;
    // Images, then allowed inline tags with per-line balancing.
    $parts = preg_split('/(<\s*img\b[^>]*>|<\s*\/?\s*(?:sup|sub|strong|b|em|i|u|s|del)\b[^>]*>)/i', $line, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
    $open = [];
    $out = '';
    foreach ($parts as $part) {
        if (preg_match('/^<\s*img\b/i', $part)) {
            $out .= $keep(project_note_image_from_tag($part));
        } elseif (preg_match('/^<\s*(\/?)\s*(sup|sub|strong|b|em|i|u|s|del)\b[^>]*>$/i', $part, $m)) {
            $tag = strtolower($m[2]);
            $tag = match ($tag) { 'b' => 'strong', 'i' => 'em', default => $tag };
            if ($m[1] === '') {
                $open[] = $tag;
                $out .= $keep("<$tag>");
            } elseif (in_array($tag, $open, true)) {
                while ($open !== []) {
                    $top = array_pop($open);
                    $out .= $keep("</$top>");
                    if ($top === $tag) break;
                }
            }
        } else {
            $out .= h(project_decode_note_text_entities($part));
        }
    }
    while ($open !== []) {
        $out .= $keep('</' . array_pop($open) . '>');
    }
    // Markdown emphasis over the escaped text (tags are already tokens).
    $out = preg_replace('/\*\*\*(?=\S)(.+?)(?<=\S)\*\*\*/', '<strong><em>$1</em></strong>', $out) ?? $out;
    $out = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/', '<strong>$1</strong>', $out) ?? $out;
    $out = preg_replace('/(?<![*\w])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![*\w])/', '<em>$1</em>', $out) ?? $out;
    $out = preg_replace('/(?<!\w)_(?=[^\s_])(.+?)(?<=[^\s_])_(?!\w)/', '<em>$1</em>', $out) ?? $out;
    $out = preg_replace('/~~(?=\S)(.+?)(?<=\S)~~/', '<del>$1</del>', $out) ?? $out;
    return preg_replace_callback('/\x00(\d+)\x01/', fn($m) => $tokens[(int)$m[1]] ?? '', $out) ?? $out;
}

/** Summary markup: one line stays inline; several lines become one block per line. */
function project_render_fold_summary(string $summary): string {
    $lines = explode("\n", trim(str_replace(["\r\n", "\r"], "\n", $summary)));
    if (count($lines) === 1) return trim($lines[0]) === '' ? 'Details' : project_md_inline(trim($lines[0]));
    $out = '';
    foreach ($lines as $line) {
        $line = trim($line);
        $out .= $line === '' ? '<div><br></div>' : '<div>' . project_md_inline($line) . '</div>';
    }
    return $out;
}

function project_render_markdown_notes(string $text): string {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace('/<br\s*\/?>[ \t]*\n?/i', "\n", $text) ?? $text;
    $lines = explode("\n", trim($text));
    if ($lines === ['']) return '';
    $html = '';
    $depth = 0;
    $lastText = false;
    $pendingBlank = false;
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        $line = rtrim($lines[$i]);
        if (trim($line) === '') {
            $pendingBlank = true;
            continue;
        }
        $trim = trim($line);
        // Closing fold, possibly after some text on the same line.
        if ($depth > 0 && preg_match('/^(.*?)\s*<\s*\/\s*details\s*>$/i', $trim, $m)) {
            if ($m[1] !== '') {
                array_splice($lines, $i + 1, 0, ['</details>']);
                $n++;
                $trim = $m[1];
            } else {
                $html .= '</details>';
                $depth--;
                $lastText = false;
                $pendingBlank = false;
                continue;
            }
        }
        // Opening fold, with an optional summary and trailing text on the same line.
        if (preg_match('/^<\s*details\b[^>]*>\s*(?:<\s*summary\b[^>]*>(.*?)<\s*\/\s*summary\s*>)?(.*)$/i', $trim, $m)) {
            $summary = $m[1] ?? '';
            $rest = trim($m[2] ?? '');
            if ($summary === '' && $rest === '') {
                $j = $i + 1;
                while ($j < $n && trim($lines[$j]) === '') $j++;
                if ($j < $n && preg_match('/^<\s*summary\b[^>]*>(.*)$/i', trim($lines[$j]), $sm)) {
                    if (preg_match('/^(.*?)<\s*\/\s*summary\s*>(.*)$/i', $sm[1], $one)) {
                        $summary = $one[1];
                        $rest = trim($one[2]);
                        $i = $j;
                    } else {
                        // Multi-line summary: runs until the line holding </summary>.
                        $buf = [$sm[1]];
                        for ($k = $j + 1; $k < $n; $k++) {
                            if (preg_match('/^(.*?)<\s*\/\s*summary\s*>(.*)$/i', trim($lines[$k]), $end)) {
                                $buf[] = $end[1];
                                $summary = implode("\n", $buf);
                                $rest = trim($end[2]);
                                $i = $k;
                                break;
                            }
                            $buf[] = $lines[$k];
                        }
                    }
                }
            }
            $html .= '<details><summary>' . project_render_fold_summary($summary) . '</summary>';
            $depth++;
            $lastText = false;
            $pendingBlank = false;
            if ($rest !== '') {
                array_splice($lines, $i + 1, 0, [$rest]);
                $n++;
            }
            continue;
        }
        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trim)) {
            $html .= '<hr>';
            $lastText = false;
            $pendingBlank = false;
            continue;
        }
        if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/', $trim, $m)) {
            $level = strlen($m[1]);
            $html .= "<h$level>" . project_md_inline($m[2]) . "</h$level>";
            $lastText = false;
            $pendingBlank = false;
            continue;
        }
        if (preg_match('/^([-*+]|\d+[.)])\s+/', $trim, $m)) {
            $ordered = ctype_digit($m[1][0]);
            $items = [];
            $start = $ordered ? (int)$m[1] : 1;
            for (; $i < $n; $i++) {
                $t = trim($lines[$i]);
                if ($ordered ? !preg_match('/^\d+[.)]\s+(.*)$/', $t, $im) : !preg_match('/^[-*+]\s+(.*)$/', $t, $im)) break;
                $items[] = '<li>' . project_md_inline($im[1]) . '</li>';
            }
            $i--;
            $tag = $ordered ? 'ol' : 'ul';
            $html .= '<' . $tag . ($ordered && $start !== 1 ? ' start="' . $start . '"' : '') . '>' . implode('', $items) . "</$tag>";
            $lastText = false;
            $pendingBlank = false;
            continue;
        }
        if ($i + 1 < $n && preg_match('/^(={2,}|-{2,})\s*$/', $lines[$i + 1], $setext)) {
            $level = $setext[1][0] === '=' ? 1 : 2;
            $html .= "<h$level>" . project_md_inline($trim) . "</h$level>";
            $i++;
            $lastText = false;
            $pendingBlank = false;
            continue;
        }
        if ($pendingBlank && $lastText) $html .= '<div><br></div>';
        $html .= '<div>' . project_md_inline($trim) . '</div>';
        $lastText = true;
        $pendingBlank = false;
    }
    return $html . str_repeat('</details>', $depth);
}
