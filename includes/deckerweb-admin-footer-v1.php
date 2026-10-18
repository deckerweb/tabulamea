<?php
/**
 * DECKERWEB Admin Footer, compatible API v1 with structured documents.
 * Based on Daily Scripture / Brand Admin Schemes and the shared changelog renderer.
 * Copyright © 2012–2026 David Decker – DECKERWEB.
 * SPDX-License-Identifier: GPL-2.0-or-later
 */
namespace Deckerweb\AdminFooter\V1;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( StructuredFooter::class, false ) ) {
	/** Render local documents without changing another plugin's older Footer class. */
	final class StructuredFooter {
		/**
		 * Print one footer and its accessible native dialogs.
		 *
		 * @param array $config Host identity, translated labels and local documents.
		 * @return void
		 */
		public static function render( array $config ): void {
			$id = sanitize_html_class( $config['id'] );
			echo '<footer class="ddw-admin-footer" aria-label="' . esc_attr( $config['about'] ) . '"><div><strong>' . esc_html( $config['name'] ) . '</strong> <span>' . esc_html( $config['version_label'] . ' ' . $config['version'] ) . '</span>';
			foreach ( $config['documents'] as $key => $document ) {
				$target = $id . '-document-' . sanitize_html_class( $key );
				echo ' · <a href="' . esc_url( $document['url'] ) . '" data-ddw-document="' . esc_attr( $target ) . '">' . esc_html( $document['label'] ) . '</a>';
			}
			echo '<p>' . esc_html( $config['slogan'] ) . '</p></div><div><span>' . esc_html( $config['copyright'] ) . ' <a href="https://github.com/deckerweb" target="_blank" rel="noopener noreferrer">David Decker – DECKERWEB</a></span><a href="' . esc_url( $config['repository'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $config['website'] ) . '</a></div></footer>';
			foreach ( $config['documents'] as $key => $document ) {
				$target = $id . '-document-' . sanitize_html_class( $key );
				$text = (string) $document['text'];
				$html = 'changelog' === $key ? \Deckerweb_Changelog_Renderer_V1::render( self::history($text) ) : self::document( $text );
				echo '<dialog hidden class="ddw-document-dialog" id="' . esc_attr( $target ) . '" aria-labelledby="' . esc_attr( $target . '-title' ) . '"><div class="ddw-document-header"><h2 id="' . esc_attr( $target . '-title' ) . '">' . esc_html( $document['label'] ) . '</h2><form method="dialog" class="ddw-document-close-form"><button type="submit" class="button" data-ddw-close>' . esc_html( $config['close'] ) . '</button></form></div><div class="ddw-document-content" tabindex="0">' . $html . '</div></dialog>';
			}
		}

        /**
         * Normalize plain history for compatible shared renderer copies from other hosts.
         *
         * @param string $text Local categorized release history.
         * @return string WordPress-format headings and entries, still untrusted text.
         */
        private static function history(string $text): string {
            if (strlen($text) > 262144) { return ''; }
            $text = preg_replace('/^(\d+\.\d+\.\d+[^\r\n]*)$/m', '= $1 =', $text);
            $text = preg_replace('/^(New|Neu|Improved|Verbessert|Fixed|Behoben|Misc|Sonstiges):/m', '* $1:', $text);
            return str_replace(' — ', ' · ', $text);
        }

		/**
		 * Convert bounded readme text to fixed markup after escaping every source line.
		 *
		 * @param string $text Local readme; no source HTML is accepted.
		 * @return string Escaped document paragraphs, headings and lists.
		 */
		private static function document( string $text ): string {
			if ( strlen( $text ) > 262144 ) { return ''; }
			$html = ''; $list = ''; $lines = preg_split('/\R/', $text);
            foreach ($lines as $index => $source) {
                $line = trim($source);
                // File navigation belongs to downloadable documents, not the local reader.
                if (preg_match('/^(?:English:|Deutsch:|Full guide:|Ausführliche Anleitung:|FAQ by topic:|Fragen nach Themen:)/u', $line) || preg_match('/^[─━_-]{3,}$/u', $line)) { continue; }
                $next = trim($lines[$index + 1] ?? '');
                $heading = $index === 0 || preg_match('/^[─━_-]{3,}$/u', $next) || str_ends_with($line, '?');
                $item = !$heading && preg_match('/^(?:([*\-]) |(\d+)\. )(.+)$/u', $line, $match);
                $kind = $item ? (!empty($match[2]) ? 'ol' : 'ul') : '';
                if ($list && $kind !== $list) { $html .= '</'.$list.'>'; $list = ''; }
                if ($line === '') { continue; }
                if ($item) {
                    if (!$list) { $list = $kind; $html .= '<'.$list.'>'; }
                    $html .= '<li>'.esc_html($match[3]).'</li>';
                } elseif ($heading || preg_match('/^={1,3}\s+(.+?)\s+={1,3}$/', $line, $title)) {
                    $html .= '<h3>'.esc_html($title[1] ?? $line).'</h3>'; unset($title);
                } else { $html .= '<p>'.esc_html($line).'</p>'; }
            }
            return $html . ($list ? '</'.$list.'>' : '');
		}
	}
}
if ( ! class_exists( Footer::class, false ) ) {
	class_alias( StructuredFooter::class, Footer::class );
}
