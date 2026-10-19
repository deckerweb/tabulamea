<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Read-only Statify row import into anonymous aggregates; implemented without Statify source code. */
final class StatifyImport {
    /** Register the protected batch action. @return void */
    public static function boot(): void {add_action('admin_post_tbm_statify_import',[self::class,'action']);}
    /** Inspect the known source layout and a bounded import horizon. @return array Source summary or empty. */
    public static function inspect(): array {
        $cached=get_transient('tbm_statify_source');if(is_array($cached)){return $cached;}global $wpdb;$table=$wpdb->prefix.'statify';if(!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))){return [];}$columns=$wpdb->get_col("SHOW COLUMNS FROM `$table`",0);if(array_diff(['id','created','target','referrer'],$columns)){return [];}$summary=(array)$wpdb->get_row("SELECT MIN(created) first,MAX(created) last,COUNT(*) rows_count,MAX(id) max_id FROM `$table`",ARRAY_A);if(!$wpdb->last_error){set_transient('tbm_statify_source',$summary,MINUTE_IN_SECONDS);}return $summary;
    }
    /** Detect another imported source to avoid unprovable cross-source deduplication. @return bool Conflict. */
    public static function conflict(): bool {global $wpdb;return (bool)$wpdb->get_var("SELECT 1 FROM {$wpdb->prefix}tbm_import_ledger WHERE source<>'statify' LIMIT 1");}
    /** Render a source preview and explicit backup confirmation. @return void */
    public static function panel(): void {
        if(!current_user_can('manage_options')){return;}echo '<section class="tbm-card"><h2>'.esc_html__('Import Statify statistics','tabulamea').'</h2>';$source=self::inspect();if(!$source){echo '<p>'.esc_html__('No supported Statify table was found on this website.','tabulamea').'</p></section>';return;}
        echo '<p>'.esc_html(sprintf(__('Source: %1$s to %2$s · %3$s records. Import through %4$s.','tabulamea'),$source['first']??'—',$source['last']??'—',number_format_i18n((int)$source['rows_count']),StatisticsImport::cutoff())).'</p><p>'.esc_html__('Each source row becomes one historical pageview. Public content is mapped by its current local URL where possible. Unmapped content contributes to overview totals only. Referrers are reduced to external domains; search strings and other query parameters are not retained.','tabulamea').'</p>';
        if(self::conflict()){echo '<p class="tbm-attention">'.esc_html__('Another statistics source has already been imported. Combining sources cannot reliably identify overlapping pageviews, so this import is unavailable on this website.','tabulamea').'</p></section>';return;}
        if(Plugin::settings()['measurement']){echo '<p>'.esc_html__('Pause measurement before importing. Also pause Statify collection and make a database backup.','tabulamea').'</p></section>';return;}
        $state=(array)get_option('tbm_statify_import',[]);if($state){echo '<p role="status">'.esc_html(sprintf(__('Processed source rows: %1$d · Mapped public contents: %2$d · Status: %3$s','tabulamea'),(int)$state['processed'],(int)$state['mapped'],!empty($state['done'])?__('Completed','tabulamea'):__('In progress','tabulamea'))).'</p>';}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tbm_statify_import">';wp_nonce_field('tbm_statify_import');echo '<input type="hidden" name="operation" value="'.($state&&empty($state['done'])?'continue':'start').'">';if(!$state||!empty($state['done'])){echo '<p><label><input type="checkbox" name="confirmed" required value="1"> '.esc_html__('I have a backup, reviewed the source and paused both collectors.','tabulamea').'</label></p>';}submit_button($state&&empty($state['done'])?__('Continue next batch','tabulamea'):__('Start reviewed import','tabulamea'),'secondary','submit',false);echo '</form><p class="description">'.esc_html__('The source is read only. Repeated imports skip known source IDs. Do not reset or replace the source table between imports. Source records at or after the first TabulaMea collection day are excluded.','tabulamea').'</p></section>';
    }
    /** Start or continue one owner-bound import after capability and nonce checks. @return void */
    public static function action(): void {
        if(!current_user_can('manage_options')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}check_admin_referer('tbm_statify_import');if(Plugin::settings()['measurement']||self::conflict()){wp_die(esc_html__('Pause measurement and review source compatibility first.','tabulamea'),'',409);}$op=InterfaceTools::text($_POST,'operation');$state=(array)get_option('tbm_statify_import',[]);
        if($op==='start'&&!empty($_POST['confirmed'])&&(!$state||!empty($state['done']))){$source=self::inspect();if(!$source){wp_die(esc_html__('No supported Statify table was found on this website.','tabulamea'),'',400);}$state=['cursor'=>0,'max_id'=>(int)$source['max_id'],'processed'=>0,'mapped'=>0,'done'=>false,'cutoff'=>StatisticsImport::cutoff(),'owner'=>get_current_user_id()];update_option('tbm_statify_import',$state,false);}
        if(!$state||(int)$state['owner']!==get_current_user_id()||!in_array($op,['start','continue'],true)){wp_die(esc_html__('Invalid import state.','tabulamea'),'',400);}$ok=self::batch();wp_safe_redirect(add_query_arg('tbm_status',$ok?'ok':'error',InterfaceTools::settings_url('data')));exit;
    }
    /** Import up to 100 source rows with a shared per-site import lock. @return bool Batch success. */
    public static function batch(): bool {
        if(Plugin::settings()['measurement']||self::conflict()){return false;}global $wpdb;$lock='tbm_import_'.md5(DB_NAME.'|'.$wpdb->prefix);if((int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock))!==1){return false;}
        try{if(self::conflict()){return false;}wp_cache_delete('tbm_statify_import','options');$state=(array)get_option('tbm_statify_import',[]);if(!$state){return false;}if($state['done']){return true;}$cutoff=min($state['cutoff'],StatisticsImport::cutoff());$rows=$wpdb->get_results($wpdb->prepare("SELECT id,created,target,referrer FROM {$wpdb->prefix}statify WHERE id>%d AND id<=%d AND created<=%s ORDER BY id LIMIT 100",$state['cursor'],$state['max_id'],$cutoff),ARRAY_A);if($wpdb->last_error){return false;}
            foreach($rows as $row){$mapped=self::copy($row);if($mapped===false){return false;}$state['cursor']=(int)$row['id'];$state['processed']++;$state['mapped']+=(int)$mapped;update_option('tbm_statify_import',$state,false);}if(count($rows)<100){$state['done']=true;update_option('tbm_statify_import',$state,false);}update_option('tbm_revision',microtime(true),false);delete_transient('tbm_storage_summary');return true;
        }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
    }
    /** Atomically import a source row and permanent source receipt.
     * @param array $row Original source fields, read only.
     * @return int|false One if mapped public content, zero otherwise, false on storage failure.
     */
    private static function copy(array $row) {
        global $wpdb;$date=substr((string)$row['created'],0,10);$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone());if(!$parsed||$parsed->format('Y-m-d')!==$date){return 0;}$target=(string)$row['target'];$url=str_starts_with($target,'/')?home_url($target):$target;$id=0;
        if(wp_parse_url($url,PHP_URL_HOST)===wp_parse_url(home_url(),PHP_URL_HOST)){$candidate=url_to_postid($url);$post=$candidate?get_post($candidate):null;if($post&&is_post_publicly_viewable($post)&&$post->post_password===''){$id=$candidate;}}
        $path=$id?(string)wp_parse_url(get_permalink($id),PHP_URL_PATH):'/__imported_overview__';$path=substr($path?:'/',0,768);$domain=Measurement::domain((string)$row['referrer']);if($domain===Measurement::domain(home_url())){$domain='';}$key=hash('sha256',(string)$row['id']);$mapped=0;
        $ok=CoreStore::import('statify','row',$key,1,static function(int $delta) use($date,$id,$path,$domain,&$mapped): bool {
            global $wpdb;
            if($wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_site_stats (date,pageviews) VALUES (%s,%d) ON DUPLICATE KEY UPDATE pageviews=pageviews+VALUES(pageviews)",$date,$delta))===false){return false;}
            if($wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_post_stats (date,post_id,path_hash,path,pageviews) VALUES (%s,%d,%s,%s,%d) ON DUPLICATE KEY UPDATE pageviews=pageviews+VALUES(pageviews)",$date,$id,hash('sha256',$id.'|'.$path),$path,$delta))===false){return false;}
            if($domain&&!in_array($domain,Plugin::settings()['referrer_blocklist'],true)&&$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}tbm_referrer_stats (date,domain,pageviews) VALUES (%s,%s,%d) ON DUPLICATE KEY UPDATE pageviews=pageviews+VALUES(pageviews)",$date,$domain,$delta))===false){return false;}
            $mapped=$id?1:0;return true;
        });
        return $ok?$mapped:false;
    }
}
