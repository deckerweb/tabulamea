<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Registers public GitHub updates and validates the exact host package before replacement. */
final class UpdateIntegration {
    /** @var bool Whether an incompatible loaded updater prevented registration. */
    private static bool $unavailable=false;
    /** Initialize the pinned updater after host translations; no release is published here. @return void */
    public static function boot(): void {
        // Use this host's pinned implementation even when another V2 version loaded first.
        $class=require __DIR__.'/deckerweb-github-release-updater-v2.php';
        if(!is_string($class)||!class_exists($class,false)||!defined($class.'::SUPPORTS_CATALOG_PROVIDER')||!constant($class.'::SUPPORTS_CATALOG_PROVIDER')||!defined($class.'::SUPPORTS_HOST_TRANSLATIONS')||!constant($class.'::SUPPORTS_HOST_TRANSLATIONS')||!defined($class.'::IMPLEMENTATION_VERSION')||version_compare(constant($class.'::IMPLEMENTATION_VERSION'),'2.2.1','<')){self::$unavailable=true;add_action('admin_notices',[self::class,'notice']);return;}
        try{
            $translate=require __DIR__.'/updater-translations.php';
            $base=plugins_url('assets/brand/',TBM_FILE);$de=str_starts_with(determine_locale(),'de_');
            $updater=new $class(TBM_FILE,'https://github.com/deckerweb/tabulamea','TabulaMea',__('Privacy-friendly website statistics','tabulamea'),['icons'=>['svg'=>$base.'icon.svg','1x'=>$base.'icon-128x128.png','2x'=>$base.'icon-256x256.png'],'banners'=>['low'=>$base.($de?'banner-772x250-de.png':'banner-772x250.png'),'high'=>$base.($de?'banner-1544x500-de.png':'banner-1544x500.png')]],array_merge(deckerweb_library_updater_options_v1(TBM_FILE,'https://github.com/deckerweb/tabulamea'),['translate'=>$translate]));
            $updater->register();add_filter('upgrader_source_selection',[self::class,'validate'],30,4);
        }catch(\Throwable $error){self::$unavailable=true;add_action('admin_notices',[self::class,'notice']);}
    }
    /** Show an actionable, host-localized configuration notice only to update administrators. @return void */
    public static function notice(): void {
        if(self::$unavailable&&current_user_can('update_plugins')){echo '<div class="notice notice-warning"><p>'.esc_html__('TabulaMea updates require a compatible deckerweb Updater. Update the other plugins that bundle this component, then check again.','tabulamea').'</p></div>';}
    }
    /** Validate the host identity, offered version and actual requirements of a native update package.
     * @param string|\WP_Error $source Unpacked and normalized candidate directory or earlier failure.
     * @param string $remote_source Native staging root.
     * @param object $upgrader Native upgrader instance.
     * @param array $context Per-package native update context.
     * @return string|\WP_Error Original source or a localized validation failure.
     */
    public static function validate($source,string $remote_source,$upgrader,array $context) {
        $basename=plugin_basename(TBM_FILE);
        if(($context['plugin']??'')!==$basename||(isset($context['type'])&&$context['type']!=='plugin')||(isset($context['action'])&&$context['action']!=='update')){return $source;}
        if(!(isset($context['type'],$context['action'])||($upgrader instanceof \Plugin_Upgrader&&$upgrader->bulk===true))||is_wp_error($source)){return $source;}
        $root=is_string($source)?realpath($source):false;$main=$root?realpath($root.'/tabulamea.php'):false;
        $error=static fn()=>new \WP_Error('tabulamea_update_invalid',__('The update package does not match TabulaMea, its offered version or this website’s requirements. The installed version has not been replaced.','tabulamea'));
        if(!$root||!$main||!str_starts_with($main,$root.DIRECTORY_SEPARATOR)||!is_readable($main)||filesize($main)>131072){return $error();}
        $headers=get_file_data($main,['name'=>'Plugin Name','version'=>'Version','uri'=>'Update URI','domain'=>'Text Domain','wp'=>'Requires at least','php'=>'Requires PHP']);
        $updates=get_site_transient('update_plugins');$offer=is_object($updates)?($updates->response[$basename]??null):null;$expected=is_object($offer)?($offer->new_version??''):'';
        if($headers['name']!=='TabulaMea'||$headers['domain']!=='tabulamea'||$headers['uri']!=='https://github.com/deckerweb/tabulamea'||!is_string($expected)||$expected===''||$headers['version']!==$expected||version_compare($headers['version'],TBM_VERSION,'<=')||!preg_match('/^\d+\.\d+(?:\.\d+)?$/D',$headers['wp'])||!preg_match('/^\d+\.\d+(?:\.\d+)?$/D',$headers['php'])||version_compare(get_bloginfo('version'),$headers['wp'],'<')||version_compare(PHP_VERSION,$headers['php'],'<')){return $error();}
        return $source;
    }
}
