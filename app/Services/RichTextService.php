<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Membersihkan HTML dari editor Quill 2 untuk ditampilkan (halaman ringkasan & PDF).
 *
 * Quill 2 menyimpan SEMUA daftar sebagai <ol><li data-list="ordered|bullet"> dan angka/poinnya
 * digambar oleh CSS Quill. Di luar editor angka itu hilang (Tailwind preflight juga menghapus
 * list-style), dan bullet pun tampil sebagai <ol>. Kelas ini mengubahnya menjadi <ol>/<ul> asli
 * dan membuang atribut/tag lain (aman dipakai dengan {!! !!}).
 */
class RichTextService
{
    private const INLINE_KEEP = ['b' => 'strong', 'strong' => 'strong', 'i' => 'em', 'em' => 'em', 'u' => 'u', 's' => 's'];

    /** HTML bersih: <p>, <ol>, <ul>, <li>, <strong>, <em>, <u>, <s>, <br> saja. */
    public static function toHtml(?string $html): string
    {
        $out = '';
        foreach (self::blocks($html) as $block) {
            if ($block['type'] === 'p') {
                $out .= '<p>' . ($block['html'] === '' ? '<br>' : $block['html']) . '</p>';
            } else {
                $out .= '<' . $block['type'] . '>';
                foreach ($block['items'] as $item) {
                    $out .= '<li>' . $item . '</li>';
                }
                $out .= '</' . $block['type'] . '>';
            }
        }

        return $out;
    }

    /** Teks biasa per baris; daftar diberi awalan "1. " atau "• ". Untuk PDF yang dipecah per baris. */
    public static function toLines(?string $html): array
    {
        $lines = [];
        foreach (self::blocks($html) as $block) {
            if ($block['type'] === 'p') {
                $lines[] = self::plain($block['html']);
                continue;
            }
            foreach ($block['items'] as $n => $item) {
                $lines[] = ($block['type'] === 'ol' ? ($n + 1) . '. ' : '• ') . self::plain($item);
            }
        }
        while ($lines && trim(end($lines)) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /** @return array<int, array{type:string, html?:string, items?:array<int,string>}> */
    private static function blocks(?string $html): array
    {
        $html = trim((string) $html);
        if ($html === '') {
            return [];
        }

        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="rt">' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $dom->getElementById('rt');
        if (! $root) {
            return [['type' => 'p', 'html' => e(strip_tags($html))]];
        }

        $blocks = [];
        $addItem = function (string $type, string $item) use (&$blocks): void {
            $last = count($blocks) - 1;
            if ($last >= 0 && $blocks[$last]['type'] === $type) {
                $blocks[$last]['items'][] = $item;
            } else {
                $blocks[] = ['type' => $type, 'items' => [$item]];
            }
        };

        foreach ($root->childNodes as $node) {
            if (! $node instanceof DOMElement) {
                $text = trim($node->textContent ?? '');
                if ($text !== '') {
                    $blocks[] = ['type' => 'p', 'html' => e($text)];
                }
                continue;
            }

            $tag = strtolower($node->tagName);
            if ($tag === 'ol' || $tag === 'ul') {
                foreach ($node->childNodes as $li) {
                    if (! $li instanceof DOMElement || strtolower($li->tagName) !== 'li') {
                        continue;
                    }
                    $kind = $li->getAttribute('data-list') ?: ($tag === 'ul' ? 'bullet' : 'ordered');
                    $addItem($kind === 'bullet' ? 'ul' : 'ol', self::inline($li));
                }
            } else {
                $blocks[] = ['type' => 'p', 'html' => self::inline($node)];
            }
        }

        return $blocks;
    }

    private static function inline(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (str_contains(' ' . $child->getAttribute('class') . ' ', ' ql-ui ')) {
                    continue; // penanda angka milik editor
                }
                if ($tag === 'script' || $tag === 'style') {
                    continue;
                }
                if ($tag === 'br') {
                    $out .= '<br>';
                } elseif (isset(self::INLINE_KEEP[$tag])) {
                    $t = self::INLINE_KEEP[$tag];
                    $out .= "<$t>" . self::inline($child) . "</$t>";
                } else {
                    $out .= self::inline($child); // span, a, dll: ambil isinya saja
                }
            } else {
                $out .= e($child->textContent);
            }
        }

        return $out;
    }

    private static function plain(string $html): string
    {
        return html_entity_decode(strip_tags(str_replace('<br>', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
