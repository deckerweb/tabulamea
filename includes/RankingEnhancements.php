<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Plain-language ranking prerequisites, reproducible snapshots and personal favorites. */
final class RankingEnhancements {
    /** Explain analytical methods using concrete editorial examples. @return void */
    public static function method_help(): void {
        echo '<details class="tbm-card"><summary>'.esc_html__('Which ranking method fits?','tabulamea').'</summary><dl>';
        foreach([
            __('Most viewed','tabulamea')=>__('Example: the ten posts with the most recorded pageviews in the selected year.','tabulamea'),
            __('Gaining attention','tabulamea')=>__('Example: content with the largest absolute increase compared with the preceding equally long period. Small changes are not proof of a trend.','tabulamea'),
            __('Losing attention','tabulamea')=>__('Example: previously popular content whose recorded views fell most. This does not assess content quality.','tabulamea'),
            __('Popular every month','tabulamea')=>__('Example: content reaching at least ten views in each of three complete calendar months. Requires retained history for every selected month.','tabulamea'),
            __('Older content gaining attention','tabulamea')=>__('Example: a post published over a year ago that is now receiving more views. Uses publication age and an absolute period comparison.','tabulamea')
        ] as $label=>$text){echo '<dt><strong>'.esc_html($label).'</strong></dt><dd>'.esc_html($text).'</dd>';}echo '</dl></details>';
    }
    /** Return a factual limitation when the selected historical basis is incomplete.
     * @param array $rank Ranking definition.
     * @return string Explanation, or empty when no limitation was found.
     */
    public static function assessment(array $rank): string {
        $range=$rank['snapshot']['range']??Rankings::dates($rank);if(!$range){return __('Choose a valid date range.','tabulamea');}$e=ReportTools::evidence($range);
        if(($rank['mode']??'')==='evergreen'&&$e['partial']){return __('The retained history does not cover all requested complete months. Save this definition for later, or choose fewer months before interpreting its results.','tabulamea');}
        if($e['partial']){return __('This period starts before the retained measurement history. Results describe the available records only.','tabulamea');}return '';
    }
    /** Display prerequisites and the exact frozen rules used by a saved snapshot.
     * @param array $rank Definition and optional snapshot.
     * @return void
     */
    public static function explain(array $rank): void {
        $message=self::assessment($rank);if($message){echo '<p class="tbm-attention">'.esc_html($message).'</p>';}
        if(in_array($rank['mode']??'popular',['rising','falling','rediscovered'],true)){echo '<p class="description">'.esc_html__('Changes are absolute counts. The minimum baseline avoids treating tiny numbers as meaningful trends. These numbers do not measure quality or conversions.','tabulamea').'</p>';}
        if(isset($rank['snapshot']['definition'])){$d=$rank['snapshot']['definition'];echo '<details><summary>'.esc_html__('Rules captured with this snapshot','tabulamea').'</summary><p>'.esc_html((RankingAdmin::modes()[$d['mode']]??'').' · '.($d['type']??'').' · '.sprintf(__('Maximum items: %d','tabulamea'),(int)($d['limit']??10))).'</p><p>'.esc_html(sprintf(__('Minimum views: %1$d · Complete months: %2$d','tabulamea'),(int)($d['min_views']??10),(int)($d['months']??3))).'</p><p>'.esc_html((ReportTools::periods()[$d['period']]??'').' · '.sprintf(__('Excluded contents: %1$d · Publication year: %2$s','tabulamea'),count((array)($d['exclude_ids']??[])),!empty($d['published_year'])?(string)$d['published_year']:__('All years','tabulamea'))).'</p></details>';}
    }
    /** Offer a protected personal favorite action without changing shared rankings.
     * @param string $id Saved ranking ID.
     * @param array $favorites Current user's ranking IDs.
     * @return void
     */
    public static function favorite(string $id,array $favorites): void {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="tbm-command"><input type="hidden" name="action" value="tbm_insight"><input type="hidden" name="operation" value="favorite"><input type="hidden" name="id" value="'.esc_attr($id).'">';wp_nonce_field('tbm_insight_favorite');submit_button(in_array($id,$favorites,true)?__('Remove from my favorites','tabulamea'):__('Add to my favorites','tabulamea'),'secondary','submit',false);echo '</form>';
    }
}
