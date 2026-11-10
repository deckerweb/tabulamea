<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Coarse operational information, bounded maintenance and a non-mandatory setup check. */
final class OperationalStatus {
    /** Render factual setup steps without enabling optional collection. @return void */
    public static function setup(): void {
        if(!current_user_can('manage_options')){return;}$s=Plugin::settings();
        echo '<section class="tbm-card"><h2>'.esc_html__('Ready to use TabulaMea','tabulamea').'</h2><ul class="tbm-setup-check">';
        $rows=[Plugin::ready()?__('Statistics storage is ready.','tabulamea'):__('Statistics storage needs attention.','tabulamea'),$s['measurement']?__('Measurement is switched on.','tabulamea'):__('Measurement is paused.','tabulamea'),array_intersect(wp_get_current_user()->roles,$s['exclude_roles'])?__('Your signed-in role is excluded from regular pageviews.','tabulamea'):__('Your signed-in role is not excluded from regular pageviews.','tabulamea'),__('Country, technology and search reporting are independent optional choices.','tabulamea')];
        foreach($rows as $row){echo '<li>'.esc_html($row).'</li>';}echo '</ul><p>'.esc_html__('Next step: use the guided measurement test. It checks delivery from this browser and does not add a pageview. Privacy signals still apply.','tabulamea').'</p></section>';
    }
    /** Append a coarse failure at most once per minute, retaining only 20 records.
     * @param string $code Fixed category, never raw SQL or request data.
     * @return void
     */
    public static function failure(string $code): void {
        $code=in_array($code,['capacity','storage','country'],true)?$code:'storage';$history=(array)get_option('tbm_operations',[]);$last=end($history);
        if(is_array($last)&&$last['code']===$code&&(int)$last['time']>time()-60){return;}
        $history[]=['time'=>time(),'code'=>$code];update_option('tbm_operations',array_slice($history,-20),false);
    }
    /** Explain the latest fault and subsequent successful regular delivery. @return void */
    public static function panel(): void {
        $history=(array)get_option('tbm_operations',[]);$last=(int)get_option('tbm_last_received',0);$maintenance=(int)get_option('tbm_last_maintenance',0);
        echo '<details class="tbm-card"><summary>'.esc_html__('Recent technical operation','tabulamea').'</summary><p>'.esc_html__('Last successful maintenance:','tabulamea').' '.esc_html($maintenance?wp_date(get_option('date_format').' '.get_option('time_format'),$maintenance):__('Not yet recorded','tabulamea')).'</p><ul>';
        $labels=['storage'=>__('A statistics write failed.','tabulamea'),'capacity'=>__('The measurement request limit was reached.','tabulamea'),'country'=>__('The country database could not be updated. An existing database is not replaced.','tabulamea')];
        foreach(array_reverse(array_slice($history,-20)) as $entry){if(!is_array($entry)||!isset($labels[$entry['code']??''])){continue;}echo '<li>'.esc_html(wp_date(get_option('date_format').' '.get_option('time_format'),(int)$entry['time']).' · '.$labels[$entry['code']]);if($entry['code']!=='country'&&$last>(int)$entry['time']){echo ' '.esc_html__('A regular pageview was stored successfully afterwards.','tabulamea');}echo '</li>';}
        echo '</ul><p>'.esc_html__('Only technical categories and times are retained, at most 20 entries. A later success does not reconstruct missed pageviews.','tabulamea').'</p></details>';
    }
    /** Remove a bounded batch of expired report caches and abandoned rebuild locks. @return bool Whether cleanup completed without an SQL failure. */
    public static function cleanup(): bool {
        global $wpdb;$prefixes=['_transient_timeout_tbm_rank_','_transient_timeout_tbm_draft_','_transient_timeout_tbm_stage_'];$ok=true;
        foreach($prefixes as $prefix){$names=$wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED)<%d LIMIT 100",$wpdb->esc_like($prefix).'%',time()));$ok=$ok&&!$wpdb->last_error;foreach((array)$names as $name){delete_transient(substr($name,strlen('_transient_timeout_')));}}
        $locks=$wpdb->get_results($wpdb->prepare("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 100",$wpdb->esc_like('tbm_report_lock_').'%'));
        $ok=$ok&&!$wpdb->last_error;foreach((array)$locks as $lock){$value=maybe_unserialize($lock->option_value);if(is_array($value)&&(int)($value['expires']??0)<time()){$ok=$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s",$lock->option_name,$lock->option_value))!==false&&$ok;wp_cache_delete($lock->option_name,'options');}}
        $history=array_values(array_filter((array)get_option('tbm_operations',[]),static fn($e)=>is_array($e)&&(int)($e['time']??0)>time()-30*DAY_IN_SECONDS));if($history!==(array)get_option('tbm_operations',[])){update_option('tbm_operations',$history,false);}return $ok;
    }
}
