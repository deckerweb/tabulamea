<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Explain effective access and provide administrator-only user and role tools. */
final class AccessTools {
    /**
     * Explain the source of a site-specific access decision.
     * @param int $id Member ID.
     * @param string $area Access area.
     * @return string Translated explanation.
     */
    public static function reason(int $id,string $area): string {
        if($area==='tools'){return __('WordPress website-administration capability','tabulamea');}
        $user=get_userdata($id);if(!$user){return __('User not found on this website.','tabulamea');}
        if((is_multisite()&&is_super_admin($id))||in_array('administrator',$user->roles,true)){return __('Administrator recovery access','tabulamea');}
        $rule=Plugin::settings()['users'][$id][$area] ?? 'inherit';
        return $rule==='allow'?__('Personal permission','tabulamea'):($rule==='deny'?__('Personal restriction','tabulamea'):__('Role rules','tabulamea'));
    }
    /**
     * Render a bounded native search with reusable selection links.
     * @return void
     */
    public static function search(): void {
        $search=mb_substr(InterfaceTools::text($_GET,'user_search'),0,80);
        echo '<section class="tbm-card"><h2>'.esc_html__('Find a user','tabulamea').'</h2><form method="get"><input type="hidden" name="page" value="tabulamea"><input type="hidden" name="tab" value="access">';
        InterfaceTools::input('user_search',__('Search by name, login or email','tabulamea'),$search);submit_button(__('Search users','tabulamea'),'secondary','submit',false);echo '</form>';
        if($search!==''){
            $users=get_users(['blog_id'=>get_current_blog_id(),'search'=>'*'.$search.'*','search_columns'=>['user_login','user_email','display_name'],'number'=>20,'orderby'=>'display_name']);
            echo '<ul>';foreach($users as $user){echo '<li><a href="'.esc_url(add_query_arg('inspect_user',$user->ID,InterfaceTools::settings_url('access'))).'">'.esc_html($user->display_name.' ('.$user->user_login.')').'</a></li>';}echo '</ul><p class="description">'.esc_html__('Up to 20 matches. Select a user to inspect permissions or add a personal rule.','tabulamea').'</p>';
        }echo '</section>';
        $id=absint(InterfaceTools::text($_GET,'inspect_user'));$user=$id?get_userdata($id):false;
        if(!$user || !is_user_member_of_blog($id)){return;}
        $s=Plugin::settings();echo '<section class="tbm-card"><h2>'.esc_html__('Effective access','tabulamea').': '.esc_html($user->display_name).'</h2><p>'.esc_html__('This checks permissions without signing in as the selected user. WordPress screen options may still hide a widget.','tabulamea').'</p><table class="widefat"><thead><tr><th>'.esc_html__('Area','tabulamea').'</th><th>'.esc_html__('Access','tabulamea').'</th><th>'.esc_html__('Reason','tabulamea').'</th></tr></thead><tbody>';
        $checks=[__('Full statistics','tabulamea')=>[Plugin::ready()&&Plugin::allowed('view',$id),'view'],__('Rankings, search and additional reports','tabulamea')=>[Plugin::ready()&&Plugin::allowed('view',$id),'view'],__('Statistics overview widget','tabulamea')=>[Plugin::ready()&&$s['original_widget']&&Plugin::allowed('view',$id),'view'],__('Compact widget','tabulamea')=>[Plugin::ready()&&$s['widget']&&Plugin::allowed('widget',$id),'widget'],__('Plugin settings and access policy','tabulamea')=>[user_can($id,'manage_options'),'tools']];
        foreach($checks as $label=>[$allow,$area]){echo '<tr><td>'.esc_html($label).'</td><td>'.esc_html($allow?__('Allowed','tabulamea'):__('Unavailable','tabulamea')).'</td><td>'.esc_html(!Plugin::ready()&&$area!=='tools'?__('Statistics storage is unavailable','tabulamea'):($allow?self::reason($id,$area):self::reason($id,$area).' · '.__('A website display switch or an access rule may restrict this area.','tabulamea'))).'</td></tr>';}
        echo '</tbody></table>';
        InterfaceTools::form('add_user',['user_id'=>$id]);submit_button(__('Add personal access rule','tabulamea'),'secondary','submit',false);echo '</form></section>';
    }
    /**
     * Return explicit role templates; administrators and personal overrides are preserved.
     * @return array Named settings patches.
     */
    public static function presets(): array {
        return ['administrators'=>['label'=>__('Administrators only','tabulamea'),'settings'=>['view_roles'=>['administrator'],'manage_roles'=>['administrator'],'widget_roles'=>['administrator']]],'editorial'=>['label'=>__('Editors may view statistics','tabulamea'),'settings'=>['view_roles'=>['administrator','editor'],'manage_roles'=>['administrator'],'widget_roles'=>['administrator','editor']]],'compact'=>['label'=>__('Editors see the compact widget only','tabulamea'),'settings'=>['view_roles'=>['administrator'],'manage_roles'=>['administrator'],'widget_roles'=>['administrator','editor']]]];
    }
    /** Render role-preset preview controls after the native settings form. @return void */
    public static function presets_form(): void {
        echo '<section class="tbm-card"><h2>'.esc_html__('Permission templates','tabulamea').'</h2><p>'.esc_html__('Templates change role rules. Existing personal rules keep their priority; administrator recovery access remains available. Review the changes before applying.','tabulamea').'</p>';
        InterfaceTools::form('access_preview');InterfaceTools::select('preset',__('Template','tabulamea'),array_map(static fn($p)=>$p['label'],self::presets()));submit_button(__('Preview changes','tabulamea'),'secondary','submit',false);echo '</form></section>';
    }
}
