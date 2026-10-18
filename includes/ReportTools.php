<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Inclusive periods, safe report explanations and private preferences. */
final class ReportTools {
    /**
     * Return translated period presets.
     * @return array Period keys and labels.
     */
    public static function periods(): array {
        return ['today'=>__('Today','tabulamea'),'yesterday'=>__('Yesterday','tabulamea'),'7_days'=>__('Last 7 days','tabulamea'),'14_days'=>__('Last 14 days','tabulamea'),'30_days'=>__('Last 30 days','tabulamea'),'90_days'=>__('Last 90 days','tabulamea'),'180_days'=>__('Last 180 days','tabulamea'),'365_days'=>__('Last 365 days','tabulamea'),'month'=>__('This month','tabulamea'),'previous_month'=>__('Last month','tabulamea'),'year'=>__('Calendar year','tabulamea'),'previous_year'=>__('Previous calendar year','tabulamea'),'custom'=>__('Custom dates','tabulamea')];
    }
    /**
     * Return a valid requested date definition, with the personal default as fallback.
     * @return array Validated period definition.
     */
    public static function requested(): array {
        $prefs=(array)get_user_option('tbm_preferences');
        $period=InterfaceTools::text($_GET,'period',isset($_GET['start'])?'custom':($prefs['report_period'] ?? '30_days'));
        $a=['period'=>array_key_exists($period,self::periods())?$period:'30_days','year'=>(int)InterfaceTools::text($_GET,'year',wp_date('Y')),'start'=>InterfaceTools::text($_GET,'start'),'end'=>InterfaceTools::text($_GET,'end')];
        return $a;
    }
    /**
     * Render native GET period controls and an independent personal-default action.
     * @param string $page Registered report slug.
     * @param array $a Current period definition.
     * @return void
     */
    public static function controls(string $page,array $a): void {
        Insights::navigate($page,$a);echo '<form method="get" class="tbm-card tbm-period"><input type="hidden" name="page" value="'.esc_attr($page).'">';
        if(Insights::content()){echo '<input type="hidden" name="content" value="'.(int)Insights::content().'">';}foreach(['content_type','content_search','items','chart','compare'] as $key){if(isset($_GET[$key])){echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr(InterfaceTools::text($_GET,$key)).'">';}}InterfaceTools::select('period',__('Period','tabulamea'),self::periods(),$a['period']);
        InterfaceTools::input('year',__('Calendar year','tabulamea'),$a['year'],'number');
        InterfaceTools::input('start',__('Start date for custom period','tabulamea'),$a['start'],'date');
        InterfaceTools::input('end',__('End date for custom period','tabulamea'),$a['end'],'date');
        echo '<button class="button button-primary">'.esc_html__('Apply','tabulamea').'</button><p class="description">'.esc_html__('Date fields apply only to Custom dates. Calendar year uses the year field.','tabulamea').'</p></form>';
        if(in_array($a['period'],['today','yesterday','7_days','14_days','30_days','90_days','180_days','365_days','month','previous_month','previous_year'],true)){
            InterfaceTools::form('preference',['area'=>'report_period','period'=>$a['period'],'return_page'=>$page]);submit_button(__('Use this period as my default','tabulamea'),'secondary','submit',false);echo '</form>';
        }
    }
    /**
     * Summarize retained pageview evidence for an interval, without claiming zero-traffic days are outages.
     * @param array $range Two ISO dates.
     * @return array Evidence summary.
     */
    public static function evidence(array $range): array {
        global $wpdb;
        if(count($range)!==2 || !Plugin::ready()){return ['first'=>'','last'=>'','days'=>0,'views'=>0,'partial'=>true];}
        $row=(array)$wpdb->get_row($wpdb->prepare("SELECT MIN(date) first,MAX(date) last,COUNT(DISTINCT date) days,COALESCE(SUM(pageviews),0) views FROM {$wpdb->prefix}tbm_site_stats WHERE date BETWEEN %s AND %s AND pageviews>0",$range),ARRAY_A);
        $first=$wpdb->get_var("SELECT MIN(date) FROM {$wpdb->prefix}tbm_site_stats WHERE pageviews>0");
        $row['partial']=!$first || $first>$range[0];return $row;
    }
    /**
     * Explain historical limits alongside comparisons and editorial output.
     * @param array $range Two ISO dates.
     * @return void
     */
    public static function explain(array $range): void {
        $e=self::evidence($range);
        echo '<p class="description">'.esc_html(sprintf(__('Retained days with pageviews: %d. Days without records may mean no traffic or interrupted collection. Missing history is not estimated.','tabulamea'),(int)$e['days'])).'</p>';
        if($e['partial']){echo '<p class="tbm-attention">'.esc_html__('The retained history does not cover the start of this period. Interpret totals and changes with care.','tabulamea').'</p>';}
    }
    /**
     * Save a site-specific display preference without changing global settings.
     * @return void
     */
    public static function preference(): void {
        $area=InterfaceTools::text($_POST,'area');
        if(in_array($area,['overview_widget','compact_widget'],true)){DashboardTools::save_preference($area);return;}
        $period=InterfaceTools::text($_POST,'period');$allowed=$area==='widget_period'?Plugin::allowed('widget'):Plugin::allowed('view');
        if(!$allowed||!in_array($area,['widget_period','report_period'],true)||!isset(DashboardTools::periods()[$period])||($area==='widget_period'&&!Plugin::settings()['widget_personal'])){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}
        $prefs=(array)get_user_option('tbm_preferences');$prefs[$area]=$period;update_user_option(get_current_user_id(),'tbm_preferences',$prefs,false);
        $page=InterfaceTools::text($_POST,'return_page','tabulamea-statistics');if(!in_array($page,['tabulamea-statistics','tabulamea-dimensions','tabulamea-search'],true)){$page='tabulamea-statistics';}wp_safe_redirect($area==='widget_period'?admin_url('index.php'):admin_url('admin.php?page='.$page));exit;

    }
}
