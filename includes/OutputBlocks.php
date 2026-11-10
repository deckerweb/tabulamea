<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Discoverable native blocks for anonymous counters and popular-content lists. */
final class OutputBlocks {
    /** Register dynamic block types and local editor controls. @return void */
    public static function register(): void {
        wp_register_script('tbm-output-blocks',plugins_url('assets/output-blocks.js',TBM_FILE),['wp-blocks','wp-element','wp-i18n','wp-components','wp-block-editor','wp-dom-ready','wp-server-side-render','wp-data'],TBM_VERSION,true);wp_set_script_translations('tbm-output-blocks','tabulamea',dirname(TBM_FILE).'/languages');
        register_block_type('tabulamea/counter',['api_version'=>3,'editor_script'=>'tbm-output-blocks','supports'=>self::supports(),'uses_context'=>['postId'],'attributes'=>['before'=>['type'=>'string','default'=>''],'after'=>['type'=>'string','default'=>''],'days'=>['type'=>'number','default'=>30],'global'=>['type'=>'boolean','default'=>false]],'render_callback'=>[self::class,'counter']]);
        register_block_type('tabulamea/popular',['api_version'=>3,'editor_style'=>'tbm-ranking','style'=>'tbm-ranking','editor_script'=>'tbm-output-blocks','supports'=>self::supports(),'attributes'=>['days'=>['type'=>'number','default'=>30],'number'=>['type'=>'number','default'=>5],'postType'=>['type'=>'string','default'=>'post']],'render_callback'=>[self::class,'popular']]);
        add_filter('query_loop_block_query_vars',[self::class,'query_loop'],20,2);
        add_action('init',[self::class,'collections'],30);add_action('init',[self::class,'patterns'],31);
        add_action('enqueue_block_editor_assets',[self::class,'labels']);
    }
    /** Register three native starting points without hard-coded content IDs or invented counts. @return void */
    public static function patterns(): void {
        register_block_pattern_category('tabulamea',['label'=>'TabulaMea']);
        $patterns=[
            'popular-articles'=>[__('Popular articles','tabulamea'),'<!-- wp:tabulamea/popular /-->'],
            'year-review'=>[__('Year in review','tabulamea'),'<!-- wp:tabulamea/ranking /-->'],
            'pageview-label'=>[__('Pageviews with a label','tabulamea'),'<!-- wp:tabulamea/counter '.wp_json_encode(['before'=>__('Pageviews: ','tabulamea')]).' /-->']
        ];
        foreach($patterns as $slug=>$pattern){register_block_pattern('tabulamea/'.$slug,['title'=>$pattern[0],'categories'=>['tabulamea'],'description'=>$slug==='year-review'?__('Choose a saved calendar-year ranking in the block sidebar. Public output requires explicit permission.','tabulamea'):__('A starting point using recorded pageviews. Adjust the block in its sidebar.','tabulamea'),'content'=>'<!-- wp:group {"layout":{"type":"constrained"}} --><div class="wp-block-group">'.$pattern[1].'</div><!-- /wp:group -->']);}
    }
    /** Register native collection filters after public custom types have been registered. @return void */
    public static function collections(): void {
        foreach(get_post_types(['public'=>true]) as $type){add_filter('rest_'.$type.'_collection_params',[self::class,'collection_params']);add_filter('rest_'.$type.'_query',[self::class,'rest_query'],20,2);}
    }
    /** Return native appearance controls shared by output blocks. @return array Block support flags. */
    public static function supports(): array {return ['color'=>['text'=>true,'background'=>true],'typography'=>['fontSize'=>true,'lineHeight'=>true],'spacing'=>['margin'=>true,'padding'=>true],'align'=>true,'html'=>false];}
    /** Provide translated labels and available public types without exposing statistics. @return void */
    public static function labels(): void {
        $types=[];foreach(get_post_types(['public'=>true],'objects') as $type){if($type->name!=='attachment'){$types[]=['value'=>$type->name,'label'=>$type->labels->name];}}
        wp_localize_script('tbm-output-blocks','tbmOutputLabels',['enabled'=>(bool)Plugin::settings()['public_rankings'],'counts'=>(bool)Plugin::settings()['public_counts'],'counter'=>__('TabulaMea pageview counter','tabulamea'),'popular'=>__('TabulaMea popular content','tabulamea'),'days'=>__('Days','tabulamea'),'number'=>__('Number of items','tabulamea'),'global'=>__('Count the whole website','tabulamea'),'type'=>__('Content type','tabulamea'),'countHelp'=>__('Counters also require permission to publish exact numbers.','tabulamea'),'help'=>__('Uses recorded pageviews. Public output must be enabled in TabulaMea settings.','tabulamea'),'before'=>__('Text before the number','tabulamea'),'after'=>__('Text after the number','tabulamea'),'settings'=>__('Display settings','tabulamea'),'disabled'=>__('Public output is disabled. Your block settings are preserved. An administrator can allow output under TabulaMea → Access.','tabulamea'),'countsDisabled'=>__('Publishing exact numbers is disabled. Your counter settings are preserved. An administrator can allow numbers under TabulaMea → Access.','tabulamea'),'empty'=>__('No matching recorded pageviews are available for this selection. Try another period or content type. Your settings are preserved.','tabulamea'),'unavailable'=>__('No public output is available. Check publication permissions, the content selection and recorded data.','tabulamea'),'types'=>$types,'query'=>__('TabulaMea popular content loop','tabulamea')]);
    }
    /** Render the pageview counter with the same publication guard as its shortcode.
     * @param array $attributes Block attributes.
     * @param string $content Saved inner markup, unused by this dynamic block.
     * @param \WP_Block|null $block Block context, including a Query Loop content ID.
     * @return string Escaped counter markup or empty output.
     */
    public static function counter(array $attributes,string $content='',?\WP_Block $block=null): string {$text=Rankings::counter(['days'=>$attributes['days']??30,'global'=>!empty($attributes['global'])?'true':'false'],(int)($block->context['postId']??0));return $text===''?'':'<span '.get_block_wrapper_attributes().'>'.esc_html((string)($attributes['before']??'')).$text.esc_html((string)($attributes['after']??'')).'</span>';}
    /** Render a bounded popular-content list.
     * @param array $attributes Block attributes.
     * @return string Safe linked-list markup.
     */
    public static function popular(array $attributes): string { $html=Rankings::popular(['days'=>$attributes['days']??30,'number'=>$attributes['number']??5,'post_type'=>$attributes['postType']??'post']);return $html===''?'':'<div '.get_block_wrapper_attributes().'>'.$html.'</div>';}
    /** Permit the explicitly named popular ordering in native editor collection requests.
     * @param array $params REST collection schema.
     * @return array Schema with the additional ordering value.
     */
    public static function collection_params(array $params): array {if(isset($params['orderby']['enum'])){$params['orderby']['enum'][]='tbm_popular';}return $params;}
    /** Resolve the popular loop's frontend query without affecting unrelated Query Loops.
     * @param array $query Native query arguments.
     * @param \WP_Block $block Query context block.
     * @return array Safe ordered public-content query.
     */
    public static function query_loop(array $query,\WP_Block $block): array {return ($block->context['query']['orderBy']??'')==='tbm_popular'?self::popular_query($query):$query;}
    /** Support the matching native block editor preview through the WordPress REST API.
     * @param array $query Native collection query.
     * @param \WP_REST_Request $request Current REST request.
     * @return array Safe public-content query.
     */
    public static function rest_query(array $query,\WP_REST_Request $request): array {return $request->get_param('orderby')==='tbm_popular'?self::popular_query($query):$query;}
    /** Replace a popular loop's order using bounded recorded counts and publication opt-in.
     * @param array $query Native query arguments.
     * @return array Arguments containing only approved public content IDs.
     */
    private static function popular_query(array $query): array {
        $ids=[];if(Plugin::settings()['public_rankings']){$type=is_string($query['post_type']??null)?$query['post_type']:'post';$rows=Rankings::query(['type'=>$type,'period'=>'30_days','limit'=>50]);$ids=array_column(Rankings::visible([], $rows),'ID');}
        $query['post__in']=$ids?:[0];$query['orderby']='post__in';$query['ignore_sticky_posts']=true;$query['post_status']='publish';$query['has_password']=false;return $query;
    }

}
