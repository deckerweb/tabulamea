<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Capability- and nonce-protected commands for previews, preferences and exports. */
final class ToolsActions {
    /** Dispatch only known commands; write operations require site administration. @return void */
    public static function dispatch(): void {
        $command=InterfaceTools::text($_POST,'command');$allowed=['preference','csv','access_preview','add_user','save_template','delete_template','template_preview','network_preview','reset_preview','apply_preview','discard_preview','export_selected','import_preview','starter','duplicate'];
        if(!in_array($command,$allowed,true)){wp_die(esc_html__('Unknown action.','tabulamea'),'',400);}
        check_admin_referer('tbm_tools_'.$command);
        if($command==='preference'){ReportTools::preference();}
        if($command==='csv'){self::csv();}
        if(!current_user_can('manage_options')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}
        $destination=InterfaceTools::settings_url('data');$ok=true;
        try{
            $sections=self::list('sections');$templates=(array)get_option('tbm_templates',[]);$id=sanitize_key(InterfaceTools::text($_POST,'template'));
            if($command==='access_preview'){$presets=AccessTools::presets();$preset=InterfaceTools::text($_POST,'preset');if(!isset($presets[$preset])){throw new \InvalidArgumentException('preset');}ConfigurationTools::stage($presets[$preset]['settings'],[],[get_current_blog_id()]);$destination=InterfaceTools::settings_url('access');}
            if($command==='add_user'){$user=absint(InterfaceTools::text($_POST,'user_id'));if(!$user||!is_user_member_of_blog($user)){throw new \InvalidArgumentException('user');}$s=Plugin::settings();$s['users'][$user] ??= ['view'=>'inherit','manage'=>'inherit','widget'=>'inherit'];update_option('tbm_settings',$s,false);$destination=add_query_arg('inspect_user',$user,InterfaceTools::settings_url('access'));}
            if($command==='export_selected'){$data=ConfigurationTools::package($sections,self::list('rank_ids'));nocache_headers();header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="tabulamea-configuration.json"');echo wp_json_encode($data,JSON_PRETTY_PRINT);exit;}
            if($command==='import_preview'){self::import_preview($sections,!empty($_POST['include_rankings']));}
            if($command==='save_template'){$name=mb_substr(InterfaceTools::text($_POST,'name'),0,100);if(!$name||count($templates)>=20){throw new \InvalidArgumentException('template');}$key=sanitize_key(wp_generate_uuid4());$templates[$key]=['name'=>$name,'package'=>ConfigurationTools::package($sections,self::list('rank_ids'))];update_option('tbm_templates',$templates,false);}
            if($command==='delete_template'){unset($templates[$id]);update_option('tbm_templates',$templates,false);}
            if(in_array($command,['template_preview','network_preview'],true)){
                if(!isset($templates[$id])){throw new \InvalidArgumentException('template');}$package=ConfigurationTools::validate($templates[$id]['package'],$sections,!empty($_POST['include_rankings']));$sites=[get_current_blog_id()];$preserve=false;
                if($command==='network_preview'){if(!is_multisite()||!current_user_can('manage_network_options')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}$sites=array_map('absint',self::list('sites'));if(!$sites||count($sites)>50){throw new \InvalidArgumentException('sites');}$preserve=!empty($_POST['preserve_custom']);}
                ConfigurationTools::stage($package['settings'],$package['rankings'],$sites,$preserve);
            }
            if($command==='reset_preview'){$section=InterfaceTools::text($_POST,'section');$keys=ConfigurationTools::sections()[$section] ?? null;if(!$keys){throw new \InvalidArgumentException('section');}$patch=array_intersect_key(Plugin::defaults(),array_flip($keys));unset($patch['retention']);ConfigurationTools::stage($patch,[],[get_current_blog_id()]);}
            if($command==='apply_preview'){ConfigurationTools::apply();}
            if($command==='discard_preview'){delete_transient('tbm_stage_'.get_current_user_id());}
            if($command==='starter'){$preset=InterfaceTools::text($_POST,'preset');$definitions=RankingAdmin::starters();if(!isset($definitions[$preset])){throw new \InvalidArgumentException('preset');}$rank=Rankings::clean($definitions[$preset]);if($preset==='category-posts'){$rank['category']=absint(InterfaceTools::text($_POST,'category'));}$ranks=(array)get_option('tbm_rankings',[]);if(count($ranks)>=100){throw new \RuntimeException('capacity');}$key=self::unique_id($preset,$ranks);$ranks[$key]=$rank;update_option('tbm_rankings',$ranks,false);$destination=add_query_arg('edit_ranking',$key,admin_url('admin.php?page=tabulamea-reports'));}
            if($command==='duplicate'){$ranks=(array)get_option('tbm_rankings',[]);$original=sanitize_key(InterfaceTools::text($_POST,'id'));if(!isset($ranks[$original])||count($ranks)>=100){throw new \InvalidArgumentException('ranking');}$key=self::unique_id($original.'-copy',$ranks);$rank=Rankings::clean($ranks[$original]);$rank['title']=sprintf(__('Copy of %s','tabulamea'),$rank['title']);$rank['public']=false;$ranks[$key]=$rank;update_option('tbm_rankings',$ranks,false);$destination=add_query_arg('edit_ranking',$key,admin_url('admin.php?page=tabulamea-reports'));}
            if(in_array($command,['starter','duplicate'],true)){update_option('tbm_revision',microtime(true),false);}
        }catch(\Throwable $error){$ok=false;}
        wp_safe_redirect(add_query_arg('tbm_status',$ok?'ok':'error',$destination));exit;
    }
    /**
     * Read a bounded list of scalar POST values.
     * @param string $key Input key.
     * @return array Sanitized unique values.
     */
    public static function list(string $key): array {
        $values=is_array($_POST[$key] ?? null)?$_POST[$key]:[];return array_values(array_unique(array_map('sanitize_key',array_filter(array_slice(wp_unslash($values),0,200),'is_scalar'))));
    }
    /**
     * Find an unused bounded ranking identifier.
     * @param string $base Desired ID.
     * @param array $ranks Existing definitions.
     * @return string Available ID.
     */
    private static function unique_id(string $base,array $ranks): string {
        $base=substr(sanitize_key($base),0,64);$id=$base;$suffix=2;while(isset($ranks[$id])){$id=$base.'-'.$suffix++;}return $id;
    }
    /**
     * Validate an uploaded JSON file and stage, never directly apply, its selected sections.
     * @param array $sections Selected sections.
     * @param bool $rankings Include definitions.
     * @return void
     */
    public static function import_preview(array $sections,bool $rankings): void {
        $file=$_FILES['configuration'] ?? [];if(($file['error'] ?? 1)!==UPLOAD_ERR_OK||($file['size'] ?? 0)>262144||!is_uploaded_file($file['tmp_name'] ?? '')){throw new \InvalidArgumentException('upload');}
        $data=json_decode((string)file_get_contents($file['tmp_name']),true,64,JSON_THROW_ON_ERROR);if(!is_array($data)){throw new \InvalidArgumentException('json');}$package=ConfigurationTools::validate($data,$sections,$rankings);ConfigurationTools::stage($package['settings'],$package['rankings'],[get_current_blog_id()]);
    }
    /**
     * Neutralize spreadsheet formulas in every exported text cell.
     * @param mixed $value Scalar CSV value.
     * @return string Safe spreadsheet cell.
     */
    public static function cell($value): string { $value=(string)$value;return preg_match('/^[\p{Z}\p{C}\s]*[=+@\-]/u',$value)||preg_match('/^[\t\r\n]/',$value)?"'".$value:$value; }
    /** Export only authorized, currently visible report rows. @return void */
    private static function csv(): void {
        if(!Plugin::ready()||!Plugin::allowed('view')){wp_die(esc_html__('Access denied.','tabulamea'),'',403);}
        $kind=InterfaceTools::text($_POST,'report');$out=[];
        if(in_array($kind,['overview','contents','referrers','search'],true)){$range=Rankings::dates(['period'=>'custom','start'=>InterfaceTools::text($_POST,'start'),'end'=>InterfaceTools::text($_POST,'end')]);if(!$range){wp_die(esc_html__('Invalid report.','tabulamea'),'',400);}$out=DataExports::rows($kind,$range);}
        elseif($kind==='dimension'){$range=Rankings::dates(['period'=>'custom','start'=>InterfaceTools::text($_POST,'start'),'end'=>InterfaceTools::text($_POST,'end')]);$dim=InterfaceTools::text($_POST,'dimension');if(!$range||!isset(DimensionReports::labels()[$dim])){wp_die(esc_html__('Invalid report.','tabulamea'),'',400);}$out[]=[__('Category','tabulamea'),__('Pageviews','tabulamea'),__('Share of displayed categories','tabulamea')];foreach(DimensionReports::rows($dim,$range) as $row){$out[]=[Tracking::label($row['value']),$row['pageviews'],$row['share']];}}
        elseif($kind==='ranking'){$id=sanitize_key(InterfaceTools::text($_POST,'id'));$rank=get_option('tbm_rankings',[])[$id] ?? null;if(!is_array($rank)){wp_die(esc_html__('Invalid report.','tabulamea'),'',400);}$out[]=[__('Title','tabulamea'),'URL',__('Pageviews','tabulamea'),__('Start date','tabulamea'),__('End date','tabulamea')];$range=$rank['snapshot']['range'] ?? Rankings::dates($rank);foreach(Rankings::visible($rank,$rank['snapshot']['rows'] ?? Rankings::query($rank)) as $row){$out[]=[get_the_title($row['ID']),get_permalink($row['ID']),$row['pageviews'],$range[0],$range[1]];}}
        else{wp_die(esc_html__('Invalid report.','tabulamea'),'',400);}
        nocache_headers();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="tabulamea-'.sanitize_key($kind).'-'.$range[0].'-'.$range[1].'.csv"');$stream=fopen('php://output','w');fwrite($stream,"\xEF\xBB\xBF");foreach($out as $row){fputcsv($stream,array_map([self::class,'cell'],$row),',','"','');}fclose($stream);exit;
    }
}
