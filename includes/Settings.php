<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Accessible settings navigation and branded screen headers. */
final class Settings {
    /**
     * Render the single approved icon next to the title and slogan.
     * @param string $title Current page title.
     * @return void
     */
    public static function header(string $title='TabulaMea'): void {
        echo '<header class="tbm-header"><img src="'.esc_url(plugins_url('assets/brand/icon.svg',TBM_FILE)).'" width="64" height="64" alt=""><div><h1>'.esc_html($title).'</h1><p class="tbm-lead">'.esc_html__('Your content. Its impact. All in view.','tabulamea').'</p></div></header><hr class="wp-header-end">';
    }
    /**
     * Show one result notice outside the branded header.
     * @return void
     */
    public static function status(): void {
        if(isset($_GET['tbm_status']) && !($_GET['tbm_status']==='ok' && isset($_GET['settings-updated']))){echo '<div class="notice '.($_GET['tbm_status']==='ok'?'notice-success':'notice-error').'" role="'.($_GET['tbm_status']==='ok'?'status':'alert').'"><p>'.esc_html($_GET['tbm_status']==='ok' ? __('Action completed.','tabulamea') : __('The action could not be completed. Check the input or create a new preview if it expired or the target settings changed.','tabulamea')).'</p></div>';}
    }
    /**
     * Render one independently saved settings section.
     * @return void
     */
    public static function page(): void {
        if(!current_user_can('manage_options')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}
        $tabs=['overview'=>__('Overview','tabulamea'),'measurement'=>__('Measurement','tabulamea'),'access'=>__('Access','tabulamea'),'dashboard'=>__('Dashboard','tabulamea'),'collection'=>__('Additional collection','tabulamea'),'content'=>__('Content lists','tabulamea'),'shortcodes'=>__('Shortcodes & blocks','tabulamea'),'data'=>__('Data & transfer','tabulamea')];
        $tab=is_string($_GET['tab'] ?? null) ? sanitize_key(wp_unslash($_GET['tab'])) : 'overview';
        if(!isset($tabs[$tab])){$tab='overview';}
        $s=Plugin::settings();echo '<div class="wrap tbm">';self::header();self::status();
        if(!Plugin::ready()){echo '<div class="notice notice-warning"><p>'.esc_html__('The statistics store is unavailable. Check database permissions and reactivate the plugin.','tabulamea').'</p></div>';}
        Navigation::links();
        echo '<nav class="nav-tab-wrapper tbm-tabs" aria-label="'.esc_attr__('Settings sections','tabulamea').'">';
        foreach($tabs as $key=>$label){echo '<a class="nav-tab'.($tab===$key?' nav-tab-active':'').'" '.($tab===$key?'aria-current="page"':'').' href="'.esc_url(add_query_arg('tab',$key,admin_url('options-general.php?page=tabulamea'))).'">'.esc_html($label).'</a>';}
        echo '</nav>';echo '<section class="tbm-panel">';ConfigurationTools::preview();if($tab==='access'){AccessTools::search();}
        if(in_array($tab,['access','measurement','dashboard','collection','content','data'],true)){
            echo '<form action="options.php" method="post">';settings_fields('tbm');
            echo '<input type="hidden" name="tbm_settings[_section]" value="'.esc_attr($tab).'">';
        }
        if($tab==='overview'){
            echo '<h2>'.esc_html__('Overview','tabulamea').'</h2><p>'.esc_html__('Content rankings use retained pageviews for posts and pages. Additional statistics show independently collected country and technology totals.','tabulamea').'</p>';
            OverviewTools::render();DataCare::privacy();OverviewTools::diagnostic_panel();
        }
        if($tab==='measurement'){Measurement::fields();}
        if($tab==='content'){ContentColumns::fields();}
        if($tab==='access'){
        echo '<h2>'.esc_html__('Access and dashboard','tabulamea').'</h2><p>'.esc_html__('Administrators retain access for recovery. Personal rules override role rules. Settings and access policy can only be changed by website administrators.','tabulamea').'</p><div class="tbm-grid">';
        foreach(['view'=>__('View statistics','tabulamea'),'widget'=>__('View compact dashboard widget','tabulamea')] as $area=>$label){echo '<fieldset><legend><strong>'.esc_html($label).'</strong></legend>';foreach(wp_roles()->roles as $slug=>$role){echo '<p><label><input type="checkbox" name="tbm_settings['.esc_attr($area).'_roles][]" value="'.esc_attr($slug).'" '.checked(in_array($slug,$s[$area.'_roles'],true),true,false).'> '.esc_html(translate_user_role($role['name'])).'</label></p>';}echo '</fieldset>';}
        echo '</div><h3>'.esc_html__('Personal rules','tabulamea').'</h3><table class="widefat"><thead><tr><th>'.esc_html__('User','tabulamea').'</th><th>'.esc_html__('Statistics','tabulamea').'</th><th>'.esc_html__('Widget','tabulamea').'</th><th>'.esc_html__('Remove personal rule','tabulamea').'</th></tr></thead><tbody>';
        foreach($s['users'] as $id=>$rules){ $u=get_userdata($id); if(!$u || !is_user_member_of_blog($id)){continue;} echo '<tr><td>'.esc_html($u->display_name.' ('.$u->user_login.')').'</td>';foreach(['view','widget'] as $area){echo '<td><select aria-label="'.esc_attr($u->user_login.' '.$area).'" name="tbm_settings[users]['.(int)$id.']['.esc_attr($area).']">';foreach(['inherit'=>__('Use role rules','tabulamea'),'allow'=>__('Allow','tabulamea'),'deny'=>__('Deny','tabulamea')] as $value=>$label){echo '<option value="'.esc_attr($value).'" '.selected($rules[$area] ?? 'inherit',$value,false).'>'.esc_html($label).'</option>';}echo '</select></td>';}echo '<td><label><input type="checkbox" name="tbm_settings[remove_users]['.(int)$id.']" value="1"> '.esc_html__('Use role rules again','tabulamea').'</label></td></tr>';}
        echo '</tbody></table>';


            Admin::checkbox('public_rankings',__('Allow explicitly published rankings on the public website','tabulamea'),$s);Admin::checkbox('public_counts',__('Also allow exact pageview counts in public output','tabulamea'),$s);echo '<p class="description">'.esc_html__('Ranking order can be published without exact counts. Counters remain empty until exact counts are allowed.','tabulamea').'</p>';
        }
        if($tab==='dashboard'){
            echo '<h2>'.esc_html__('Dashboard widgets','tabulamea').'</h2><p>'.esc_html__('The overview widget follows statistics access. The compact widget uses its own access rules. Both retain WordPress screen options.','tabulamea').'</p>';
            DashboardTools::overview_fields();
            echo '<section class="tbm-card"><h3>'.esc_html__('Compact widget','tabulamea').'</h3>';Admin::checkbox('widget',__('Enable compact dashboard widget','tabulamea'),$s);DashboardTools::fields();echo '</section>';
            DashboardTools::policy_fields();

        }
        if($tab==='collection'){
        echo '<h2>'.esc_html__('Optional country and technology reports','tabulamea').'</h2><p>'.esc_html__('These reports count accepted pageviews from activation onward. No additional browser request is sent. They do not identify visitors and cannot reconstruct past country or technology data.','tabulamea').'</p>';
        Admin::checkbox('technology',__('Collect device, browser and operating system categories','tabulamea'),$s);Admin::checkbox('country',__('Collect country categories using a local GeoIP database','tabulamea'),$s);Admin::checkbox('geo_download',__('Allow downloading and monthly updating DB-IP Country Lite from download.db-ip.com','tabulamea'),$s);
        echo '<p>'.esc_html__('Only country and technology totals are stored, without IP addresses or full browser headers. Country lookup uses the direct connection address; reverse proxies require correct server configuration. Browser and device detection is approximate.','tabulamea').'</p><p><label>'.esc_html__('Keep dimension reports for months','tabulamea').' <input type="number" min="1" max="120" name="tbm_settings[retention]" value="'.(int)$s['retention'].'"></label></p>';

        }
        if($tab==='data'){
            echo '<h2>'.esc_html__('Data & transfer','tabulamea').'</h2>';
            Admin::checkbox('delete_data',__('Delete all TabulaMea settings, statistics, rankings and snapshots when uninstalling on this website. Source data belonging to other plugins is preserved.','tabulamea'),$s);
        }
        if(in_array($tab,['access','measurement','dashboard','collection','content','data'],true)){submit_button();echo '</form>';}
        if($tab==='access'){AccessTools::presets_form();}if($tab==='dashboard'){NetworkSettings::inheritance();}
        if($tab==='collection'){
        echo '<h2>'.esc_html__('Country database','tabulamea').'</h2><p>'.esc_html__('Database month:','tabulamea').' '.esc_html(get_option('tbm_geo_month',__('Not installed','tabulamea'))).'</p>';Admin::button('geo',__('Download or refresh country database','tabulamea'));
        echo '<p><a href="https://db-ip.com/">IP Geolocation by DB-IP</a> · <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a></p>';

        }
        if($tab==='data'){DataCare::panel();StatisticsImport::panel();StatifyImport::panel();ConfigurationTools::render();OverviewTools::diagnostic_panel();}
        if($tab==='shortcodes'){ShortcodeTools::render();}
        echo '</section>';Admin::footer();echo '</div>';
    }
}
