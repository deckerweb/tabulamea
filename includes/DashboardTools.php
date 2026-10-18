<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Compact dashboard composition and site-specific personal periods. */
final class DashboardTools {
    /** Render widget controls inside the site's Settings API form. @return void */
    public static function fields(): void {
        $s=Plugin::settings();$choices=[''=>__('Use the widget filters below','tabulamea')];foreach((array)get_option('tbm_rankings',[]) as $id=>$rank){$choices[$id]=$rank['title'];}
        InterfaceTools::select('tbm_settings[widget_ranking]',__('Saved ranking in the compact widget','tabulamea'),$choices,$s['widget_ranking']);
        echo '<p class="description">'.esc_html__('A selected saved ranking keeps its own filters, period and snapshot. Public publication is not required for this private widget.','tabulamea').'</p>';
        InterfaceTools::select('tbm_settings[widget_period]',__('Default widget period','tabulamea'),self::periods(),$s['widget_period']);
        InterfaceTools::input('tbm_settings[widget_limit]',__('Number of widget items (1–20)','tabulamea'),$s['widget_limit'],'number');
        $types=['all'=>__('All public content types','tabulamea')];foreach(get_post_types(['public'=>true],'objects') as $type){if($type->name!=='attachment'){$types[$type->name]=$type->labels->name;}}
        InterfaceTools::select('tbm_settings[widget_type]',__('Widget content type','tabulamea'),$types,$s['widget_type']);
        InterfaceTools::check('tbm_settings[widget_pageviews]',__('Show pageview total','tabulamea'),$s['widget_pageviews']);
        echo '<p class="description">'.esc_html__('TabulaMea counts pageviews without identifying individual visitors. Personal periods affect only the current user on this website.','tabulamea').'</p>';
    }
    /** Return non-custom rolling periods suitable for user preferences. @return array Period labels. */
    public static function periods(): array { return array_intersect_key(ReportTools::periods(),array_flip(['today','yesterday','7_days','14_days','30_days','90_days','180_days','365_days','month','previous_month','previous_year'])); }
    /** Render a private widget with no additional REST privileges. @return void */
    public static function render(): void {
        if(!Plugin::ready()||!Plugin::allowed('widget')){return;}$s=Plugin::settings();$prefs=(array)get_user_option('tbm_preferences');$saved=get_option('tbm_rankings',[])[$s['widget_ranking']] ?? null;
        $period=($s['widget_personal']?($prefs['widget_period']??$s['widget_period']):$s['widget_period']);if(!isset(self::periods()[$period])){$period='30_days';}
        $a=is_array($saved)?$saved:['type'=>$s['widget_type'],'period'=>$period,'limit'=>$s['widget_limit'],'counts'=>true];$range=$a['snapshot']['range'] ?? Rankings::dates($a);
        if(!$range){return;}$stats=(new Stats())->get_totals(...$range);
        echo '<p>'.esc_html(StatisticsAdmin::date($range[0]).' – '.StatisticsAdmin::date($range[1])).'</p>';
        $frozen=isset($a['snapshot']);
        if($frozen){echo '<p><strong>'.esc_html__('Saved snapshot','tabulamea').'</strong> · '.esc_html($a['snapshot']['date']).'</p>';}
        if($s['widget_pageviews']){
            if($frozen&&!array_key_exists('site_total',$a['snapshot'])){echo '<p>'.esc_html__('No site total was saved with this older snapshot.','tabulamea').'</p>';}
            else{$views=$frozen?(int)$a['snapshot']['site_total']:(int)($stats->pageviews ?? 0);echo '<p>'.esc_html($frozen?__('Saved site pageviews:','tabulamea'):__('Pageviews:','tabulamea')).' '.esc_html(number_format_i18n($views)).'</p>';}
        }
        if(is_array($saved)){echo '<h3>'.esc_html($saved['title']).'</h3>';}
        echo Rankings::render($a,$a['snapshot']['rows'] ?? null);
        if(!is_array($saved)&&$s['widget_personal']){echo '<details><summary>'.esc_html__('My widget period','tabulamea').'</summary>';InterfaceTools::form('preference',['area'=>'widget_period']);InterfaceTools::select('period',__('Period','tabulamea'),self::periods(),$period);submit_button(__('Save my display','tabulamea'),'secondary','submit',false);echo '</form></details>';}
        Navigation::links();
    }
    /** Render administrator-controlled limits for both widgets. @return void */
    public static function policy_fields(): void {
        $s=Plugin::settings();echo '<section class="tbm-card"><h3>'.esc_html__('General widget settings','tabulamea').'</h3>';
        InterfaceTools::check('tbm_settings[widget_personal]',__('Allow personal widget adjustments','tabulamea'),$s['widget_personal']);InterfaceTools::input('tbm_settings[widget_max]',__('Maximum personal widget items (1–20)','tabulamea'),$s['widget_max'],'number',['min'=>1,'max'=>20]);
        echo '<p class="description">'.esc_html__('Personal choices apply only to that user on this website. Turning them off restores website defaults without deleting saved preferences. Saved compact rankings keep their own definition.','tabulamea').'</p></section>';
    }
    /** Return module labels; pageviews remain mandatory. @return array Module keys and labels. */
    public static function modules(): array {return ['comparison'=>__('Period comparison','tabulamea'),'chart'=>__('Pageview chart','tabulamea'),'contents'=>__('Most viewed content','tabulamea'),'referrers'=>__('Referring websites','tabulamea')];}
    /** Render module checkboxes in a settings form.
     * @param string $name Form array name.
     * @param array $selected Enabled optional modules.
     * @param array $allowed Modules permitted by the administrator.
     * @return void
     */
    public static function module_fields(string $name,array $selected,array $allowed): void {
        echo '<input type="hidden" name="'.esc_attr($name).'[]" value=""><p><strong>'.esc_html__('Pageviews and period are always shown.','tabulamea').'</strong></p><fieldset><legend>'.esc_html__('Optional modules','tabulamea').'</legend>';
        foreach(self::modules() as $key=>$label){if(in_array($key,$allowed,true)){InterfaceTools::check($name.'[]',$label,in_array($key,$selected,true),$key);}}echo '</fieldset>';
    }
    /** Render the large widget site defaults and permitted modules. @return void */
    public static function overview_fields(): void {
        $s=Plugin::settings();echo '<section class="tbm-card"><h3>'.esc_html__('Large widget','tabulamea').'</h3>';Admin::checkbox('original_widget',__('Enable statistics overview widget','tabulamea'),$s);
        InterfaceTools::select('tbm_settings[overview_period]',__('Overview widget period','tabulamea'),self::periods(),$s['overview_period']);InterfaceTools::input('tbm_settings[overview_items]',__('Default overview items (1–10)','tabulamea'),$s['overview_items'],'number',['min'=>1,'max'=>10]);
        InterfaceTools::select('tbm_settings[chart_style]',__('Default chart style','tabulamea'),['line'=>__('Line','tabulamea'),'bars'=>__('Bars','tabulamea')],$s['chart_style']);
        echo '<h4>'.esc_html__('Default modules','tabulamea').'</h4>';self::module_fields('tbm_settings[overview_modules]',$s['overview_modules'],array_keys(self::modules()));
        echo '<details><summary>'.esc_html__('Modules users may choose','tabulamea').'</summary><p>'.esc_html__('Only these modules can be added in personal widget settings. Website defaults remain available.','tabulamea').'</p>';self::module_fields('tbm_settings[overview_allowed]',$s['overview_allowed'],array_keys(self::modules()));echo '</details></section>';
    }
    /** Render the rich overview widget with optional personal settings. @return void */
    public static function overview(): void {
        if(!Plugin::allowed('view')){return;}$s=Plugin::settings();$prefs=(array)get_user_option('tbm_preferences');$a=$s['widget_personal']?(array)($prefs['overview_widget']??[]):[];
        $period=isset(self::periods()[$a['period']??''])?$a['period']:$s['overview_period'];$items=max(1,min(10,$s['widget_personal']?(int)$s['widget_max']:10,(int)($a['items']??$s['overview_items'])));
        $modules=$s['overview_modules'];if($s['widget_personal']&&isset($a['modules'])&&is_array($a['modules'])){$modules=array_values(array_intersect($a['modules'],array_unique(array_merge($s['overview_allowed'],$s['overview_modules']))));}
        StatisticsAdmin::report(Rankings::dates(['period'=>$period]),true,$items,$modules);
        if($s['widget_personal']){echo '<details class="tbm-widget-settings"><summary>'.esc_html__('Customize my overview','tabulamea').'</summary>';InterfaceTools::form('preference',['area'=>'overview_widget']);InterfaceTools::select('period',__('Period','tabulamea'),self::periods(),$period);InterfaceTools::input('items',__('Number of items','tabulamea'),$items,'number',['min'=>1,'max'=>min(10,$s['widget_max'])]);self::module_fields('modules',$modules,array_unique(array_merge($s['overview_allowed'],$s['overview_modules'])));submit_button(__('Save my display','tabulamea'),'secondary','submit',false);submit_button(__('Reset to website defaults','tabulamea'),'secondary','reset_widget',false);echo '</form></details>';}else{echo '<p class="description">'.esc_html__('Display settings are managed by the website administrator.','tabulamea').'</p>';}
        Navigation::links();
    }
    /** Save a bounded personal widget preference after the command's nonce check.
     * @param string $area Widget preference key.
     * @return void
     */
    public static function save_preference(string $area): void {
        $s=Plugin::settings();if(!$s['widget_personal']||($area==='overview_widget'?!Plugin::allowed('view'):!Plugin::allowed('widget'))){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}
        $period=InterfaceTools::text($_POST,'period');if(!isset(self::periods()[$period])){wp_die(esc_html__('Choose a valid date range.','tabulamea'),'',400);}$items=max(1,min($s['widget_max'],$area==='overview_widget'?10:20,absint(InterfaceTools::text($_POST,'items','5'))));$prefs=(array)get_user_option('tbm_preferences');$prefs[$area]=['period'=>$period,'items'=>$items];if($area==='overview_widget'){$prefs[$area]['modules']=array_values(array_intersect(array_keys(self::modules()),array_unique(array_merge($s['overview_allowed'],$s['overview_modules'])),array_filter((array)($_POST['modules']??[]),'is_string')));}if(isset($_POST['reset_widget'])){unset($prefs[$area]);}update_user_option(get_current_user_id(),'tbm_preferences',$prefs,false);wp_safe_redirect(admin_url('index.php'));exit;
    }

}
