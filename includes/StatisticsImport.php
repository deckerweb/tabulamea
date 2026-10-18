<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Read-only Koko table import with resumable batches and an atomic duplicate ledger. No Koko code is included. */
final class StatisticsImport {
    /** Check a fixed table in the current site's database.
     * @param string $suffix Fixed source table suffix.
     * @return bool Whether the table exists.
     */
    private static function exists(string $suffix): bool {global $wpdb;return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix.$suffix)))===$wpdb->prefix.$suffix;}
    /** Inspect source columns to distinguish supported Koko storage generations.
     * @param string $suffix Fixed table suffix.
     * @return array Column names.
     */
    private static function columns(string $suffix): array {global $wpdb;return self::exists($suffix)?$wpdb->get_col('SHOW COLUMNS FROM '.$wpdb->prefix.$suffix):[];}
    /** Return source availability and aggregate coverage without changing either plugin.
     * @return array Source schema and bounded summary.
     */
    public static function inspect(): array {
        global $wpdb;$site=self::columns('koko_analytics_site_stats');$post=self::columns('koko_analytics_post_stats');$ref=self::columns('koko_analytics_referrer_stats');
        if(!in_array('pageviews',$site,true)||!in_array('date',$site,true)){return [];}
        $paths=in_array('path_id',$post,true)&&self::exists('koko_analytics_paths');$labels=self::exists('koko_analytics_referrer_labels');
        if(!in_array('pageviews',$post,true)||(!in_array('post_id',$post,true)&&!in_array('id',$post,true)) || !in_array($labels?'hits':'pageviews',$ref,true) || !self::exists($labels?'koko_analytics_referrer_labels':'koko_analytics_referrer_urls')){return [];}
        $summary=(array)$wpdb->get_row("SELECT MIN(date) first,MAX(date) last,COUNT(*) days,COALESCE(SUM(pageviews),0) pageviews FROM {$wpdb->prefix}koko_analytics_site_stats",ARRAY_A);
        return ['paths'=>$paths,'labels'=>$labels,'post_column'=>in_array('post_id',$post,true)?'post_id':'id','summary'=>$summary];
    }
    /** Determine the last importable day, excluding dates already measured by this plugin.
     * @return string Inclusive cutoff date, or today if this plugin has not measured yet.
     */
    public static function cutoff(): string {
        $first=(string)get_option('tbm_live_since','');return $first?(new \DateTimeImmutable($first,wp_timezone()))->modify('-1 day')->format('Y-m-d'):wp_date('Y-m-d');
    }
    /** Render source summary, limits, and protected start/continue controls. @return void */
    public static function panel(): void {
        echo '<section class="tbm-card"><h2>'.esc_html__('Import Koko Analytics statistics','tabulamea').'</h2>';$source=self::inspect();
        if(!$source){echo '<p>'.esc_html__('No supported Koko Analytics tables were found on this website. The source plugin does not need to be active.','tabulamea').'</p></section>';return;}
        if(Plugin::settings()['measurement']){echo '<p class="tbm-attention">'.esc_html__('Pause TabulaMea measurement under Measurement before importing. Enable it again after the import is complete.','tabulamea').'</p></section>';return;}
        global $wpdb;if($wpdb->get_var("SELECT 1 FROM {$wpdb->prefix}tbm_import_ledger WHERE source='statify' LIMIT 1")){echo '<p>'.esc_html__('Statify data has already been imported. Combining sources cannot reliably remove overlaps.','tabulamea').'</p></section>';return;}$s=$source['summary'];echo '<p>'.esc_html(sprintf(__('Source: %1$s to %2$s · %3$s pageviews. Import through %4$s.','tabulamea'),$s['first']??'–',$s['last']??'–',number_format_i18n($s['pageviews']),self::cutoff())).'</p><p>'.esc_html__('Imports daily pageviews, content counts and referrer domains. Visitor counts are not imported because their measurement differs. Dates already measured by TabulaMea are excluded to prevent overlapping totals. Koko tables remain unchanged.','tabulamea').'</p>';
        $eligible=(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(pageviews),0) FROM {$wpdb->prefix}koko_analytics_site_stats WHERE date<=%s",self::cutoff()));echo '<p>'.esc_html(sprintf(__('Eligible source pageviews: %1$s · Excluded overlapping pageviews: %2$s','tabulamea'),number_format_i18n($eligible),number_format_i18n(max(0,(int)$s['pageviews']-$eligible)))).'</p>';$state=get_option('tbm_import_state',[]);if($state){echo '<p role="status" data-tbm-import-phase="'.esc_attr($state['phase']).'">'.esc_html(sprintf(__('Import status: %1$s · processed rows: %2$d.','tabulamea'),(['site'=>__('Site totals','tabulamea'),'posts'=>__('Content totals','tabulamea'),'referrers'=>__('Referrer totals','tabulamea'),'done'=>__('Completed','tabulamea')][$state['phase']] ?? ''),(int)$state['processed'])).'</p>';}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tbm_statistics_import">';wp_nonce_field('tbm_statistics_import');
        if($state && $state['phase']!=='done'){echo '<input type="hidden" name="operation" value="continue">';submit_button(__('Continue import (next batch)','tabulamea'),'secondary','submit',false);echo ' <button class="button" type="button" data-tbm-auto-import data-stop="'.esc_attr__('Pause automatic import','tabulamea').'" data-done="'.esc_attr__('Import completed.','tabulamea').'" data-error="'.esc_attr__('Import paused. Reload this page and try again.','tabulamea').'">'.esc_html__('Continue automatically','tabulamea').'</button>';}
        else{echo '<input type="hidden" name="operation" value="start"><p><label><input type="checkbox" name="confirmed" value="1" required> '.esc_html__('I have a current database backup and have reviewed the source period above.','tabulamea').'</label></p>';submit_button(__('Import these statistics','tabulamea'),'secondary','submit',false);}
        echo '</form><p class="description">'.esc_html__('Import runs in small batches. Continue until it reports done. Repeating it adds only source increases already identified by the import ledger. Keep Koko collection paused during the import for a consistent source.','tabulamea').'</p></section>';
    }
    /** Process one protected import action and redirect to the result. @return void */
    public static function action(): void {
        if(!current_user_can('manage_options')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}check_admin_referer('tbm_statistics_import');
        if(Plugin::settings()['measurement']){wp_die(esc_html__('Pause measurement before importing.','tabulamea'),'',409);}
        $op=InterfaceTools::text($_POST,'operation');$state=get_option('tbm_import_state',[]);
        if($op==='start' && !empty($_POST['confirmed']) && self::inspect() && (!$state||$state['phase']==='done')){$state=['phase'=>'site','offset'=>0,'processed'=>0,'cutoff'=>self::cutoff(),'owner'=>get_current_user_id()];update_option('tbm_import_state',$state,false);}
        if(!$state || (int)$state['owner']!==get_current_user_id() || !in_array($op,['start','continue'],true)){wp_die(esc_html__('Invalid import state.','tabulamea'),'',400);}
        $ok=self::batch();wp_safe_redirect(add_query_arg('tbm_status',$ok?'ok':'error',InterfaceTools::settings_url('data')));exit;
    }
    /** Copy at most 250 source rows; offsets advance only after committed rows.
     * @return bool Whether the batch succeeded.
     */
    public static function batch(): bool {
        if(Plugin::settings()['measurement']){return false;}
        global $wpdb;if($wpdb->get_var("SELECT 1 FROM {$wpdb->prefix}tbm_import_ledger WHERE source='statify' LIMIT 1")){return false;}$lock='tbm_import_'.md5(DB_NAME.'|'.$wpdb->prefix);if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1){return false;}
        try{
            $state=get_option('tbm_import_state',[]);$source=self::inspect();if(!$source||!$state){return false;}if($state['phase']==='done'){return true;}
            $phase=$state['phase'];$prefix=$wpdb->prefix;$offset=(int)$state['offset'];$cutoff=min($state['cutoff'],self::cutoff());
            if($phase==='site'){$sql="SELECT date,pageviews FROM {$prefix}koko_analytics_site_stats WHERE date<=%s ORDER BY date LIMIT 250 OFFSET %d";}
            elseif($phase==='posts'){
                $col=$source['post_column'];$sql=$source['paths']?"SELECT s.date,s.$col post_id,s.path_id source_id,s.pageviews,p.path FROM {$prefix}koko_analytics_post_stats s LEFT JOIN {$prefix}koko_analytics_paths p ON p.id=s.path_id WHERE s.date<=%s ORDER BY s.date,s.path_id LIMIT 250 OFFSET %d":"SELECT date,$col post_id,$col source_id,pageviews FROM {$prefix}koko_analytics_post_stats WHERE date<=%s ORDER BY date,$col LIMIT 250 OFFSET %d";
            }elseif($phase==='referrers'){$table=$source['labels']?'referrer_labels':'referrer_urls';$col=$source['labels']?'value':'url';$hits=$source['labels']?'hits':'pageviews';$sql="SELECT s.date,s.id source_id,s.$hits pageviews,r.$col referrer FROM {$prefix}koko_analytics_referrer_stats s LEFT JOIN {$prefix}koko_analytics_$table r ON r.id=s.id WHERE s.date<=%s ORDER BY s.date,s.id LIMIT 250 OFFSET %d";}
            else{return false;}
            $rows=$wpdb->get_results($wpdb->prepare($sql,$cutoff,$offset),ARRAY_A);if($wpdb->last_error){return false;}
            foreach($rows as $row){if(!self::copy($phase,$row)){return false;}$state['offset']++;$state['processed']++;update_option('tbm_import_state',$state,false);}
            if(count($rows)<250){$state['phase']=['site'=>'posts','posts'=>'referrers','referrers'=>'done'][$phase];$state['offset']=0;update_option('tbm_import_state',$state,false);}
            update_option('tbm_revision',microtime(true),false);return true;
        }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    /** Atomically apply one positive source increment and its import ledger entry.
     * @param string $kind Fixed source group.
     * @param array $row Source aggregate, never executed as SQL.
     * @return bool Whether the row committed or was already imported.
     */
    private static function copy(string $kind,array $row): bool {
        global $wpdb;$date=$row['date'];$views=(int)$row['pageviews'];if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||$views<0){return false;}
        $key=hash('sha256',$date.'|'.($row['source_id']??''));if($wpdb->query('START TRANSACTION')===false){return false;}
        $previous=$wpdb->get_var($wpdb->prepare("SELECT pageviews FROM {$wpdb->prefix}tbm_import_ledger WHERE source='koko' AND kind=%s AND source_key=%s FOR UPDATE",$kind,$key));$delta=max(0,$views-(int)$previous);
        $result=0;
        if($delta>0){
            if($kind==='site'){$result=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_site_stats (date,pageviews) VALUES (%s,%d) ON DUPLICATE KEY UPDATE pageviews=pageviews+VALUES(pageviews)",$date,$delta));}
            elseif($kind==='posts'){
                $id=(int)$row['post_id'];$path=(string)($row['path']??'');if(!$path){$path=(string)wp_parse_url(get_permalink($id)?:'/',PHP_URL_PATH);}
                $path=(string)wp_parse_url($path,PHP_URL_PATH);$path=substr($path,0,768);if(!str_starts_with($path,'/')){$path='/'.$path;}
                $result=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_post_stats (date,post_id,path_hash,path,pageviews) VALUES (%s,%d,%s,%s,%d) ON DUPLICATE KEY UPDATE pageviews=pageviews+VALUES(pageviews)",$date,$id,hash('sha256',$id.'|'.$path),$path,$delta));
            }else{$domain=Measurement::domain((string)($row['referrer']??''));if($domain){$result=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_referrer_stats (date,domain,pageviews) VALUES (%s,%s,%d) ON DUPLICATE KEY UPDATE pageviews=pageviews+VALUES(pageviews)",$date,$domain,$delta));}}
        }
        if($result===false){$wpdb->query('ROLLBACK');return false;}
        $ledger=$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_import_ledger (source,kind,source_key,pageviews) VALUES ('koko',%s,%s,%d) ON DUPLICATE KEY UPDATE pageviews=GREATEST(pageviews,VALUES(pageviews))",$kind,$key,$views));
        if($ledger===false){$wpdb->query('ROLLBACK');return false;}return $wpdb->query('COMMIT')!==false;
    }
}
