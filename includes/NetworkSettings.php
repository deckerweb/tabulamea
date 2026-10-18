<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Network defaults never merge statistics or replace explicit website choices. */
final class NetworkSettings {
    /** Register network administration without adding per-visitor work. @return void */
    public static function boot(): void {add_action('network_admin_menu',[self::class,'menu']);add_action('network_admin_edit_tbm_defaults',[self::class,'save']);}
    /** Return the small allowlist of centrally defaulted display settings. @return array Defaults keys. */
    private static function keys(): array {return ['widget','original_widget','widget_personal','widget_max','widget_period','overview_period','overview_items'];}
    /** Return validated network defaults for the current site's network. @return array Configuration patch. */
    public static function defaults(): array {return is_multisite()?array_intersect_key((array)get_network_option(get_current_network_id(),'tbm_network_defaults',[]),array_flip(self::keys())):[];}
    /** Register a settings page only in network administration. @return void */
    public static function menu(): void {add_submenu_page('settings.php','TabulaMea','TabulaMea','manage_network_options','tabulamea-network',[self::class,'page']);}
    /** Render explicitly scoped defaults with native form submission. @return void */
    public static function page(): void {
        if(!current_user_can('manage_network_options')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}$s=array_replace(Plugin::defaults(),self::defaults());
        echo '<div class="wrap tbm">';Settings::header();Settings::status();echo '<section class="tbm-panel"><h2>'.esc_html__('Network defaults','tabulamea').'</h2><p>'.esc_html__('Each website measures and stores its own statistics, including the main website. These are display defaults only. Website choices override network defaults; personal choices stay within website limits. Collection, access rights and external-download consent are configured separately on each website.','tabulamea').'</p><form method="post" action="'.esc_url(network_admin_url('edit.php?action=tbm_defaults')).'">';wp_nonce_field('tbm_network_defaults');
        foreach(['original_widget'=>__('Enable configurable overview widget','tabulamea'),'widget'=>__('Enable compact widget','tabulamea'),'widget_personal'=>__('Allow personal widget adjustments','tabulamea')] as $key=>$label){InterfaceTools::check('defaults['.$key.']',$label,$s[$key]);}
        InterfaceTools::input('defaults[widget_max]',__('Maximum personal widget items (1–20)','tabulamea'),$s['widget_max'],'number',['min'=>1,'max'=>20]);InterfaceTools::input('defaults[overview_items]',__('Default overview items (1–10)','tabulamea'),$s['overview_items'],'number',['min'=>1,'max'=>10]);
        foreach(['widget_period','overview_period'] as $key){InterfaceTools::select('defaults['.$key.']',$key==='widget_period'?__('Compact widget period','tabulamea'):__('Overview widget period','tabulamea'),DashboardTools::periods(),$s[$key]);}
        submit_button();echo '</form></section>';Admin::footer();echo '</div>';
    }
    /** Explain current network inheritance and offer a protected local reset. @return void */
    public static function inheritance(): void {
        if(!is_multisite()||!current_user_can('manage_options')){return;}$defaults=self::defaults();if(!$defaults){return;}$raw=(array)get_option('tbm_settings',[]);echo '<details class="tbm-card"><summary>'.esc_html__('Website and network display defaults','tabulamea').'</summary><p>'.esc_html__('Explicit website choices override network defaults. Personal widget preferences remain within the website limits.','tabulamea').'</p><ul>';$labels=['widget'=>__('Compact widget','tabulamea'),'original_widget'=>__('Overview widget','tabulamea'),'widget_personal'=>__('Personal adjustments','tabulamea'),'widget_max'=>__('Personal item limit','tabulamea'),'widget_period'=>__('Compact widget period','tabulamea'),'overview_period'=>__('Overview widget period','tabulamea'),'overview_items'=>__('Overview items','tabulamea')];foreach($defaults as $key=>$value){echo '<li>'.esc_html(($labels[$key]??$key).' · '.(array_key_exists($key,$raw)?__('Website override','tabulamea'):__('Network default','tabulamea'))).'</li>';}echo '</ul><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="tbm_insight"><input type="hidden" name="operation" value="inherit">';wp_nonce_field('tbm_insight_inherit');submit_button(__('Restore network display defaults on this website','tabulamea'),'secondary','submit',false);echo '</form></details>';
    }
    /** Save network-only defaults after capability and nonce checks. @return void */
    public static function save(): void {
        if(!current_user_can('manage_network_options')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}check_admin_referer('tbm_network_defaults');$a=wp_unslash($_POST['defaults']??[]);if(!is_array($a)){wp_die(esc_html__('Invalid input.','tabulamea'),'',400);}$s=[];
        foreach(['original_widget','widget','widget_personal'] as $key){$s[$key]=!empty($a[$key]);}$s['widget_max']=max(1,min(20,absint($a['widget_max']??10)));$s['overview_items']=max(1,min(10,absint($a['overview_items']??5)));
        foreach(['widget_period','overview_period'] as $key){$value=is_string($a[$key]??null)?$a[$key]:'';$s[$key]=isset(DashboardTools::periods()[$value])?$value:'7_days';}
        update_network_option(get_current_network_id(),'tbm_network_defaults',$s);wp_safe_redirect(add_query_arg('tbm_status','ok',network_admin_url('settings.php?page=tabulamea-network')));exit;
    }
}
