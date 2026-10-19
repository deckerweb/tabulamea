<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Native command palette, Site Health and contextual editorial shortcuts. */
final class WordPressIntegration {
    /** Register integrations without changing WordPress permissions. @return void */
    public static function boot(): void {
        add_action('admin_enqueue_scripts',[self::class,'commands']);add_filter('site_status_tests',[self::class,'tests']);add_filter('debug_information',[self::class,'information']);add_action('add_meta_boxes',[self::class,'boxes']);add_action('admin_bar_menu',[self::class,'toolbar'],90);
    }
    /** Build a private report URL for one public content item.
     * @param int $id Content ID.
     * @return string Local admin URL.
     */
    public static function content_url(int $id): string {return add_query_arg(['page'=>'tabulamea-statistics','content'=>$id],admin_url('admin.php'));}
    /** Add discoverable native commands only for accessible destinations. @return void */
    public static function commands(): void {
        if(!wp_script_is('wp-commands','registered')){return;}$items=[];
        if(Plugin::allowed('view')){
            foreach(['tabulamea-statistics'=>__('Statistics overview','tabulamea'),'tabulamea-dimensions'=>__('Additional statistics','tabulamea'),'tabulamea-reports'=>__('Content rankings','tabulamea'),'tabulamea-search'=>__('Internal search','tabulamea')] as $page=>$label){$items[]=['name'=>$page,'label'=>'TabulaMea: '.$label,'url'=>admin_url('admin.php?page='.$page)];}
            foreach(['today','7_days','14_days','30_days','90_days','180_days'] as $period){$items[]=['name'=>$period,'label'=>'TabulaMea: '.ReportTools::periods()[$period],'url'=>add_query_arg('period',$period,admin_url('admin.php?page=tabulamea-statistics'))];}
            $id=absint($_GET['post']??0);if($id&&is_post_publicly_viewable($id)){$items[]=['name'=>'current','label'=>__('TabulaMea: Statistics for this content','tabulamea'),'url'=>self::content_url($id)];}
            foreach(array_slice((array)get_option('tbm_rankings',[]),0,100,true) as $id=>$rank){$items[]=['name'=>'ranking-'.$id,'label'=>'TabulaMea: '.$rank['title'],'url'=>add_query_arg('ranking',$id,admin_url('admin.php?page=tabulamea-reports')),'ranking'=>true];}
        }
        if(current_user_can('manage_options')){foreach(['overview','measurement','access','dashboard','collection','content','shortcodes','data'] as $tab){$labels=['overview'=>__('Measurement status and diagnostics','tabulamea'),'measurement'=>__('Measurement','tabulamea'),'access'=>__('Access','tabulamea'),'dashboard'=>__('Dashboard widgets','tabulamea'),'collection'=>__('Additional collection','tabulamea'),'content'=>__('Content lists','tabulamea'),'shortcodes'=>__('Shortcode generator and blocks','tabulamea'),'data'=>__('Import, export and data care','tabulamea')];$items[]=['name'=>'settings-'.$tab,'label'=>'TabulaMea: '.$labels[$tab],'url'=>InterfaceTools::settings_url($tab)];}$items[]=['name'=>'create','label'=>'TabulaMea: '.__('Create a ranking','tabulamea'),'url'=>admin_url('admin.php?page=tabulamea-reports&view=create')];}
        if(!$items){return;}wp_enqueue_script('tbm-commands',plugins_url('assets/commands.js',TBM_FILE),['wp-data','wp-commands','wp-element'],TBM_VERSION,true);wp_localize_script('tbm-commands','tbmCommands',['items'=>$items]);
    }
    /** Register lightweight status checks with native Site Health. 
     * @param array $tests Existing tests.
     * @return array Extended tests.
     */
    public static function tests(array $tests): array {$tests['direct']['tabulamea_storage']=['label'=>__('TabulaMea statistics storage','tabulamea'),'test'=>[self::class,'storage_test']];$tests['direct']['tabulamea_maintenance']=['label'=>__('TabulaMea maintenance','tabulamea'),'test'=>[self::class,'maintenance_test']];return $tests;}
    /** Return a consistent Site Health result.
     * @param string $test Test suffix.
     * @param bool $ok Whether evidence supports normal operation.
     * @param string $label Human-readable conclusion.
     * @param string $description Explanation without sensitive context.
     * @return array Native result.
     */
    private static function result(string $test,bool $ok,string $label,string $description): array {return ['test'=>'tabulamea_'.$test,'status'=>$ok?'good':'recommended','label'=>$label,'badge'=>['label'=>'TabulaMea','color'=>'blue'],'description'=>'<p>'.esc_html($description).'</p>','actions'=>'<a href="'.esc_url(InterfaceTools::settings_url('overview')).'">'.esc_html__('Review TabulaMea status','tabulamea').'</a>'];}
    /** Check actual storage structures, never create synthetic visitor records. @return array Native test result. */
    public static function storage_test(): array {
        global $wpdb;$ok=Plugin::ready();foreach(['site_stats','post_stats','referrer_stats','dimensions','receipts','rate','search_stats','import_ledger'] as $suffix){$row=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$wpdb->prefix.'tbm_'.$suffix));if(!$row||strcasecmp($row->Engine,'InnoDB')!==0){$ok=false;}if($row){$indexes=$wpdb->get_col("SHOW INDEX FROM {$wpdb->prefix}tbm_{$suffix}",2);if(!in_array('PRIMARY',$indexes,true)||($suffix==='post_stats'&&!in_array('post_date',$indexes,true))){$ok=false;}}}
        return self::result('storage',$ok,$ok?__('TabulaMea storage is available','tabulamea'):__('TabulaMea storage needs attention','tabulamea'),__('Checks the plugin schema and transactional tables. This does not prove that every browser can deliver pageviews. Use the guided frontend test for that.','tabulamea'));
    }
    /** Check scheduled work and optional local data only when they are required. @return array Native test result. */
    public static function maintenance_test(): array {
        $s=Plugin::settings();$next=wp_next_scheduled('tbm_maintenance');$ok=$next&&$next>time()-2*DAY_IN_SECONDS;if($s['country']&&(!is_readable(Tracking::geo_path())||($s['geo_download']&&(int)get_option('tbm_geo_updated',0)<time()-62*DAY_IN_SECONDS))){$ok=false;}
        $error=(array)get_option('tbm_core_error',[]);$capacity=($error['code']??'')==='capacity'&&(int)($error['occurrences']??0)>=3&&(int)($error['time']??0)>time()-10*MINUTE_IN_SECONDS;if($capacity){$ok=false;}if(($error['code']??'')==='storage'&&(int)($error['occurrences']??0)>=3&&(int)($error['time']??0)>time()-HOUR_IN_SECONDS&&(int)($error['time']??0)>(int)get_option('tbm_last_received',0)){$ok=false;}
        return self::result('maintenance',(bool)$ok,$ok?__('TabulaMea maintenance is configured','tabulamea'):($capacity?__('Review TabulaMea measurement capacity','tabulamea'):__('Review TabulaMea maintenance or country data','tabulamea')),($capacity?__('The anonymous request budget was reached repeatedly in the last ten minutes. Some deliveries were rejected. Review the configured limit and server capacity.','tabulamea'):__('Checks overdue scheduled work and required local country data. Low traffic, paused collection and disabled optional reports are not errors.','tabulamea')));
    }
    /** Add only operational facts to the native copyable information screen.
     * @param array $info Existing information sections.
     * @return array Extended information.
     */
    public static function information(array $info): array {
        $data=OverviewTools::diagnostic();$data['last_successful_maintenance']=(int)get_option('tbm_last_maintenance',0);$data['network_active']=is_multisite()&&is_plugin_active_for_network(plugin_basename(TBM_FILE));$data['search_enabled']=Plugin::settings()['search_enabled'];$summary=DataCare::summary();$data['approximate_storage_bytes']=$summary['bytes'];$data['retained_from']=$summary['first'];$data['retained_through']=$summary['last'];$data['country_database_month']=(string)get_option('tbm_geo_month','');$data['network_display_defaults']=!empty(NetworkSettings::defaults());$labels=['plugin_version'=>__('Plugin version','tabulamea'),'wordpress_version'=>__('WordPress version','tabulamea'),'php_version'=>__('PHP version','tabulamea'),'multisite'=>__('Multisite','tabulamea'),'storage_ready'=>__('Statistics storage','tabulamea'),'measurement_enabled'=>__('Measurement','tabulamea'),'last_received'=>__('Last accepted request','tabulamea'),'technology_enabled'=>__('Technology collection','tabulamea'),'country_enabled'=>__('Country collection','tabulamea'),'country_database_readable'=>__('Country database readable','tabulamea'),'maintenance_scheduled'=>__('Scheduled maintenance','tabulamea'),'retention_months'=>__('Additional retention in months','tabulamea'),'core_schema'=>__('Storage schema','tabulamea'),'request_limit'=>__('Maximum valid measurement requests per minute (site-wide)','tabulamea'),'recent_error'=>__('Recent operational status','tabulamea'),'last_successful_maintenance'=>__('Last successful maintenance','tabulamea'),'network_active'=>__('Network activation','tabulamea'),'search_enabled'=>__('Search collection','tabulamea'),'approximate_storage_bytes'=>__('Approximate storage in bytes','tabulamea'),'retained_from'=>__('Retained from','tabulamea'),'retained_through'=>__('Retained through','tabulamea'),'country_database_month'=>__('Country database month','tabulamea'),'network_display_defaults'=>__('Network display defaults','tabulamea')];$fields=[];foreach($data as $key=>$value){$debug=is_scalar($value)?(string)$value:wp_json_encode($value);$display=is_bool($value)?($value?__('Enabled','tabulamea'):__('Disabled','tabulamea')):$value;if(in_array($key,['last_received','last_successful_maintenance'],true)){$display=$value?wp_date(get_option('date_format').' H:i:s',(int)$value):__('Not yet recorded','tabulamea');}elseif($key==='recent_error'){$display=empty($value)?__('No recent error','tabulamea'):(($value['code']??'')==='capacity'?__('Request limit reached','tabulamea'):__('Storage error','tabulamea'));}$fields[$key]=['label'=>$labels[$key]??$key,'value'=>$display,'debug'=>$debug];}$info['tabulamea']=['label'=>'TabulaMea','description'=>__('Operational settings only; no visitor or content details.','tabulamea'),'fields'=>$fields];return $info;
    }
    /** Register an optional collapsed native editor box for public content. @return void */
    public static function boxes(): void {if(!Plugin::allowed('view')){return;}foreach(get_post_types(['public'=>true]) as $type){if($type!=='attachment'){add_meta_box('tbm-content-statistics','TabulaMea',[self::class,'box'],$type,'side','low');}}}
    /** Render counts only when the edited content is publicly viewable.
     * @param \WP_Post $post Edited content.
     * @return void
     */
    public static function box(\WP_Post $post): void {
        if(!Plugin::allowed('view')||!is_post_publicly_viewable($post)||$post->post_password!==''){echo '<p>'.esc_html__('Statistics are shown only for public content.','tabulamea').'</p>';return;}$range=Rankings::dates(['period'=>'30_days']);$total=(new Stats())->content_total($post->ID,$range);$prior=(new Stats())->content_total($post->ID,Rankings::previous($range));echo '<details><summary>'.esc_html__('Last 30 days','tabulamea').'</summary><p>'.esc_html(sprintf(__('Pageviews: %1$s · Previous period: %2$s','tabulamea'),number_format_i18n($total),number_format_i18n($prior))).'</p></details><p><a href="'.esc_url(self::content_url($post->ID)).'">'.esc_html__('Open content statistics','tabulamea').'</a></p>';
    }
    /** Add a contextual shortcut without exposing statistics to visitors.
     * @param \WP_Admin_Bar $bar Toolbar instance.
     * @return void
     */
    public static function toolbar(\WP_Admin_Bar $bar): void {if(!Plugin::allowed('view')||!is_singular()||!is_post_publicly_viewable(get_queried_object())||get_queried_object()->post_password!==''){return;}$bar->add_node(['id'=>'tabulamea','title'=>__('Content statistics','tabulamea'),'href'=>self::content_url(get_queried_object_id())]);}
}
