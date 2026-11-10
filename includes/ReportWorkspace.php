<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Personal report views and explicit, shareable transient report context. */
final class ReportWorkspace {
    /** @var string[] Supported private report routes. */
    private const PAGES=['tabulamea-statistics','tabulamea-dimensions','tabulamea-search'];
    /** Normalize a report view without storing permissions, nonces or content IDs.
     * @param array $input Scalar report fields.
     * @return array Safe bounded view definition.
     */
    public static function clean(array $input): array {
        $text=static fn($k,$default='')=>isset($input[$k])&&is_scalar($input[$k])?sanitize_text_field((string)$input[$k]):$default;
        $period=$text('period','30_days');$range=Rankings::dates(['period'=>$period,'year'=>(int)$text('year',wp_date('Y')),'start'=>$text('start'),'end'=>$text('end')]);
        if(!isset(ReportTools::periods()[$period])||!$range){$period='30_days';}
        $types=get_post_types(['public'=>true]);$type=$text('content_type','all');
        return ['period'=>$period,'year'=>max(2000,min((int)wp_date('Y'),(int)$text('year',wp_date('Y')))),'start'=>$period==='custom'?$range[0]:'','end'=>$period==='custom'?$range[1]:'','content_type'=>$type==='all'||(in_array($type,$types,true)&&$type!=='attachment')?$type:'all','content_search'=>mb_substr($text('content_search'),0,80),'items'=>in_array((int)$text('items','10'),[10,25,50],true)?(int)$text('items','10'):10,'chart'=>$text('chart')==='bars'?'bars':'line','compare'=>$text('compare')==='1'?'1':'','completed'=>$text('completed')==='1'?'1':'','resolution'=>in_array($text('resolution'),['day','week','month'],true)?$text('resolution'):'day'];
    }
    /** Read only this user's site-specific saved views. @return array Named definitions. */
    public static function views(): array {return array_slice((array)get_user_option('tbm_report_views'),0,20,true);}
    /** Apply an explicitly selected personal view to this request only. @return void */
    public static function hydrate(): void {
        if(!Plugin::allowed('view')||!in_array(InterfaceTools::text($_GET,'page'),self::PAGES,true)){return;}
        $id=sanitize_key(InterfaceTools::text($_GET,'saved_view'));$views=self::views();
        if($id!==''&&isset($views[$id]['filters'])&&is_array($views[$id]['filters'])){foreach(self::clean($views[$id]['filters']) as $key=>$value){$_GET[$key]=(string)$value;}}
    }
    /** Build report navigation carrying only a temporary period selection.
     * @param string $page Allowed report route.
     * @return string Private admin URL, with no credentials or nonces.
     */
    public static function url(string $page): string {
        $a=ReportTools::requested();$args=['page'=>$page];
        if(in_array($page,self::PAGES,true)){foreach(['period','year','start','end'] as $key){$args[$key]=$a[$key];}if(InterfaceTools::text($_GET,'completed')==='1'){$args['completed']='1';}}
        return add_query_arg($args,admin_url('admin.php'));
    }
    /** Render refresh, active filter chips and personal saved-view controls.
     * @param array $a Valid period definition.
     * @return void
     */
    public static function controls(array $a): void {
        if(!Plugin::allowed('view')){return;}
        $page=InterfaceTools::text($_GET,'page','tabulamea-statistics');if(!in_array($page,self::PAGES,true)){return;}
        $filters=self::clean(array_merge($a,$_GET));$args=array_merge($filters,['page'=>$page]);foreach(['content','focus_date'] as $key){$value=InterfaceTools::text($_GET,$key);if($value!==''){$args[$key]=$value;}}
        echo '<div class="tbm-workspace"><a class="button" href="'.esc_url(wp_nonce_url(add_query_arg($args,admin_url('admin.php')),'tbm_refresh','tbm_refresh')).'">'.esc_html__('Refresh this view','tabulamea').'</a> <span class="description">'.esc_html__('Page loaded:','tabulamea').' '.esc_html(wp_date(get_option('date_format').' '.get_option('time_format'))).' · '.esc_html__('Lists normally refresh within one minute; historical rankings within 15 minutes. During a rebuild, the previous result can remain visible briefly. Refresh requests a new result without resetting filters.','tabulamea').'</span></div>';
        $chips=['content_search'=>__('Title filter','tabulamea'),'content_type'=>__('Content type','tabulamea'),'focus_date'=>__('Selected day','tabulamea')];echo '<div class="tbm-filter-chips">';
        foreach($chips as $key=>$label){$value=InterfaceTools::text($_GET,$key);if($value===''||($key==='content_type'&&$value==='all')){continue;}$display=$key==='content_type'?(get_post_type_object($value)->labels->name??$value):($key==='focus_date'&&Rankings::dates(['period'=>'custom','start'=>$value,'end'=>$value])?StatisticsAdmin::date($value):$value);echo '<a class="button" href="'.esc_url(remove_query_arg([$key,'saved_view','tbm_refresh'])).'">'.esc_html($label.': '.$display).' <span aria-hidden="true">×</span><span class="screen-reader-text"> '.esc_html__('Remove filter','tabulamea').'</span></a>';}
        if(InterfaceTools::text($_GET,'content_search')!==''||InterfaceTools::text($_GET,'content_type','all')!=='all'||InterfaceTools::text($_GET,'focus_date')!==''){echo '<a href="'.esc_url(remove_query_arg(['content_search','content_type','focus_date','items','saved_view','tbm_refresh'])).'">'.esc_html__('Clear content filters; keep period','tabulamea').'</a>';}echo '</div>';
        echo '<details class="tbm-saved-views"><summary>'.esc_html__('My saved report views','tabulamea').'</summary><p>'.esc_html__('These views belong to you on this website. They do not change website defaults or publish a ranking.','tabulamea').'</p><ul>';
        foreach(self::views() as $id=>$view){if(!is_array($view)||!is_string($view['name']??null)){continue;}echo '<li><a href="'.esc_url(add_query_arg(['page'=>$page,'saved_view'=>$id],admin_url('admin.php'))).'">'.esc_html($view['name']).'</a> ';self::form('delete',array_merge($args,['view'=>$id]));echo '<button class="button-link">'.esc_html__('Delete this saved view','tabulamea').'</button></form></li>';}
        echo '</ul>';self::form('save',$args);InterfaceTools::input('view_name',__('Name this view','tabulamea'),'','text',['maxlength'=>60,'required'=>true]);submit_button(__('Save my report view','tabulamea'),'secondary','submit',false);echo '</form>';self::form('reset',['page'=>$page]);submit_button(__('Reset my report display','tabulamea'),'secondary','submit',false);echo '</form><p class="description">'.esc_html__('Resets your report period and chart style only. Saved views, widget choices, website settings and statistics are preserved.','tabulamea').'</p></details>';
    }
    /** Render a protected workspace form.
     * @param string $operation Fixed command.
     * @param array $fields Scalar values.
     * @return void
     */
    private static function form(string $operation,array $fields): void {
        echo '<form action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="tbm_workspace"><input type="hidden" name="operation" value="'.esc_attr($operation).'">';wp_nonce_field('tbm_workspace');foreach($fields as $key=>$value){echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr((string)$value).'">';}
    }
    /** Save only the current user's bounded views after capability and nonce checks. @return void */
    public static function action(): void {
        if(!Plugin::allowed('view')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}check_admin_referer('tbm_workspace');
        $op=InterfaceTools::text($_POST,'operation');$views=self::views();
        if($op==='save'){$name=mb_substr(InterfaceTools::text($_POST,'view_name'),0,60);if($name===''||count($views)>=20){wp_die(esc_html__('Use a name and keep at most 20 saved views.','tabulamea'),'',400);}$views[sanitize_key(wp_generate_uuid4())]=['name'=>$name,'filters'=>self::clean(wp_unslash($_POST))];update_user_option(get_current_user_id(),'tbm_report_views',$views,false);}
        elseif($op==='delete'){unset($views[sanitize_key(InterfaceTools::text($_POST,'view'))]);update_user_option(get_current_user_id(),'tbm_report_views',$views,false);}
        elseif($op==='reset'){$prefs=(array)get_user_option('tbm_preferences');unset($prefs['report_period'],$prefs['chart'],$prefs['resolution']);update_user_option(get_current_user_id(),'tbm_preferences',$prefs,false);}
        else{wp_die(esc_html__('Unknown action.','tabulamea'),'',400);}
        $page=InterfaceTools::text($_POST,'page','tabulamea-statistics');if(!in_array($page,self::PAGES,true)){$page='tabulamea-statistics';}$args=$op==='reset'?[]:self::clean(wp_unslash($_POST));$args['page']=$page;wp_safe_redirect(add_query_arg($args,admin_url('admin.php')));exit;
    }
}
