<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Independent search-result aggregates containing only administrator-approved topic IDs. */
final class SearchStats {
    /** Register public search delivery and the private report. @return void */
    public static function boot(): void {add_action('wp_enqueue_scripts',[self::class,'enqueue']);add_action('admin_menu',static function(){if(Plugin::allowed('view')){add_submenu_page('tabulamea-statistics',__('Internal search','tabulamea'),__('Internal search','tabulamea'),'view_tabulamea','tabulamea-search',[self::class,'page']);}},110);}
    /** Create anonymous daily search totals without search text. @return void */
    public static function install(): void {global $wpdb;dbDelta("CREATE TABLE {$wpdb->prefix}tbm_search_stats (date date NOT NULL, topic varchar(16) NOT NULL, no_results tinyint unsigned NOT NULL DEFAULT 0, views bigint unsigned NOT NULL DEFAULT 0, PRIMARY KEY  (date,topic,no_results)) ENGINE=InnoDB {$wpdb->get_charset_collate()};");}
    /** Normalize an in-memory phrase without persisting it.
     * @param string $text Raw input or approved phrase.
     * @return string Case-insensitive normalized text.
     */
    public static function normalize(string $text): string {return mb_strtolower(trim(preg_replace('/\s+/u',' ',$text)??''));}
    /** Enqueue one anonymous request only on the first results page. @return void */
    public static function enqueue(): void {
        $s=Plugin::settings();if(!$s['measurement']||!$s['search_enabled']||!Plugin::ready()||!is_search()||is_paged()||is_feed()||is_preview()||array_intersect(wp_get_current_user()->roles,$s['exclude_roles'])){return;}
        $input=self::normalize((string)get_search_query(false));$topic='';foreach($s['search_topics'] as $id=>$label){if($input===self::normalize($label)){$topic=$id;break;}}global $wp_query;$empty=(int)($wp_query->found_posts===0);$proof=hash_hmac('sha256',get_current_blog_id().'|search|'.$topic.'|'.$empty,wp_salt('auth'));
        wp_enqueue_script('tabulamea-measure',plugins_url('assets/measure.js',TBM_FILE),[],TBM_VERSION,['in_footer'=>true,'strategy'=>'defer']);wp_add_inline_script('tabulamea-measure','window.tabulaMeaPage='.wp_json_encode(['endpoint'=>rest_url('tabulamea/v1/pageview'),'post'=>0,'path'=>'/__search__','signature'=>Measurement::signature(0,'/__search__'),'context'=>['topic'=>$topic,'empty'=>$empty,'proof'=>$proof]]).';','before');
    }
    /** Accept a signed search context after the ordinary endpoint's size, origin and bot checks.
     * @param array $a Parsed request payload.
     * @return \WP_REST_Response|\WP_Error Empty success or retryable failure.
     */
    public static function receive(array $a) {
        $s=Plugin::settings();if(!$s['search_enabled']){return new \WP_REST_Response(null,204);}$c=$a['context']??null;
        if(!is_array($c)||!is_string($c['topic']??null)||!in_array($c['empty']??null,[0,1],true)||!is_string($c['proof']??null)){return new \WP_Error('invalid_search',__('Invalid search delivery.','tabulamea'),['status'=>400]);}
        $topic=$c['topic'];$empty=$c['empty'];$proof=hash_hmac('sha256',get_current_blog_id().'|search|'.$topic.'|'.$empty,wp_salt('auth'));
        if(!hash_equals($proof,$c['proof'])||($topic!==''&&!isset($s['search_topics'][$topic]))){return new \WP_Error('invalid_search',__('Invalid search delivery.','tabulamea'),['status'=>400]);}
        $event=$a['event']??'';if(!is_string($event)||($event!==''&&!preg_match('/^[a-f0-9]{32}$/D',$event))){return new \WP_Error('invalid_search',__('Invalid search delivery.','tabulamea'),['status'=>400]);}
        $budget=CoreStore::budget();if($budget!==true){return new \WP_REST_Response(null,$budget===false?429:503);}
        $date=wp_date('Y-m-d');
        if (!CoreStore::search($date,$topic,$empty,$event)) {
            CoreStore::failure('storage');
            return new \WP_Error('storage_failed',__('Statistics could not be saved.','tabulamea'),['status'=>503]);
        }
        add_option('tbm_search_since',$date,'',false);
        if(wp_rand(1,100)===1){CoreStore::cleanup();}
        return new \WP_REST_Response(null,204);
    }
    /** Read private search aggregates for a valid interval.
     * @param array $range Inclusive dates.
     * @return array Totals and approved-topic rows above the disclosure threshold.
     */
    public static function rows(array $range): array {
        global $wpdb;$totals=$wpdb->get_row($wpdb->prepare("SELECT COALESCE(SUM(views),0) views,COALESCE(SUM(IF(no_results=1,views,0)),0) no_results_views FROM {$wpdb->prefix}tbm_search_stats WHERE topic='' AND date BETWEEN %s AND %s",$range),ARRAY_A);$rows=$wpdb->get_results($wpdb->prepare("SELECT topic,SUM(views) views FROM {$wpdb->prefix}tbm_search_stats WHERE topic<>'' AND date BETWEEN %s AND %s GROUP BY topic HAVING SUM(views)>=5 ORDER BY views DESC,topic ASC LIMIT 50",$range),ARRAY_A);return ['totals'=>$totals,'topics'=>$rows];
    }
    /** Render a private annual or rolling search ranking without arbitrary search strings. @return void */
    public static function page(): void {
        if(!Plugin::allowed('view')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}echo '<div class="wrap tbm tbm-statistics">';Settings::header();Navigation::links();$a=ReportTools::requested();ReportTools::controls('tabulamea-search',$a);$range=Rankings::dates($a);if(!$range){echo '<p>'.esc_html__('Choose a valid date range.','tabulamea').'</p>';Admin::footer();echo '</div>';return;}$data=self::rows($range);$s=Plugin::settings();echo '<section class="tbm-card"><h2>'.esc_html__('Internal search','tabulamea').'</h2><p>'.esc_html__('First search-results page loads, including reloads. These are not unique searches or people. Search views are separate from regular pageviews.','tabulamea').'</p><p>'.esc_html(sprintf(
/* translators: %1$s: formatted search-result pageviews; %2$s: formatted views of empty search results. */
__('Search-result views: %1$s · Without results: %2$s','tabulamea'),number_format_i18n((int)$data['totals']['views']),number_format_i18n((int)$data['totals']['no_results_views']))).'</p>';
        if(!$s['search_enabled']){echo '<p class="tbm-attention">'.esc_html__('Search collection is disabled. Existing history remains available.','tabulamea').'</p>';}
        echo '<h3>'.esc_html__('Most used approved search topics','tabulamea').'</h3><p>'.esc_html__('Only approved topics with at least five recorded views are listed. Unknown search phrases are never saved. Select a calendar year for a year-in-review ranking.','tabulamea').'</p><ol>';
        foreach($data['topics'] as $row){$label=$s['search_topics'][$row['topic']]??null;if($label===null){continue;}echo '<li><a href="'.esc_url(add_query_arg('s',$label,home_url('/'))).'">'.esc_html($label).'</a> · '.esc_html(number_format_i18n((int)$row['views'])).'</li>';}echo '</ol>';if(!$data['topics']){echo '<p class="tbm-empty">'.esc_html__('No approved topic meets the display threshold in this period.','tabulamea').'</p>';}if(current_user_can('manage_options')){echo '<p><a href="'.esc_url(InterfaceTools::settings_url('measurement')).'">'.esc_html__('Review settings','tabulamea').'</a></p>';}echo '</section>';DataExports::controls($range,true);Admin::footer();echo '</div>';
    }
}
