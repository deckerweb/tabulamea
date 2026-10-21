<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Contextual report controls, honest coverage and bounded personal presentation settings. */
final class Insights {
    /** Register protected editorial preferences. @return void */
    public static function boot(): void {add_action('admin_post_tbm_insight',[self::class,'action']);add_action('wp_ajax_tbm_display',[self::class,'save_display']);}
    /** Resolve a viewable public content selection, never reveal private titles.
     * @return int Eligible selected ID or zero.
     */
    public static function content(): int {$id=absint(InterfaceTools::text($_GET,'content'));$post=$id?get_post($id):null;return $post&&is_post_publicly_viewable($post)&&$post->post_password===''?$id:0;}
    /** Read aggregate values with record-presence metadata for honest chart gaps.
     * @param array $range Inclusive dates.
     * @param int $id Optional public content ID.
     * @return array Values and known-record dates.
     */
    public static function series(array $range,int $id=0): array {
        global $wpdb;$monthly=Stats::monthly($range);$date=$monthly?"DATE_FORMAT(date,'%%Y-%%m-01')":'date';$table=$id?'post_stats':'site_stats';$filter=$id?$wpdb->prepare(' AND post_id=%d',$id):'';$sql="SELECT $date day,SUM(pageviews) views FROM {$wpdb->prefix}tbm_{$table} WHERE date BETWEEN %s AND %s $filter GROUP BY day ORDER BY day";$rows=$wpdb->get_results($wpdb->prepare($sql,$range),OBJECT_K);$values=Stats::calendar($range);foreach($values as $day=>$_){$values[$day]=(int)($rows[$day]->views??0);}return ['values'=>$values,'known'=>array_keys($rows)];
    }
    /** Return only known pause intervals from the bounded technical journal. @return array Inclusive local-date pairs. */
    public static function pauses(): array {$intervals=[];$start=null;foreach((array)get_option('tbm_changes',[]) as $entry){$day=wp_date('Y-m-d',(int)$entry['time']);if(empty($entry['active'])&&$start===null){$start=$day;}elseif(!empty($entry['active'])&&$start!==null){$intervals[]=[$start,$day];$start=null;}}if($start!==null){$intervals[]=[$start,wp_date('Y-m-d')];}return $intervals;}
    /** Render search, type, row-count and presentation controls without changing stored defaults.
     * @param array $a Current period definition.
     * @return void
     */
    public static function filters(array $a): void {
        $active=InterfaceTools::text($_GET,'content_search')!==''||InterfaceTools::text($_GET,'content_type','all')!=='all'||InterfaceTools::text($_GET,'items','10')!=='10';
        echo '<details class="tbm-report-options"'.($active?' open':'').'><summary>'.esc_html__('Filter content','tabulamea').($active?' · '.esc_html__('Filters active','tabulamea'):'').'</summary>';
        $types=['all'=>__('All public content types','tabulamea')];foreach(get_post_types(['public'=>true],'objects') as $type){if($type->name!=='attachment'){$types[$type->name]=$type->labels->name;}}
        echo '<form method="get" class="tbm-card tbm-period"><input type="hidden" name="page" value="tabulamea-statistics">';foreach($a as $key=>$value){echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr((string)$value).'">';}foreach(['content','chart','compare'] as $key){if(InterfaceTools::text($_GET,$key)!==''){echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr(InterfaceTools::text($_GET,$key)).'">';}}
        InterfaceTools::input('content_search',__('Find content by title','tabulamea'),InterfaceTools::text($_GET,'content_search'));InterfaceTools::select('content_type',__('Content type','tabulamea'),$types,InterfaceTools::text($_GET,'content_type','all'));InterfaceTools::select('items',__('Rows per list','tabulamea'),[10=>'10',25=>'25',50=>'50'],absint(InterfaceTools::text($_GET,'items','10')));submit_button(__('Apply','tabulamea'),'secondary','submit',false);echo '</form></details>';
        if($active){echo '<p class="tbm-filter-state">'.esc_html__('Content filters active','tabulamea').' <a href="'.esc_url(remove_query_arg(['content_search','content_type','items'])).'">'.esc_html__('Reset filters','tabulamea').'</a></p>';}
    }
    /** Render always-visible chart controls without mixing editorial list filters. @return void */
    public static function chart_controls(): void {
        if(is_admin()&&get_current_screen()&&get_current_screen()->id==='dashboard'){return;}
        $prefs=(array)get_user_option('tbm_preferences');echo '<form method="get" class="tbm-chart-controls" data-tbm-display data-endpoint="'.esc_url(admin_url('admin-ajax.php')).'" data-nonce="'.esc_attr(wp_create_nonce('tbm_display')).'">';
        $keys=['page','period','year','start','end','content','content_search','content_type','items','focus_date'];foreach($keys as $key){$value=InterfaceTools::text($_GET,$key,$key==='page'?'tabulamea-statistics':'');if($value!==''){echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">';}}
        InterfaceTools::select('chart',__('Display','tabulamea'),['line'=>__('Line','tabulamea'),'bars'=>__('Bars','tabulamea')],InterfaceTools::text($_GET,'chart',$prefs['chart']??Plugin::settings()['chart_style']));InterfaceTools::check('compare',__('Compare previous period','tabulamea'),!empty($_GET['compare']));submit_button(__('Apply','tabulamea'),'secondary','submit',false);echo '<span class="description">'.esc_html__('Chart style is remembered for you on this website.','tabulamea').'</span><span role="status" data-tbm-display-status></span></form>';
    }
    /** Save a personal chart style via an authenticated, nonce-protected request. @return void */
    public static function save_display(): void {
        check_ajax_referer('tbm_display');if(!Plugin::allowed('view')){wp_send_json_error([],403);}$chart=InterfaceTools::text($_POST,'chart');if(!in_array($chart,['line','bars'],true)){wp_send_json_error([],400);}$prefs=(array)get_user_option('tbm_preferences');$prefs['chart']=$chart;update_user_option(get_current_user_id(),'tbm_preferences',$prefs,false);wp_send_json_success();

    }
    /** Render chronological navigation for the exact displayed interval.
     * @param string $page Registered admin slug.
     * @param array $a Selected period definition.
     * @return void
     */
    public static function navigate(string $page,array $a): void {
        $range=Rankings::dates($a);if(!$range){return;}$first=new \DateTimeImmutable($range[0],wp_timezone());$last=new \DateTimeImmutable($range[1],wp_timezone());$days=(int)$first->diff($last)->days+1;
        echo '<nav class="tbm-subnav" aria-label="'.esc_attr__('Browse periods','tabulamea').'">';foreach([-1=>__('Previous period','tabulamea'),1=>__('Next period','tabulamea')] as $direction=>$label){$shift=$direction*$days;$start=$first->modify(($shift>=0?'+':'').$shift.' days')->format('Y-m-d');$end=$last->modify(($shift>=0?'+':'').$shift.' days')->format('Y-m-d');if(in_array($a['period'],['month','previous_month'],true)){$month=$first->modify('first day of this month')->modify(($direction>0?'+1':'-1').' month');$start=$month->format('Y-m-d');$end=$month->modify('last day of this month')->format('Y-m-d');}elseif(in_array($a['period'],['year','previous_year'],true)){$year=(int)$first->format('Y')+$direction;$start=$year.'-01-01';$end=$year.'-12-31';}if($start<'2000-01-01'||$end>wp_date('Y').'-12-31'){continue;}$params=['page'=>$page,'period'=>'custom','start'=>$start,'end'=>$end];$id=self::content();if($id){$params['content']=$id;}echo '<a href="'.esc_url(add_query_arg($params,admin_url('admin.php'))).'">'.esc_html($label).'</a>';}echo '</nav>';
    }
    /** Explain known collection changes and partial comparison semantics.
     * @param array $range Inclusive interval.
     * @return void
     */
    public static function coverage(array $range): void {
        echo '<section class="tbm-card"><h2>'.esc_html__('Coverage and interpretation','tabulamea').'</h2><p>'.esc_html__('A missing record is not proof of zero visits. Only known setting changes can explain pauses; historical outages cannot be reconstructed. The current day is incomplete. Comparisons use the preceding interval with the same number of calendar days.','tabulamea').'</p>';
        if(Plugin::settings()['measure_scope']==='selected'){echo '<p class="tbm-attention">'.esc_html__('Current collection covers only the selected part of this website. Historical totals can include earlier collection scopes.','tabulamea').'</p>';}
        ReportTools::explain($range);$history=array_values(array_filter((array)get_option('tbm_changes',[]),static function($entry)use($range){$date=wp_date('Y-m-d',(int)$entry['time']);return $date>=$range[0]&&$date<=$range[1];}));if(!$history){echo '<p class="description">'.esc_html__('No collection setting changes are recorded for this period.','tabulamea').'</p></section>';return;}echo '<details><summary>'.esc_html__('Technical collection changes','tabulamea').'</summary><ul>';foreach(array_reverse($history) as $entry){$day=wp_date('Y-m-d',$entry['time']);if($day<$range[0]||$day>$range[1]){continue;}$labels=['measurement'=>__('Measurement','tabulamea'),'exclude_roles'=>__('Excluded roles','tabulamea'),'measure_scope'=>__('Collection scope','tabulamea'),'measure_types'=>__('Content types','tabulamea'),'measure_archives'=>__('Archive types','tabulamea'),'exclude_search_bots'=>__('Search-engine bot filter','tabulamea'),'exclude_ai_bots'=>__('AI bot filter','tabulamea'),'technology'=>__('Technology collection','tabulamea'),'country'=>__('Country collection','tabulamea'),'search_enabled'=>__('Search collection','tabulamea'),'core_retention'=>__('Retention','tabulamea')];echo '<li>'.esc_html(StatisticsAdmin::date($day).' · '.implode(', ',array_map(static fn($key)=>$labels[$key]??$key,$entry['keys'])).' · '.($entry['active']?__('Measurement enabled','tabulamea'):__('Measurement paused','tabulamea'))).'</li>';}echo '</ul></details></section>';
    }
    /** Render the dry-run status and a fresh protected frontend test link. @return void */
    public static function test_panel(): void {
        if(!current_user_can('manage_options')){return;}$last=(int)get_option('tbm_test_received',0);echo '<section class="tbm-card" id="tbm-function-test"><h2>'.esc_html__('Guided measurement test','tabulamea').'</h2><p>'.esc_html__('Open the public homepage using this link, then return here and reload. The signed test bypasses your role exclusion but does not add a pageview. Measurement must be enabled. A successful test proves this browser delivery only.','tabulamea').'</p><p><a class="button" target="_blank" rel="noopener" href="'.esc_url(add_query_arg('tbm_test',wp_create_nonce('tbm_measure_test'),home_url('/'))).'">'.esc_html__('Open frontend test','tabulamea').'</a></p><p>'.esc_html($last?sprintf(
/* translators: %s: localized date and time of the last successful test. */
__('Last successful test: %s','tabulamea'),wp_date(get_option('date_format').' H:i:s',$last)):__('No test received yet.','tabulamea')).'</p></section>';
    }
    /** Explain aggregate composition without exposing missing or private content.
     * @param array $range Inclusive dates.
     * @return void
     */
    public static function composition(array $range): void {global $wpdb;$rows=$wpdb->get_results($wpdb->prepare("SELECT IF(s.post_id=0,'archives',IF(p.ID IS NULL OR p.post_status<>'publish','unavailable',p.post_type)) category,SUM(s.pageviews) views FROM {$wpdb->prefix}tbm_post_stats s LEFT JOIN {$wpdb->posts} p ON p.ID=s.post_id WHERE s.date BETWEEN %s AND %s GROUP BY category",$range),ARRAY_A);echo '<details class="tbm-card"><summary>'.esc_html__('How pageviews are distributed','tabulamea').'</summary><ul>';foreach($rows as $row){$type=get_post_type_object($row['category']);$label=$type?$type->labels->name:($row['category']==='archives'?__('Archives and overview pages','tabulamea'):__('Unavailable or no longer public content','tabulamea'));echo '<li>'.esc_html($label).' · '.esc_html(number_format_i18n((int)$row['views'])).'</li>';}echo '</ul><p>'.esc_html__('Content-list shares refer to all recorded website pageviews in the same period, including archives and content outside the list.','tabulamea').'</p></details>';}
    /** Return a local friendly domain label without loading external icons.
     * @param string $domain Recorded hostname.
     * @return string Human-readable label and retained hostname.
     */
    public static function referrer_name(string $domain): string {$names=['google.com'=>'Google','bing.com'=>'Bing','duckduckgo.com'=>'DuckDuckGo','wordpress.org'=>'WordPress.org','chatgpt.com'=>'ChatGPT','perplexity.ai'=>'Perplexity'];return isset($names[$domain])?$names[$domain].' · '.$domain:$domain;}
    /** Save protected preferences or restore explicit local network overrides. @return void */
    public static function action(): void {
        $op=InterfaceTools::text($_POST,'operation');check_admin_referer('tbm_insight_'.$op);if(!Plugin::allowed('view')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}$prefs=(array)get_user_option('tbm_preferences');$to=admin_url('admin.php?page=tabulamea-statistics');
        if($op==='display'){$prefs['chart']=InterfaceTools::text($_POST,'chart')==='bars'?'bars':'line';}
        elseif($op==='favorite'){$id=sanitize_key(InterfaceTools::text($_POST,'id'));$ranks=(array)get_option('tbm_rankings',[]);if(!isset($ranks[$id])){wp_die(esc_html__('Invalid ranking.','tabulamea'),'',400);}$favorites=(array)($prefs['favorites']??[]);$prefs['favorites']=in_array($id,$favorites,true)?array_values(array_diff($favorites,[$id])):array_slice(array_merge($favorites,[$id]),0,100);$to=admin_url('admin.php?page=tabulamea-reports');}
        elseif($op==='inherit'&&current_user_can('manage_options')&&is_multisite()){$s=(array)get_option('tbm_settings',[]);foreach(array_keys(NetworkSettings::defaults()) as $key){unset($s[$key]);}$s['_inherit_network']=true;update_option('tbm_settings',$s,false);$to=InterfaceTools::settings_url('dashboard');}
        else{wp_die(esc_html__('Unknown action.','tabulamea'),'',400);}update_user_option(get_current_user_id(),'tbm_preferences',$prefs,false);wp_safe_redirect($to);exit;
    }
}
