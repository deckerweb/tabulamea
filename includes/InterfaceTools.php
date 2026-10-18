<?php
namespace Deckerweb\TabulaMea;
defined('ABSPATH') || exit;
/** Small accessible controls shared by the plugin screens. */
final class InterfaceTools {
    /**
     * Print a POST form for a protected Tools command.
     * @param string $command Operation name.
     * @param array $fields Scalar hidden fields.
     * @return void
     */
    public static function form(string $command,array $fields=[]): void {
        echo '<form class="tbm-command" action="'.esc_url(admin_url('admin-post.php')).'" method="post"><input type="hidden" name="action" value="tbm_tools"><input type="hidden" name="command" value="'.esc_attr($command).'">';
        wp_nonce_field('tbm_tools_'.$command);
        foreach($fields as $key=>$value){echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr((string)$value).'">';}
    }
    /**
     * Print a labeled text, number or date input.
     * @param string $name Submitted key.
     * @param string $label Translated label.
     * @param mixed $value Scalar value.
     * @param string $type HTML input type.
     * @param array $attributes Allowed native validation attributes.
     * @return void
     */
    public static function input(string $name,string $label,$value='',string $type='text',array $attributes=[]): void {
        $extra='';foreach(array_intersect_key($attributes,array_flip(['min','max','step','maxlength','required'])) as $key=>$attribute){if($attribute===false){continue;}$extra.=' '.esc_attr($key).'="'.esc_attr($attribute===true?$key:(string)$attribute).'"';}
        echo '<p><label>'.esc_html($label).' <input type="'.esc_attr($type).'" name="'.esc_attr($name).'" value="'.esc_attr((string)$value).'"'.$extra.'></label></p>';
    }
    /**
     * Print an accessible select.
     * @param string $name Submitted key.
     * @param string $label Translated label.
     * @param array $options Allowed value/label pairs.
     * @param string $value Selected value.
     * @return void
     */
    public static function select(string $name,string $label,array $options,string $value=''): void {
        echo '<p><label>'.esc_html($label).' <select name="'.esc_attr($name).'">';
        foreach($options as $key=>$text){echo '<option value="'.esc_attr((string)$key).'" '.selected($value,(string)$key,false).'>'.esc_html($text).'</option>';}
        echo '</select></label></p>';
    }
    /**
     * Print a checkbox.
     * @param string $name Submitted key.
     * @param string $label Translated label.
     * @param bool $checked Current state.
     * @param string $value Submitted scalar value.
     * @return void
     */
    public static function check(string $name,string $label,bool $checked=false,string $value='1'): void {
        echo '<p><label><input type="checkbox" name="'.esc_attr($name).'" value="'.esc_attr($value).'" '.checked($checked,true,false).'> '.esc_html($label).'</label></p>';
    }
    /**
     * Print copyable text with a keyboard fallback.
     * @param string $text Plain text, never HTML.
     * @param string $label Translated field label.
     * @param int $rows Textarea height.
     * @param string $button Optional copy button label.
     * @return void
     */
    public static function copy(string $text,string $label,int $rows=2,string $button=''): void {
        echo '<div class="tbm-copy"><label>'.esc_html($label).'<textarea readonly rows="'.(int)$rows.'">'.esc_textarea($text).'</textarea></label><button type="button" class="button" data-tbm-copy data-success="'.esc_attr__('Copied.','tabulamea').'" data-fallback="'.esc_attr__('Select the text and copy it with the keyboard.','tabulamea').'">'.esc_html($button?:__('Copy','tabulamea')).'</button><span class="tbm-copy-status" role="status"></span></div>';
    }
    /**
     * Return a scalar request parameter without accepting arrays.
     * @param array $source Input source.
     * @param string $key Requested key.
     * @param string $default Fallback value.
     * @return string Unsplashed sanitized text.
     */
    public static function text(array $source,string $key,string $default=''): string {
        return isset($source[$key]) && is_scalar($source[$key]) ? sanitize_text_field(wp_unslash((string)$source[$key])) : $default;
    }
    /**
     * Return a local settings URL.
     * @param string $tab Registered section.
     * @return string Local URL.
     */
    public static function settings_url(string $tab='overview'): string { return admin_url('options-general.php?page=tabulamea&tab='.sanitize_key($tab)); }
    /**
     * Store a short-lived workflow value and register its key for uninstall cleanup.
     * @param string $key Known workflow key, bound to an administrator ID.
     * @param mixed $value Preview or draft state.
     * @param int $ttl Lifetime in seconds.
     * @return void
     */
    public static function temporary(string $key,$value,int $ttl): void {
        if(!preg_match('/^tbm_(stage|draft|apply_result|cleanup)_\\d+$/D',$key)){throw new \InvalidArgumentException('key');}
        $keys=(array)get_option('tbm_workflow_keys',[]);$now=time();foreach($keys as $name=>$expiry){if((int)$expiry<$now){unset($keys[$name]);}}$keys[$key]=$now+$ttl;update_option('tbm_workflow_keys',$keys,false);
        if(!set_transient($key,$value,$ttl) && get_transient($key)!==$value){throw new \RuntimeException('write');}
    }
}
