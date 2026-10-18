<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Catalog of local public counters and saved ranking output. */
final class ShortcodeTools {
    /**
     * Render a progressive-enhancement shortcode generator and block reference.
     * @return void
     */
    public static function render(): void {
        echo '<p class="tbm-intro">'.esc_html__('Your overview, inside WordPress or on your website. Choose a saved ranking, a simple popular-content list or a pageview counter. All public output is opt-in.','tabulamea').'</p>';
        echo '<p>'.esc_html__('Equivalent blocks: saved ranking → TabulaMea content ranking; popular list → TabulaMea popular content; counter → TabulaMea pageview counter. Exact counts need their own publication permission.','tabulamea').'</p>';echo '<h2>'.esc_html__('Shortcode generator','tabulamea').'</h2><p>'.esc_html__('Choose an output and copy its shortcode into a Shortcode block or a compatible builder field. Public output requires explicit permission in Settings.','tabulamea').'</p>';
        echo '<div class="tbm-card" id="tbm-generator"><p><label for="tbm-shortcode-kind">'.esc_html__('Output','tabulamea').'</label> <select id="tbm-shortcode-kind"><option value="ranking">'.esc_html__('Saved content ranking','tabulamea').'</option><option value="popular">'.esc_html__('Most viewed content','tabulamea').'</option><option value="counter">'.esc_html__('Pageview counter','tabulamea').'</option></select></p>';
        echo '<div data-generator-panel="ranking"><p><label for="tbm-ranking-id">'.esc_html__('Saved ranking','tabulamea').'</label> <select id="tbm-ranking-id"><option value="">'.esc_html__('Choose a ranking','tabulamea').'</option>';
        foreach((array)get_option('tbm_rankings',[]) as $id=>$rank){echo '<option value="'.esc_attr($id).'" '.selected(InterfaceTools::text($_GET,'ranking'),(string)$id,false).'>'.esc_html($rank['title'].' ('.$id.')').'</option>';}
        echo '</select></p><p>'.esc_html__('Public output requires both the global public-ranking setting and publication of the selected ranking. A private ranking returns no public output.','tabulamea').'</p></div>';
        echo '<div data-generator-panel="popular"><p><label>'.esc_html__('Number of posts','tabulamea').' <input id="tbm-number" type="number" min="1" max="50" value="5"></label></p><p><label>'.esc_html__('Content type','tabulamea').' <select id="tbm-post-type">';
        foreach(get_post_types(['public'=>true],'objects') as $type){if($type->name==='attachment'){continue;}echo '<option value="'.esc_attr($type->name).'">'.esc_html($type->labels->name).'</option>';}
        echo '</select></label></p></div>';
        echo '<div data-generator-panel="counter"><p><label>'.esc_html__('Metric','tabulamea').' <select id="tbm-metric"><option value="pageviews">'.esc_html__('Pageviews','tabulamea').'</option></select></label></p><p><label><input type="checkbox" id="tbm-global"> '.esc_html__('Count the whole website instead of the current content','tabulamea').'</label></p></div>';
        echo '<p data-generator-days><label>'.esc_html__('Days','tabulamea').' <input id="tbm-days" type="number" min="1" max="36500" value="30"></label> <span>'.esc_html__('Defaults to 30 days. Minimum: 1 day.','tabulamea').'</span></p>';
        echo '<p><label for="tbm-generated">'.esc_html__('Generated shortcode','tabulamea').'</label><textarea id="tbm-generated" rows="2" readonly spellcheck="false">[tabulamea_ranking id="ranking-id"]</textarea></p><button type="button" class="button" id="tbm-copy" data-copied="'.esc_attr__('Copied.','tabulamea').'" data-fallback="'.esc_attr__('Select the shortcode and copy it with the keyboard.','tabulamea').'">'.esc_html__('Copy shortcode','tabulamea').'</button><p id="tbm-copy-status" role="status" aria-live="polite"></p>';
        echo '<noscript><p>'.esc_html__('JavaScript is needed for the generator. Copy and adapt one of the examples below.','tabulamea').'</p></noscript></div>';
        echo '<h2>'.esc_html__('Available shortcodes','tabulamea').'</h2>';
        self::reference('tabulamea_ranking','TabulaMea','[tabulamea_ranking id="ranking-id"]',__('Uses a saved ranking, including its period, content filters, count/image settings and optional snapshot. The only parameter is id.','tabulamea'));
        self::reference('tabulamea_popular','TabulaMea','[tabulamea_popular number="5" days="30" post_type="post"]',__('Linked list of popular content. Parameters: number (default 5), days (30), post_type (post).','tabulamea'));
        self::reference('tabulamea_counter','TabulaMea','[tabulamea_counter days="30" global="false"]',__('Pageview counter. Parameters: days (default 30), global (false = current content; true = whole website). No visitor identification.','tabulamea'));
        echo '<h2>'.esc_html__('Available blocks','tabulamea').'</h2><p>'.esc_html__('In the block editor, open the block inserter and search for the block name. Shortcodes can also be inserted using the WordPress Shortcode block.','tabulamea').'</p>';
        echo '<div class="tbm-documentation-cards">';
        self::reference(__('TabulaMea content ranking','tabulamea'),'TabulaMea','tabulamea/ranking',__('Displays a saved ranking. Select a published ranking in the block settings; public output must also be enabled globally.','tabulamea'));
        self::reference(__('TabulaMea pageview counter','tabulamea'),'TabulaMea','tabulamea/counter',__('Displays current-content or whole-site pageviews. Choose the period and scope in the block sidebar; the block itself previews the output.','tabulamea'));
        self::reference(__('TabulaMea popular content','tabulamea'),'TabulaMea','tabulamea/popular',__('Linked popular-content list with controls for period, item count and public content type.','tabulamea'));
        self::reference(__('TabulaMea popular content loop','tabulamea'),'TabulaMea','core/query · tabulamea/popular-query',__('Based on the WordPress Query Loop block, ordered by recorded pageviews over the last 30 days. Design titles, images and excerpts using normal blocks. Up to 50 ranked contents; public output must be enabled.','tabulamea'));
        echo '</div>';


    }
    /**
     * Print an escaped catalog card.
     * @param string $title Display label.
     * @param string $owner Owning plugin.
     * @param string $example Copyable syntax or block identifier.
     * @param string $description Verified behavior.
     * @return void
     */
    private static function reference(string $title,string $owner,string $example,string $description): void {
        echo '<section class="tbm-card"><h3>'.esc_html($title).'</h3><p>'.esc_html($owner).'</p>'.(str_contains($example,'/')&&!str_starts_with($example,'[')?'<details><summary>'.esc_html__('Technical details','tabulamea').'</summary><code>'.esc_html($example).'</code></details>':'<code>'.esc_html($example).'</code>').'<p>'.esc_html($description).'</p></section>';
    }
}
