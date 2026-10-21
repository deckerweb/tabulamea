<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Independent dimension reports with identical display/export suppression. */
final class DimensionReports {
    /** Return the supported independent dimensions. @return array Keys and translated labels. */
    public static function labels(): array { return ['device'=>__('Devices','tabulamea'),'browser'=>__('Browsers','tabulamea'),'os'=>__('Operating systems','tabulamea'),'country'=>__('Countries','tabulamea')]; }
    /**
     * Return only reportable categories; percentages use visible rows only.
     * @param string $dimension Allowed dimension key.
     * @param array $range Two ISO dates.
     * @return array Visible rows with pageviews and share of displayed categories.
     */
    public static function rows(string $dimension,array $range): array {
        if(!Plugin::ready()||!isset(self::labels()[$dimension])||count($range)!==2){return [];}global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT value,SUM(pageviews) pageviews FROM {$wpdb->prefix}tbm_dimensions WHERE dimension=%s AND date BETWEEN %s AND %s GROUP BY value HAVING SUM(pageviews)>=5 ORDER BY pageviews DESC,value ASC",$dimension,...$range),ARRAY_A);
        $rows=is_array($rows)?$rows:[];$total=array_sum(array_column($rows,'pageviews'));foreach($rows as &$row){$row['share']=$total?round(100*(int)$row['pageviews']/$total,1):0;}unset($row);return $rows;
    }
    /**
     * Explain an enabled, disabled, uninitialized or technically paused module.
     * @param string $dimension Allowed dimension key.
     * @return string Translated state.
     */
    public static function state(string $dimension): string {
        $s=Plugin::settings();if(!($dimension==='country'?$s['country']:$s['technology'])){return __('Disabled','tabulamea');}
        if(!Plugin::ready()||!Tracking::supported()){return __('Collection is technically paused','tabulamea');}
        if($dimension==='country'&&!is_readable(Tracking::geo_path())){return __('Country database missing; countries remain unknown','tabulamea');}
        return get_option('tbm_since_'.$dimension)?__('Collecting','tabulamea'):__('Waiting for the first accepted pageview','tabulamea');
    }
    /** Render the separate dimension reports and their final coverage card. @return void */
    public static function page(): void {
        if(!Plugin::ready()||!Plugin::allowed('view')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}
        echo '<div class="wrap tbm">';Settings::header(__('Additional statistics','tabulamea'));Settings::status();Navigation::links();$a=ReportTools::requested();$range=Rankings::dates($a);ReportTools::controls('tabulamea-dimensions',$a);
        echo '<p>'.esc_html__('These reports count pageviews, not unique people. Percentages use displayed categories only; categories below 5 pageviews and their totals are never exported.','tabulamea').'</p>';
        if(!$range){echo '<p role="alert">'.esc_html__('Choose a valid date range; the end must not precede the start.','tabulamea').'</p>';}
        else{foreach(self::labels() as $dim=>$label){echo '<section class="tbm-card"><h2>'.esc_html($label).'</h2><p class="tbm-state">'.esc_html(self::state($dim)).'</p><p>'.esc_html__('Collection started:','tabulamea').' '.esc_html(get_option('tbm_since_'.$dim,__('Not collected yet','tabulamea'))).'</p>';$rows=self::rows($dim,$range);
            if(!$rows){echo '<p>'.esc_html__('No categories reach the display threshold in this period. Check the collection status and try a longer period.','tabulamea').'</p>';}
            else{echo '<table class="widefat"><thead><tr><th>'.esc_html__('Category','tabulamea').'</th><th>'.esc_html__('Pageviews','tabulamea').'</th><th>'.esc_html__('Share of displayed categories','tabulamea').'</th></tr></thead><tbody>';foreach($rows as $row){echo '<tr><td>'.esc_html(Tracking::label($row['value'])).'</td><td>'.esc_html(number_format_i18n((int)$row['pageviews'])).'</td><td><meter min="0" max="100" value="'.esc_attr((string)$row['share']).'" aria-label="'.esc_attr(Tracking::label($row['value'])).'"></meter> '.esc_html(number_format_i18n($row['share'],1)).' %</td></tr>';}echo '</tbody></table>';}
            echo '<details><summary>'.esc_html__('How to read this report','tabulamea').'</summary><p>'.esc_html__('Detection is approximate. Unknown includes requests that could not be classified. Its percentage is shown only when that category meets the same display threshold. Hidden categories are excluded from percentage denominators.','tabulamea').'</p></details>';
            InterfaceTools::form('csv',['report'=>'dimension','dimension'=>$dim,'start'=>$range[0],'end'=>$range[1]]);submit_button(__('Download CSV','tabulamea'),'secondary','submit',false);echo '</form></section>';
        }}
        self::coverage();Admin::footer();echo '</div>';
    }
    /** Render coverage last, with traffic-sensitive interpretation. @return void */
    public static function coverage(): void {
        echo '<section class="tbm-card tbm-coverage"><h2>'.esc_html__('Data coverage','tabulamea').'</h2>';global $wpdb;$first=$wpdb->get_var("SELECT MIN(date) FROM {$wpdb->prefix}tbm_site_stats WHERE pageviews>0");
        echo '<p>'.esc_html__('Available statistics history since:','tabulamea').' '.esc_html($first?:__('No data available','tabulamea')).'</p><p>'.esc_html__('This is the earliest retained record, not necessarily the original installation date.','tabulamea').'</p>';
        foreach(self::labels() as $dim=>$label){$row=(array)$wpdb->get_row($wpdb->prepare("SELECT MIN(date) first,MAX(date) last,COUNT(DISTINCT date) days,COALESCE(SUM(pageviews),0) views FROM {$wpdb->prefix}tbm_dimensions WHERE dimension=%s",$dim),ARRAY_A);$since=get_option('tbm_since_'.$dim);$elapsed=$since?(int)(new \DateTimeImmutable($since,wp_timezone()))->diff(new \DateTimeImmutable('today',wp_timezone()))->days+1:0;
            echo '<h3>'.esc_html($label).'</h3><p>'.esc_html(self::state($dim)).' · '.esc_html($since?:__('Not collected yet','tabulamea')).'</p>';
            if(!empty($row['first'])){echo '<p>'.esc_html(sprintf(
/* translators: %1$s: first date; %2$s: last date; %3$d: days with recorded pageviews. */
__('Retained records: %1$s to %2$s, with pageviews on %3$d days.','tabulamea'),$row['first'],$row['last'],(int)$row['days'])).'</p>';}
            echo '<p>'.esc_html($elapsed>=30&&(int)($row['days'] ?? 0)>=14&&(int)($row['views'] ?? 0)>=100?__('An initial observation basis is available. This is not a guarantee of statistical significance.','tabulamea'):__('Early or sparse data: allow at least 30 calendar days, sufficient traffic and regular recorded days before comparing categories.','tabulamea')).'</p>';
        }
        echo '<p class="description">'.esc_html__('The initial-basis indicator requires at least 30 calendar days, records on 14 days and 100 pageviews. It is an orientation aid, not a significance test.','tabulamea').'</p>';
        echo '<p>'.esc_html__('Days without records may mean no traffic or a collection gap. Thirty days alone do not guarantee a useful comparison. Missing history is never estimated.','tabulamea').'</p></section>';
    }
}
