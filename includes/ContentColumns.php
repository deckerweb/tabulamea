<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Optional capability-aware, sortable pageview columns using one aggregate join per content list. */
final class ContentColumns {
    /** @var array<int,int> Counts for the current authorized content list. */
    private static array $counts=[];
    /** @var bool Whether the current list counts have been loaded. */
    private static bool $loaded=false;
    /** Register list-table integration after public content types have been registered. @return void */
    public static function boot(): void {add_action('init',[self::class,'register'],30);add_action('pre_get_posts',[self::class,'query']);add_filter('the_posts',[self::class,'load_counts'],20,2);add_filter('posts_clauses',[self::class,'clauses'],20,2);add_action('restrict_manage_posts',[self::class,'filters']);}
    /** Attach scoped columns for enabled public types, without modifying other list tables. @return void */
    public static function register(): void {
        foreach(self::types() as $type){add_filter('manage_'.$type.'_posts_columns',[self::class,'columns']);add_action('manage_'.$type.'_posts_custom_column',[self::class,'cell'],10,2);add_filter('manage_edit-'.$type.'_sortable_columns',[self::class,'sortable']);}
    }
    /** Return configured public post types supported by normal content lists. @return array Content type slugs. */
    public static function types(): array {return array_values(array_intersect(array_diff(get_post_types(['public'=>true]),['attachment']),Plugin::settings()['column_types']));}
    /** Add the column only for authorized statistics viewers.
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function columns(array $columns): array {if(Plugin::allowed('view')){$columns['tbm_views']=__('Pageviews','tabulamea').' · '.(ReportTools::periods()[self::period()]??__('Last 30 days','tabulamea'));}return $columns;}
    /** Advertise the custom sort key only for authorized viewers.
     * @param array $columns Existing sortable columns.
     * @return array Sortable columns.
     */
    public static function sortable(array $columns): array {if(Plugin::allowed('view')){$columns['tbm_views']='tbm_views';}return $columns;}
    /** Render counts obtained by the list query; missing records remain explicitly unknown.
     * @param string $column Requested column.
     * @param int $id Content identifier.
     * @return void
     */
    public static function cell(string $column,int $id): void {
        if($column!=='tbm_views'||!Plugin::allowed('view')){return;}$query=$GLOBALS['wp_query']??null;if(!self::$loaded&&$query instanceof \WP_Query&&$query->get('tbm_column')){self::load_counts((array)$query->posts,$query);}$views=self::$counts[$id]??null;
        echo $views===null?'<span title="'.esc_attr__('No recorded pageviews in this period. This is not proof of zero visits.','tabulamea').'">—</span>':'<strong>'.esc_html(number_format_i18n((int)$views)).'</strong>';
    }
    /** Load counts once for the displayed IDs, independently of WordPress post-object caching.
     * @param array $posts Posts returned by the list query.
     * @param \WP_Query $query Current query.
     * @return array Unchanged posts.
     */
    public static function load_counts(array $posts,\WP_Query $query): array {
        if(!$query->get('tbm_column')||!Plugin::allowed('view')){return $posts;}
        self::$counts=[];self::$loaded=true;$ids=array_values(array_filter(array_map(static fn($post)=>is_object($post)?(int)$post->ID:(int)$post,$posts)));if(!$ids){return $posts;}
        global $wpdb;$range=Rankings::dates(['period'=>self::period()]);foreach(array_chunk($ids,500) as $batch){$placeholders=implode(',',array_fill(0,count($batch),'%d'));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT post_id,SUM(pageviews) views FROM {$wpdb->prefix}tbm_post_stats WHERE date BETWEEN %s AND %s AND post_id IN ($placeholders) GROUP BY post_id",array_merge($range,$batch)));
        foreach($rows as $row){self::$counts[(int)$row->post_id]=(int)$row->views;}}return $posts;
    }
    /** Mark only the main authorized edit-list query for aggregation.
     * @param \WP_Query $query WordPress query.
     * @return void
     */
    public static function query(\WP_Query $query): void {
        if(!is_admin()||!$query->is_main_query()||!Plugin::allowed('view')||($GLOBALS['pagenow']??'')!=='edit.php'){return;}
        $type=$query->get('post_type')?:'post';if(!is_string($type)||!in_array($type,self::types(),true)){return;}self::$loaded=false;self::$counts=[];$query->set('tbm_column',true);
    }
    /** Join daily aggregates once, preserving native search, pagination and capability filters.
     * @param array $clauses SQL query clauses.
     * @param \WP_Query $query Current query.
     * @return array Modified SQL clauses.
     */
    public static function clauses(array $clauses,\WP_Query $query): array {
        if(!$query->get('tbm_column')||!Plugin::allowed('view')){return $clauses;}
        $filter=InterfaceTools::text($_GET,'tbm_view_filter');if($query->get('orderby')!=='tbm_views'&&!in_array($filter,['recorded','unrecorded'],true)){return $clauses;}global $wpdb;$range=Rankings::dates(['period'=>self::period()]);
        $join=$wpdb->prepare(" LEFT JOIN (SELECT post_id,SUM(pageviews) views FROM {$wpdb->prefix}tbm_post_stats WHERE date BETWEEN %s AND %s GROUP BY post_id) tbm_counts ON tbm_counts.post_id={$wpdb->posts}.ID ",$range);
        $clauses['join'].=$join;
        $filter=InterfaceTools::text($_GET,'tbm_view_filter');if($filter==='recorded'){$clauses['where'].=' AND tbm_counts.views IS NOT NULL';}elseif($filter==='unrecorded'){$clauses['where'].=' AND tbm_counts.views IS NULL';}
        if($query->get('orderby')==='tbm_views'){$direction=strtoupper((string)$query->get('order'))==='ASC'?'ASC':'DESC';$clauses['orderby']="COALESCE(tbm_counts.views,0) $direction,{$wpdb->posts}.ID ASC";}
        return $clauses;
    }
    /** Read a temporary validated list period without changing website defaults. @return string Period key. */
    public static function period(): string {$period=InterfaceTools::text($_GET,'tbm_column_period',Plugin::settings()['column_period']);return isset(DashboardTools::periods()[$period])?$period:'30_days';}
    /** Render a non-destructive native list filter.
     * @param string $type Current content type.
     * @return void
     */
    public static function filters(string $type): void {
        if(!Plugin::allowed('view')||!in_array($type,self::types(),true)){return;}
        echo '<label class="screen-reader-text" for="tbm-view-filter">'.esc_html__('Filter recorded pageviews','tabulamea').'</label><select name="tbm_view_filter" id="tbm-view-filter">';
        foreach([''=>__('All pageview states','tabulamea'),'recorded'=>__('With recorded pageviews','tabulamea'),'unrecorded'=>__('Without recorded pageviews','tabulamea')] as $key=>$label){echo '<option value="'.esc_attr($key).'" '.selected(InterfaceTools::text($_GET,'tbm_view_filter'),$key,false).'>'.esc_html($label).'</option>';}echo '</select><label class="screen-reader-text" for="tbm-column-period">'.esc_html__('Pageview period','tabulamea').'</label><select id="tbm-column-period" name="tbm_column_period">';foreach(DashboardTools::periods() as $key=>$label){echo '<option value="'.esc_attr($key).'" '.selected(self::period(),$key,false).'>'.esc_html($label).'</option>';}echo '</select> <a class="tbm-reset-views" href="'.esc_url(remove_query_arg(['tbm_view_filter','tbm_column_period'])).'">'.esc_html__('Reset view filters','tabulamea').'</a>';
    }
    /** Render administrator-only opt-in and the shared period for content columns. @return void */
    public static function fields(): void {
        $s=Plugin::settings();echo '<h2>'.esc_html__('Pageviews in content lists','tabulamea').'</h2><p>'.esc_html__('Enable a sortable Pageviews column for selected public content types. Only statistics viewers see it. Missing records are shown as a dash; they do not prove zero visits.','tabulamea').'</p>';
        foreach(get_post_types(['public'=>true],'objects') as $type){if($type->name!=='attachment'){InterfaceTools::check('tbm_settings[column_types][]',$type->labels->name,in_array($type->name,$s['column_types'],true),$type->name);}}
        InterfaceTools::select('tbm_settings[column_period]',__('Period for list-table counts','tabulamea'),DashboardTools::periods(),$s['column_period']);
    }
}
