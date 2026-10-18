<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/**
 * Coordinates dependency guards, access policy and per-site lifecycle.
 */
final class Plugin {
    /**
     * Return supported integration status.
     * @return bool Whether the statistics store is available.
     */
    public static function ready(): bool {
        return get_option('tbm_schema') === '3';
    }
    /**
     * Read merged per-site settings.
     * @return array<string,mixed> Stored configuration with safe defaults.
     */
    public static function settings(): array {
        $settings = array_replace(self::defaults(), NetworkSettings::defaults(), (array)get_option('tbm_settings', []));
        // Retired host-plugin display flags must never override the visible access policy.
        unset($settings['full_dashboard']);
        return $settings;
    }
    /**
     * Return installation defaults without reading saved configuration.
     * @return array Default site configuration.
     */
    public static function defaults(): array {
        return ['view_roles'=>['administrator'], 'manage_roles'=>['administrator'], 'widget_roles'=>['administrator'], 'users'=>[], 'widget'=>true, 'original_widget'=>true,  'technology'=>false, 'country'=>false, 'geo_download'=>false, 'retention'=>3, 'delete_data'=>false, 'public_rankings'=>false,'exclude_ids'=>[],'exclude_front'=>false,'widget_period'=>'30_days','widget_limit'=>10,'widget_type'=>'all','widget_ranking'=>'','widget_pageviews'=>true,'widget_visitors'=>false,'measurement'=>true,'exclude_roles'=>['administrator','editor'],'core_retention'=>0,'referrer_blocklist'=>[],'column_types'=>[],'column_period'=>'30_days','widget_personal'=>true,'widget_max'=>10,'overview_modules'=>['comparison','chart','contents','referrers'],'overview_allowed'=>['comparison','chart','contents','referrers'],'overview_period'=>'7_days','overview_items'=>5,'request_limit'=>60000,'exclude_search_bots'=>true,'exclude_ai_bots'=>true,'measure_scope'=>'all','measure_types'=>['post','page'],'measure_archives'=>['home','posts','taxonomy','post_type','author','date','other'],'search_enabled'=>false,'search_topics'=>[],'chart_style'=>'line','public_counts'=>false];
    }
    /**
     * Register hooks without altering stored WordPress role capabilities.
     * @return void
     */
    public static function boot(): void {
        add_action('init', [self::class, 'init'],9);
        add_action('init', [OutputBlocks::class,'register'],9);
        add_filter('user_has_cap', [self::class, 'caps'], 20, 4);
        add_action('wp_dashboard_setup', [self::class, 'widget'], 100);
        add_action('admin_menu', [Admin::class, 'menu'], 100);
        add_action('admin_init', [Admin::class, 'register']);
        add_action('admin_post_tbm_tools', [ToolsActions::class,'dispatch']);
        add_action('admin_post_tbm_action', [Admin::class, 'action']);
        add_action('admin_enqueue_scripts', [Admin::class, 'assets']);
        add_filter('plugin_action_links_' . plugin_basename(TBM_FILE), [Admin::class, 'links']);
        Measurement::boot();
        MeasurementPolicy::boot();
        SearchStats::boot();
        WordPressIntegration::boot();
        Insights::boot();
        DataCare::boot();
        StatifyImport::boot();
        ContentColumns::boot();
        NetworkSettings::boot();
        add_action('admin_post_tbm_statistics_import', [StatisticsImport::class,'action']);
        add_action('tbm_maintenance', [Tracking::class, 'maintenance']);
        add_action('wp_initialize_site', [self::class, 'new_site'], 200);
    }
    /**
     * Load translations and lazily initialize newly activated sites.
     * @return void
     */
    public static function init(): void {
        load_plugin_textdomain('tabulamea', false, dirname(plugin_basename(TBM_FILE)) . '/languages');
        if (get_option('tbm_schema') !== '3') { self::install(); }
        if(self::ready()&&!wp_next_scheduled('tbm_maintenance')){wp_schedule_event(time()+3600,'daily','tbm_maintenance');}
        Rankings::register();


    }
    /**
     * Evaluate a user's site-specific access, with administrator recovery.
     * @param string $area view, manage or widget.
     * @param int $id User ID, zero for current.
     * @return bool Access decision.
     */
    public static function allowed(string $area = 'view', int $id = 0): bool {
        $u = $id ? get_userdata($id) : wp_get_current_user();
        if (!$u || !$u->ID) { return false; }
        if (is_multisite() && is_super_admin($u->ID)) { return true; }
        if (!is_user_member_of_blog($u->ID)) { return false; }
        if (in_array('administrator', $u->roles, true)) { return true; }
        $s = self::settings();
        $rule = $s['users'][$u->ID][$area] ?? 'inherit';
        if (in_array($rule, ['allow','deny'], true)) { return $rule === 'allow'; }
        return (bool)array_intersect($u->roles, (array)($s[$area . '_roles'] ?? []));
    }
    /**
     * Map plugin capabilities for all entry points, including REST.
     * @param array $all Effective caps.
     * @param array $required Required caps.
     * @param array $args Original check arguments.
     * @param \WP_User $user Checked user.
     * @return array Modified effective caps.
     */
    public static function caps(array $all, array $required, array $args, \WP_User $user): array {
        if (!self::ready() || !array_intersect($required,['view_tabulamea','manage_tabulamea'])) { return $all; }
        foreach (['view_tabulamea'=>'view', 'manage_tabulamea'=>'manage'] as $cap=>$area) {
            $all[$cap] = self::allowed($area, $user->ID);
        }
        return $all;
    }
    /**
     * Keep statistics private.
     * @param bool $allowed Existing public dashboard decision.
     * @return bool Safe public access decision.
     */
    public static function public_dashboard(bool $allowed): bool { return self::ready() ? false : $allowed; }
    /**
     * Configure dashboard widgets for the current user.
     * @return void
     */
    public static function widget(): void {
        if (!self::ready()) { return; }
        $s = self::settings();
        if($s['original_widget'] && self::allowed('view')) { wp_add_dashboard_widget('tbm-overview', 'TabulaMea', [DashboardTools::class,'overview']); }
        if ($s['widget'] && self::allowed('widget')) { wp_add_dashboard_widget('tbm-summary', __('Website statistics', 'tabulamea'), [Admin::class, 'summary']); }
    }
    /**
     * Initialize a single site's tables and recurring task.
     * @return void
     */
    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta("CREATE TABLE {$wpdb->prefix}tbm_dimensions (date date NOT NULL, dimension varchar(12) NOT NULL, value varchar(32) NOT NULL, pageviews bigint unsigned NOT NULL DEFAULT 0, PRIMARY KEY  (date,dimension,value)) ENGINE=InnoDB {$wpdb->get_charset_collate()};");
        Measurement::install();
        SearchStats::install();
        $complete=true;foreach(['dimensions','site_stats','post_stats','referrer_stats','import_ledger','receipts','rate','search_stats'] as $table){$name=$wpdb->prefix.'tbm_'.$table;$status=$wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name=%s',$name));if(!$status){$complete=false;continue;}if(strcasecmp($status->Engine,'InnoDB')!==0&&$wpdb->query("ALTER TABLE `$name` ENGINE=InnoDB")===false){$complete=false;}}
        if($complete){update_option('tbm_schema','3',false);}else{delete_option('tbm_schema');}
        if (!wp_next_scheduled('tbm_maintenance')) { wp_schedule_event(time()+3600, 'daily', 'tbm_maintenance'); }
    }
    /**
     * Initialize activation; network sites are initialized lazily.
     * @param bool $network Whether network activated.
     * @return void
     */
    public static function activate(bool $network = false): void { self::install(); }
    /**
     * Initialize an added site when this plugin is network active.
     * @param \WP_Site $site New site.
     * @return void
     */
    public static function new_site(\WP_Site $site): void {
        if(!function_exists('is_plugin_active_for_network')){require_once ABSPATH.'wp-admin/includes/plugin.php';}
        if (!is_plugin_active_for_network(plugin_basename(TBM_FILE))) { return; }
        switch_to_blog($site->blog_id); self::install(); restore_current_blog();
    }
    /**
     * Clear background tasks on every relevant site, preserving data.
     * @param bool $network Network deactivation.
     * @return void
     */
    public static function deactivate(bool $network = false): void {
        if ($network && is_multisite()) {
            $offset=0;
            do { $ids=get_sites(['fields'=>'ids','number'=>100,'offset'=>$offset]); foreach ($ids as $id) { switch_to_blog($id); wp_clear_scheduled_hook('tbm_maintenance'); restore_current_blog(); } $offset+=100; } while(count($ids)===100);
        } else { wp_clear_scheduled_hook('tbm_maintenance'); }
    }
}
