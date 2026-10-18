<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Explicit private CSV selections sharing the visible report's dates and disclosure rules. */
final class DataExports {
    /** Render export choices for a validated report interval.
     * @param array $range Inclusive dates.
     * @param bool $search Whether only search aggregates should be offered.
     * @return void
     */
    public static function controls(array $range,bool $search=false): void {
        if(!Plugin::allowed('view')){return;}echo '<details class="tbm-card"><summary>'.esc_html__('Export this period','tabulamea').'</summary>';InterfaceTools::form('csv',['start'=>$range[0],'end'=>$range[1],'content_type'=>InterfaceTools::text($_GET,'content_type','all'),'content_search'=>InterfaceTools::text($_GET,'content_search')]);$choices=$search?['search'=>__('Internal search','tabulamea')]:['overview'=>__('Daily pageviews','tabulamea'),'contents'=>__('Public content totals','tabulamea'),'referrers'=>__('Referrer domains','tabulamea')];InterfaceTools::select('report',__('Export data','tabulamea'),$choices,array_key_first($choices));submit_button(__('Download CSV','tabulamea'),'secondary','submit',false);echo '</form><p class="description">'.esc_html__('Exports use the displayed dates and privacy rules. Content exports include up to 50 matching public items; referrer exports include up to 1,000 domains.','tabulamea').'</p></details>';
    }
    /** Prepare bounded rows without exposing deleted/private content or hidden search topics.
     * @param string $kind Fixed export type.
     * @param array $range Inclusive dates.
     * @return array Header and data rows.
     */
    public static function rows(string $kind,array $range): array {
        global $wpdb;$out=[];
        if($kind==='overview'){$out[]=[__('Date','tabulamea'),__('Pageviews','tabulamea')];$series=Insights::series($range);foreach($series['values'] as $date=>$views){$out[]=[$date,in_array($date,$series['known'],true)?$views:__('No retained record','tabulamea')];}}
        elseif($kind==='contents'){$out[]=[__('Title','tabulamea'),'URL',__('Pageviews','tabulamea'),__('Start date','tabulamea'),__('End date','tabulamea')];$a=['period'=>'custom','start'=>$range[0],'end'=>$range[1],'type'=>InterfaceTools::text($_POST,'content_type','all'),'search'=>mb_substr(InterfaceTools::text($_POST,'content_search'),0,80),'limit'=>50,'global_exclusions'=>false];foreach(Rankings::visible($a,Rankings::query($a)) as $row){$out[]=[get_the_title($row['ID']),get_permalink($row['ID']),$row['pageviews'],$range[0],$range[1]];}}
        elseif($kind==='referrers'){$out[]=[__('Referrer domain','tabulamea'),__('Pageviews','tabulamea'),__('Start date','tabulamea'),__('End date','tabulamea')];foreach($wpdb->get_results($wpdb->prepare("SELECT domain,SUM(pageviews) views FROM {$wpdb->prefix}tbm_referrer_stats WHERE date BETWEEN %s AND %s GROUP BY domain ORDER BY views DESC LIMIT 1000",$range),ARRAY_A) as $row){$out[]=[$row['domain'],$row['views'],$range[0],$range[1]];}}
        elseif($kind==='search'){$out[]=[__('Approved topic','tabulamea'),__('Search-result views','tabulamea'),__('Start date','tabulamea'),__('End date','tabulamea')];$topics=Plugin::settings()['search_topics'];foreach(SearchStats::rows($range)['topics'] as $row){if(isset($topics[$row['topic']])){$out[]=[$topics[$row['topic']],$row['views'],$range[0],$range[1]];}}}
        return $out;
    }
}
