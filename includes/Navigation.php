<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Contextual navigation between the independent plugin's reports and settings. */
final class Navigation {
    /** Render navigation with the current destination and effective permissions.
     * @param bool $full Include the main statistics destination.
     * @return void
     */
    public static function links(bool $full=true): void {
        $links=[];if(Plugin::allowed('view')){if($full){$links['tabulamea-statistics']=[admin_url('admin.php?page=tabulamea-statistics'),__('Statistics overview','tabulamea')];}$links['tabulamea-dimensions']=[admin_url('admin.php?page=tabulamea-dimensions'),__('Additional statistics','tabulamea')];$links['tabulamea-reports']=[admin_url('admin.php?page=tabulamea-reports'),__('Content rankings','tabulamea')];$links['tabulamea-search']=[admin_url('admin.php?page=tabulamea-search'),__('Internal search','tabulamea')];}
        if(current_user_can('manage_options')){$links['tabulamea']=[InterfaceTools::settings_url(),__('Settings','tabulamea')];}
        $page=InterfaceTools::text($_GET,'page');echo '<nav class="tbm-shortcuts" aria-label="'.esc_attr__('Statistics shortcuts','tabulamea').'"><ul>';
        foreach($links as $slug=>[$url,$label]){echo '<li><a href="'.esc_url($url).'" class="tbm-nav-link'.($page===$slug?' is-current':'').'"'.($page===$slug?' aria-current="page"':'').'>'.esc_html($label).'</a></li>';}echo '</ul></nav>';
    }
}
