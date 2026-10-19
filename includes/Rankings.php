<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Queries public content aggregates and renders explicitly published rankings. */
final class Rankings {
    /** Register the public shortcode and the dynamic block. @return void */
    public static function register(): void {
        wp_register_style('tbm-ranking',plugins_url('assets/rankings.css',TBM_FILE),[],TBM_VERSION);add_shortcode('tabulamea_ranking',[self::class,'shortcode']);
        add_shortcode('tabulamea_counter',[self::class,'counter']);
        add_shortcode('tabulamea_popular',[self::class,'popular']);
        wp_register_script('tbm-block',plugins_url('assets/block.js',TBM_FILE),['wp-blocks','wp-element','wp-components','wp-block-editor','wp-i18n','wp-server-side-render','wp-data'],TBM_VERSION,true);
        wp_set_script_translations('tbm-block','tabulamea',dirname(TBM_FILE).'/languages');
        add_action('enqueue_block_editor_assets',[self::class,'editor_options']);
        register_block_type('tabulamea/ranking',['api_version'=>3,'editor_script'=>'tbm-block','editor_style'=>'tbm-ranking','style'=>'tbm-ranking','attributes'=>['id'=>['type'=>'string','default'=>'']],'render_callback'=>[self::class,'shortcode']]);
        register_block_type('deckerweb/tabulamea-ranking',['api_version'=>3,'supports'=>['inserter'=>false],'editor_script'=>'tbm-block','editor_style'=>'tbm-ranking','style'=>'tbm-ranking','attributes'=>['id'=>['type'=>'string','default'=>'']],'render_callback'=>[self::class,'shortcode']]);
    }
    /** Provide published definitions and meaningful preview metadata to editors. @return void */
    public static function editor_options(): void {
        $options=[];$previews=[];
        foreach((array)get_option('tbm_rankings',[]) as $id=>$rank){
            if(empty($rank['public'])){continue;}
            $options[]=['label'=>$rank['title'].' ('.$id.')','value'=>(string)$id];
            $range=$rank['snapshot']['range'] ?? self::dates($rank);
            $previews[$id]=['title'=>$rank['title'],'period'=>implode(' – ',$range),'status'=>Plugin::settings()['public_rankings']?__('Public output enabled','tabulamea'):__('Public output is disabled in Plugin settings.','tabulamea'),'snapshot'=>isset($rank['snapshot'])?__('Saved snapshot','tabulamea'):__('Live ranking','tabulamea')];
        }
        wp_localize_script('tbm-block','tbmRankingChoices',['enabled'=>(bool)Plugin::settings()['public_rankings'],'options'=>$options,'previews'=>$previews,'label'=>__('Saved ranking','tabulamea'),'choose'=>__('Choose a ranking','tabulamea'),'help'=>__('Choose a published ranking. Its filters and appearance are managed on the Content rankings page.','tabulamea'),'missing'=>__('No published ranking selected. Create and publish a ranking in Plugin settings first.','tabulamea')]);
    }
    /**
     * Resolve inclusive dates in the website timezone; evergreen uses complete calendar months.
     * @param array $a Definition.
     * @return array Two ISO dates, or empty for invalid input.
     */
    public static function dates(array $a): array {
        $now=new \DateTimeImmutable('today',wp_timezone());$period=$a['period'] ?? '30_days';
        if(($a['mode'] ?? '')==='evergreen'){$months=max(2,min(12,(int)($a['months'] ?? 3)));$end=$now->modify('first day of this month')->modify('-1 day');return [$now->modify('first day of this month')->modify('-'.$months.' months')->format('Y-m-d'),$end->format('Y-m-d')];}
        if($period==='today'){return [$now->format('Y-m-d'),$now->format('Y-m-d')];}
        if($period==='yesterday'){$date=$now->modify('-1 day')->format('Y-m-d');return [$date,$date];}
        if($period==='month'){return [$now->modify('first day of this month')->format('Y-m-d'),$now->format('Y-m-d')];}
        if($period==='previous_month'){return [$now->modify('first day of previous month')->format('Y-m-d'),$now->modify('first day of this month')->modify('-1 day')->format('Y-m-d')];}
        if($period==='year'){$year=(int)($a['year'] ?? $now->format('Y'));return $year>=2000 && $year<=(int)$now->format('Y')?["$year-01-01",min("$year-12-31",$now->format('Y-m-d'))]:[];}
        if($period==='previous_year'){$year=(int)$now->format('Y')-1;return ["$year-01-01","$year-12-31"];}
        if($period==='custom'){
            $start=$a['start'] ?? '';$end=$a['end'] ?? '';
            foreach([$start,$end] as $date){if(!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!(($parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date,wp_timezone()))&&$parsed->format('Y-m-d')===$date)){return [];}}
            return $start<=$end && $start>='2000-01-01' && $end<=$now->format('Y').'-12-31'?[$start,$end]:[];
        }
        $days=['7_days'=>7,'14_days'=>14,'30_days'=>30,'90_days'=>90,'180_days'=>180,'365_days'=>365][$period] ?? 30;
        return [$now->modify('-'.($days-1).' days')->format('Y-m-d'),$now->format('Y-m-d')];
    }
    /**
     * Return the preceding interval with the same inclusive day count.
     * @param array $range Two ISO dates.
     * @return array Two ISO dates.
     */
    public static function previous(array $range): array {
        $start=new \DateTimeImmutable($range[0],wp_timezone());$end=new \DateTimeImmutable($range[1],wp_timezone());$days=(int)$start->diff($end)->days+1;
        return [$start->modify('-'.$days.' days')->format('Y-m-d'),$start->modify('-1 day')->format('Y-m-d')];
    }
    /**
     * Normalize an untrusted ranking, preserving backwards-compatible defaults.
     * @param array $a Submitted definition.
     * @return array Safe definition without snapshot data.
     */
    public static function clean(array $a): array {
        $exclusions=wp_parse_id_list(is_array($a['exclude_ids'] ?? null)?$a['exclude_ids']:explode(',',(string)($a['exclude_ids'] ?? '')));
        foreach($a as $key=>$value){if(!is_scalar($value)){unset($a[$key]);}}
        $types=get_post_types(['public'=>true]);unset($types['attachment']);$type=sanitize_key($a['type'] ?? 'post');if($type!=='all'&&!isset($types[$type])){$type='post';}
        $period=array_key_exists($a['period'] ?? '',ReportTools::periods())?$a['period']:'30_days';
        return ['title'=>sanitize_text_field($a['title'] ?? ''),'type'=>$type,'period'=>$period,'year'=>absint($a['year'] ?? wp_date('Y')),'start'=>sanitize_text_field($a['start'] ?? ''),'end'=>sanitize_text_field($a['end'] ?? ''),'category'=>absint($a['category'] ?? 0),'published_year'=>absint($a['published_year'] ?? 0),'limit'=>max(1,min(50,absint($a['limit'] ?? 10))),'public'=>!empty($a['public']),'caption'=>!empty($a['caption']),'counts'=>!empty($a['counts']),'images'=>!empty($a['images']),'exclude_ids'=>array_slice($exclusions,0,200),'global_exclusions'=>!isset($a['global_exclusions']) || !empty($a['global_exclusions']),'mode'=>in_array($a['mode'] ?? '',['popular','rising','falling','evergreen','rediscovered'],true)?$a['mode']:'popular','compare'=>!empty($a['compare'])&&($a['mode'] ?? '')!=='evergreen','share'=>!empty($a['share']),'min_views'=>max(5,min(100000,absint($a['min_views'] ?? 10))),'months'=>max(2,min(12,absint($a['months'] ?? 3))),'older_days'=>max(30,min(7300,absint($a['older_days'] ?? 365))),'style'=>in_array($a['style'] ?? '',['numbered','links','images'],true)?$a['style']:'numbered'];
    }
    /**
     * Merge local and optional site-wide content exclusions.
     * @param array $a Definition.
     * @return array Content IDs, never paths or visitor information.
     */
    public static function exclusions(array $a): array {
        $ids=wp_parse_id_list($a['exclude_ids'] ?? []);$s=Plugin::settings();
        if(!isset($a['global_exclusions']) || $a['global_exclusions']){$ids=array_merge($ids,wp_parse_id_list($s['exclude_ids']));if($s['exclude_front']){$ids[]=(int)get_option('page_on_front');}}
        return array_values(array_unique(array_filter($ids)));
    }
    /**
     * Query content and optional comparison totals, with bounded cached SQL.
     * @param array $a Definition.
     * @return array Aggregate rows, each with a content ID and counts.
     */
    public static function query(array $a): array {
        if(!Plugin::ready() || !($range=self::dates($a))){return [];}
        global $wpdb;$excluded=self::exclusions($a);$mode=$a['mode'] ?? 'popular';$compare=$mode!=='evergreen'&&(!empty($a['compare'])||in_array($mode,['rising','falling','rediscovered'],true));$previous=self::previous($range);
        $key='tbm_rank_'.md5(wp_json_encode([$a,$range,$excluded,get_option('tbm_revision',0),TBM_VERSION]));$cached=get_transient($key);if(is_array($cached)){return $cached;}
        $dateWhere="s.date BETWEEN %s AND %s";$dateParams=[$compare?$previous[0]:$range[0],$range[1]];
        $where="p.post_status='publish' AND p.post_password=''";$params=[];
        $types=array_values(array_diff(get_post_types(['public'=>true]),['attachment']));
        if(($a['type'] ?? 'post')!=='all'){$types=in_array($a['type'] ?? 'post',$types,true)?[$a['type'] ?? 'post']:[];}
        if(!$types){return [];}$where.=' AND p.post_type IN ('.implode(',',array_fill(0,count($types),'%s')).')';array_push($params,...$types);
        if(!empty($a['search'])&&is_string($a['search'])){$where.=' AND p.post_title LIKE %s';$params[]='%'.$wpdb->esc_like(mb_substr($a['search'],0,80)).'%';}
        if($excluded){$where.=' AND p.ID NOT IN ('.implode(',',array_fill(0,count($excluded),'%d')).')';array_push($params,...$excluded);}
        if(!empty($a['category'])){$where.=" AND EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id WHERE tr.object_id=p.ID AND tt.taxonomy='category' AND tt.term_id=%d)";$params[]=(int)$a['category'];}
        if(!empty($a['published_year'])){$where.=' AND p.post_date >= %s AND p.post_date < %s';$year=(int)$a['published_year'];$params[]="$year-01-01";$params[]=($year+1).'-01-01';}
        if($mode==='rediscovered'){$where.=' AND p.post_date < %s';$params[]=(new \DateTimeImmutable('today',wp_timezone()))->modify('-'.max(30,(int)($a['older_days'] ?? 365)).' days')->format('Y-m-d');}
        $limit=max(1,min(50,(int)($a['limit'] ?? 10)));$minimum=max(5,(int)($a['min_views'] ?? 10));
        if($mode==='evergreen'){
            $where=$dateWhere.' AND '.$where;$params=array_merge($dateParams,$params);
            $sql="SELECT monthly.ID,SUM(monthly.views) pageviews,0 previous,0 delta FROM (SELECT p.ID,DATE_FORMAT(s.date,'%%Y-%%m') month_key,SUM(s.pageviews) views FROM {$wpdb->prefix}tbm_post_stats s JOIN {$wpdb->posts} p ON p.ID=s.post_id WHERE $where GROUP BY p.ID,month_key) monthly GROUP BY monthly.ID HAVING COUNT(*) >= %d AND MIN(monthly.views) >= %d ORDER BY pageviews DESC,monthly.ID ASC LIMIT %d";
            array_push($params,max(2,(int)($a['months'] ?? 3)),$minimum,$limit);
        }else{
            $select='SUM(CASE WHEN s.date BETWEEN %s AND %s THEN s.pageviews ELSE 0 END)';$prev=$compare?'SUM(CASE WHEN s.date BETWEEN %s AND %s THEN s.pageviews ELSE 0 END)':'0';
            $head=[$range[0],$range[1]];if($compare){array_push($head,...$previous);}$wideRange=(new \DateTimeImmutable($range[0]))->diff(new \DateTimeImmutable($range[1]))->days>=179
                && ($a['type'] ?? 'post')==='all' && empty($a['search']) && empty($a['category']) && empty($a['published_year']) && $mode!=='rediscovered';
            $params=array_merge($head,$dateParams,$params);
            $having='pageviews > 0';$order='pageviews DESC,p.ID ASC';
            if(in_array($mode,['rising','rediscovered'],true)){$having='pageviews >= %d AND delta > 0';$params[]=$minimum;$order='delta DESC,pageviews DESC,p.ID ASC';}
            if($mode==='falling'){$having='previous >= %d AND delta < 0';$params[]=$minimum;$order='delta ASC,previous DESC,p.ID ASC';}
            // Delta is computed from the same aggregate expressions through a bounded derived table.
            // Broad long-range reports aggregate the date slice once, avoiding a row lookup
            // per historical counter through the post/date index. Selective filters keep
            // their indexed join. No extra index or measurement write is required.
            $aggregate=$wideRange
                ? "SELECT p.ID,counts.pageviews,counts.previous FROM (SELECT s.post_id,$select pageviews,$prev previous FROM {$wpdb->prefix}tbm_post_stats s WHERE $dateWhere GROUP BY s.post_id) counts JOIN {$wpdb->posts} p ON p.ID=counts.post_id WHERE $where"
                : "SELECT p.ID,$select pageviews,$prev previous FROM {$wpdb->prefix}tbm_post_stats s JOIN {$wpdb->posts} p ON p.ID=s.post_id WHERE $dateWhere AND $where GROUP BY p.ID";
            $sql="SELECT totals.*,totals.pageviews-totals.previous delta FROM ($aggregate) totals HAVING ".str_replace('p.ID','ID',$having).' ORDER BY '.str_replace('p.ID','ID',$order).' LIMIT %d';$params[]=$limit;
        }
        $rows=$wpdb->get_results($wpdb->prepare($sql,$params),ARRAY_A);if(is_array($rows)){set_transient($key,$rows,300);}return is_array($rows)?$rows:[];
    }
    /**
     * Obtain the explicit denominator for site-wide pageview shares.
     * @param array $a Definition including an optional saved snapshot.
     * @return int Retained site pageviews, or zero when a frozen denominator is unavailable.
     */
    public static function site_total(array $a): int {
        if(isset($a['snapshot'])){return (int)($a['snapshot']['site_total'] ?? 0);}
        $range=self::dates($a);if(!$range || !Plugin::ready()){return 0;}global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(pageviews),0) FROM {$wpdb->prefix}tbm_site_stats WHERE date BETWEEN %s AND %s",$range));
    }
    /**
     * Recheck current content visibility and exclusions even for saved snapshots.
     * @param array $a Definition.
     * @param array $rows Frozen or queried rows.
     * @return array Currently eligible rows.
     */
    public static function visible(array $a,array $rows): array {
        $excluded=self::exclusions($a);return array_values(array_filter($rows,static function($row) use($excluded){$post=get_post((int)$row['ID']);return $post && $post->post_status==='publish' && $post->post_password==='' && is_post_publicly_viewable($post) && !in_array((int)$post->ID,$excluded,true);}));
    }
    /**
     * Render an escaped list with optional shares and prior-period counts.
     * @param array $a Definition.
     * @param array|null $rows Optional snapshot rows.
     * @return string List markup or a useful empty-state message.
     */
    public static function render(array $a,?array $rows=null): string {
        $rows=self::visible($a,$rows ?? self::query($a));
        if(!$rows){return '<p class="tbm-empty">'.esc_html__('No matching public content with recorded pageviews was found for these filters.','tabulamea').'</p>';}
        wp_enqueue_style('tbm-ranking',plugins_url('assets/rankings.css',TBM_FILE),[],TBM_VERSION);
        $style=$a['style'] ?? 'numbered';$tag=$style==='numbered'?'ol':'ul';$html='<'.$tag.' class="tbm-ranking tbm-ranking-'.esc_attr($style).'">';$total=!empty($a['share'])?self::site_total($a):0;
        foreach($rows as $row){$post=get_post((int)$row['ID']);$html.='<li><a href="'.esc_url(get_permalink($post)).'">';if(!empty($a['images'])||$style==='images'){$html.=get_the_post_thumbnail($post,'thumbnail',['loading'=>'lazy']);}$html.=esc_html(get_the_title($post)).'</a>';
            if(!empty($a['counts'])){$html.=' <span>'.esc_html(number_format_i18n((int)$row['pageviews'])).' '.esc_html__('pageviews','tabulamea').'</span>';}
            if(!empty($a['share']) && $total>0){$html.=' <span>('.esc_html(number_format_i18n(100*(int)$row['pageviews']/$total,1)).' %)</span>';}
            if(empty($a['hide_changes'])&&(!empty($a['compare'])||in_array($a['mode'] ?? '',['rising','falling','rediscovered'],true)) && array_key_exists('previous',$row)){$html.='<span class="tbm-change">'.esc_html(sprintf(__('Previous period: %1$s · Change: %2$s','tabulamea'),number_format_i18n((int)$row['previous']),((int)$row['delta']>0?'+':'').number_format_i18n((int)$row['delta']))).'</span>';}
            $html.='</li>';
        }
        $html.='</'.$tag.'>';
        if(!empty($a['share'])){$html.='<p class="tbm-share-note">'.esc_html($total>0?__('Percentages refer to all recorded site pageviews in this period, including content outside this list.','tabulamea'):__('A saved site total is unavailable; percentage shares are not shown.','tabulamea')).'</p>';}
        if(!empty($a['caption'])){$range=$a['snapshot']['range']??self::dates($a);$html.='<p class="tbm-ranking-caption">'.esc_html(sprintf(__('Recorded pageviews · %1$s to %2$s','tabulamea'),$range[0],$range[1])).'</p>';}
        return $html;
    }
    /**
     * Render only explicitly published definitions; private output stays empty.
     * @param array $atts Shortcode or block attributes.
     * @return string Public output.
     */
    public static function shortcode(array $atts=[]): string {
        if(!Plugin::settings()['public_rankings']){return '';}$id=is_scalar($atts['id'] ?? '')?sanitize_key((string)($atts['id'] ?? '')):'';$rank=get_option('tbm_rankings',[])[$id] ?? null;
        if(!is_array($rank)||empty($rank['public'])){return '';}if(!Plugin::settings()['public_counts']){$rank['counts']=false;$rank['share']=false;$rank['compare']=false;$rank['hide_changes']=true;}return self::render($rank,$rank['snapshot']['rows']??null);
    }
    /** Render a public anonymous pageview counter only after explicit publication opt-in.
     * @param array $atts Days and whole-site selection.
     * @param int $id Optional block-context public content ID; zero uses the current post.
     * @return string Escaped formatted count, or empty output when disabled.
     */
    public static function counter(array $atts=[],int $id=0): string {
        if(!Plugin::ready()||!Plugin::settings()['public_rankings']||!Plugin::settings()['public_counts']){return '';}
        $a=shortcode_atts(['days'=>30,'global'=>'false'],$atts,'tabulamea_counter');$days=max(1,min(36500,(int)$a['days']));$range=[max('2000-01-01',(new \DateTimeImmutable('today',wp_timezone()))->modify('-'.($days-1).' days')->format('Y-m-d')),wp_date('Y-m-d')];
        $id=$id?:get_the_ID();if($a['global']!=='true'){$post=$id?get_post($id):null;if(!$post||!is_post_publicly_viewable($post)||$post->post_password!==''){return '';}}
        global $wpdb;$total=($a['global']==='true')?(new Stats())->get_totals(...$range)->pageviews:(int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(pageviews),0) FROM {$wpdb->prefix}tbm_post_stats WHERE post_id=%d AND date BETWEEN %s AND %s",$id,...$range));
        if($a['global']==='true'&&Plugin::settings()['measure_scope']==='selected'){return esc_html(sprintf(__('%s recorded pageviews (configured collection scope)','tabulamea'),number_format_i18n($total)));}return esc_html(number_format_i18n($total));
    }
    /** Render a convenient live list after global public output opt-in.
     * @param array $atts Number, days and public content type.
     * @return string Escaped ranking output.
     */
    public static function popular(array $atts=[]): string {
        if(!Plugin::ready()||!Plugin::settings()['public_rankings']){return '';}
        $a=shortcode_atts(['number'=>5,'days'=>30,'post_type'=>'post'],$atts,'tabulamea_popular');$days=max(1,min(36500,(int)$a['days']));
        return self::render(self::clean(['type'=>sanitize_key($a['post_type']),'period'=>'custom','start'=>max('2000-01-01',(new \DateTimeImmutable('today',wp_timezone()))->modify('-'.($days-1).' days')->format('Y-m-d')),'end'=>wp_date('Y-m-d'),'limit'=>max(1,min(50,(int)$a['number'])),'counts'=>Plugin::settings()['public_counts']]));
    }

}
