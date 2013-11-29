<?php
/*
Plugin Name: Speaker Deck
Plugin URI: http://bordoni.me/
Description: A WordPress plugin to allow Speaker Deck users embed their Presentations on the site/blog with ease.
Author: webord
Author URI: http://en.bordoni.me/plugins/speakerdeck
Version: 1.0
Text Domain: speakerdeck
Domain Path: /lang
License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html
*/

class SpeakerDeck {

    private $url = false;
    private $keys = array( // This will be casted as a object on the construct
        'menu' => 'speakerdeck',
        'configuration' => 'speakerdeck_configuration',
        'items' => 'speakerdeck_items',
        'speaks' => 'speakerdeck_speaks'
    );
    private $tabs = array();

    function __construct() {
        // Define private variables
        $this->keys = (object) $this->keys;
        $this->url = plugins_url('', __FILE__);

        // Hook all the functions to it's respective position
        add_action('init', array(&$this, 'load_settings'));
        add_action('init', array(&$this, 'init'));
        add_action('admin_init', array(&$this, 'register_settings'));
        add_action('admin_menu', array(&$this, 'admin_menu'));
        add_action('wp_enqueue_scripts', array(&$this, 'wp_enqueue_scripts'));
        add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
        add_action('admin_print_styles', array(&$this, 'admin_print_styles'));

        add_action( 'add_meta_boxes', array(&$this, 'add_meta_boxes') );
        add_action( 'save_post', array(&$this, 'save_post') );
        add_action('after_wp_tiny_mce', array(&$this, 'tinymce_form'));

        // Ajax Hooks
        add_action('wp_ajax_speakerdeck_webcrawler', array(&$this, 'proxy_url'));

        // Create the Shortcode for the Speakerdeck
        add_shortcode('speakerdeck', array(&$this, 'shortcode'));

    }

    function load_settings() {
        $this->configuration = (array) get_option( $this->keys->configuration );
        $this->items = get_option( $this->keys->items );
        if(empty($this->items))
            $this->items = array();

        // Merge with default configurations
        $this->configuration = array_merge( array(
            'profile_slug' => '',
            'association_post_types' => array('post', 'page'),
            'enqueue_css' => true,
            'enqueue_js' => true
        ), $this->configuration );
    }

    function init() {
        // Register the SpeakerDeck oembed
        wp_oembed_add_provider( '#https?://(www\.)?speakerdeck.com/.*/.*#', 'http://speakerdeck.com/oembed.json', true );

        wp_register_script( 'speakerdeck-admin', $this->url . '/js/speakerdeck-admin.js', array('jquery'), '1.0.0', true );
        wp_register_script( 'speakerdeck', $this->url . '/js/speakerdeck.js', array('jquery'), '1.0.0', true );

        wp_register_style( 'speakerdeck-admin', $this->url . '/css/speakerdeck-admin.css', array(), '1.0.0', 'all' );
        wp_register_style( 'speakerdeck', $this->url . '/css/speakerdeck.css', array(), '1.0.0', 'all' );

        if ( get_user_option('rich_editing') == 'true') {
            add_filter('mce_external_plugins', array(&$this, 'tinymce_plugin'));
            add_filter('mce_buttons', array(&$this, 'tinymce_button'));
        }
    }

    function register_settings(){
        $this->tabs[$this->keys->configuration] = __('Configuration', 'speakerdeck');
        
        register_setting( $this->keys->configuration, $this->keys->configuration, array(&$this, 'prepare_configuration') );
        add_settings_section( 'section_profile', __('Speaker Deck Profile', 'speakerdeck'), '__return_false', $this->keys->configuration );
        add_settings_field( 'profile_slug', __('Slug', 'speakerdeck'), array( &$this, 'field_configuration_profile_slug' ), $this->keys->configuration, 'section_profile' );
        add_settings_section( 'section_association', __('Association', 'speakerdeck'), '__return_false', $this->keys->configuration );
        add_settings_field( 'association_post_types', __('Post Types', 'speakerdeck'), array( &$this, 'field_configuration_association_post_types' ), $this->keys->configuration, 'section_association' );
        add_settings_section( 'section_visualization', __('Visualization', 'speakerdeck'), '__return_false', $this->keys->configuration );
        add_settings_field( 'enqueue_script_style', __('CSS and JavaScript', 'speakerdeck'), array( &$this, 'field_configuration_enqueue_script_style' ), $this->keys->configuration, 'section_visualization' );


        $this->tabs[$this->keys->items] = __('New Speak', 'speakerdeck');

        register_setting( $this->keys->items, $this->keys->items, array(&$this, 'prepare_items') );
        add_settings_section( 'section', '', '__return_false', $this->keys->items );
        add_settings_field( 'url', __('Page URL', 'speakerdeck'), array( &$this, 'field_items_url' ), $this->keys->items, 'section' );
        add_settings_field( 'title', __('Title', 'speakerdeck'), array( &$this, 'field_items_title' ), $this->keys->items, 'section' );
        add_settings_field( 'id', __('ID', 'speakerdeck'), array( &$this, 'field_items_id' ), $this->keys->items, 'section' );
        add_settings_field( 'ratio', __('Ratio', 'speakerdeck'), array( &$this, 'field_items_ratio' ), $this->keys->items, 'section' );
        add_settings_field( 'download', __('Download URL', 'speakerdeck'), array( &$this, 'field_items_download' ), $this->keys->items, 'section' );
        add_settings_field( 'total', __('Slides', 'speakerdeck'), array( &$this, 'field_items_total' ), $this->keys->items, 'section' );
    
        $this->tabs[$this->keys->speaks] = __('Speaks', 'speakerdeck');
    }

    function admin_menu() {
        add_menu_page('Speaker Deck', 'Speaker Deck', 'manage_options', $this->keys->menu, array(&$this, 'render_page'), 'div'); 
    }

    function wp_enqueue_scripts() {
        if(!is_admin()){
            if($this->configuration['enqueue_js'])
                wp_enqueue_script( 'speakerdeck' );
            if($this->configuration['enqueue_css'])
                wp_enqueue_style( 'speakerdeck' );
        }
    }

    function admin_enqueue_scripts() {
        wp_enqueue_script( 'speakerdeck-admin' );
        wp_localize_script( 'speakerdeck-admin', 'wpSpeakerDeckL10n', array(
            'form_title' => __('Speaker Deck Shortcode', 'speakerdeck')
        ) );
    }

    function admin_print_styles() {
        wp_enqueue_style( 'speakerdeck-admin' );
    }

    function add_meta_boxes() {
        add_meta_box( 
            'speakerdeck-associate',
            __( 'Speaker Deck Association', 'speakerdeck' ),
            array(&$this, 'render_metabox_association'),
            null,
            'side'
        );
    }

    function save_post($post_id){
        // verify if this is an auto save routine. 
        // If it is our form has not been submitted, so we dont want to do anything
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
            return;

        // verify this came from the our screen and with proper authorization,
        // because save_post can be triggered at other times

        if ( isset($_POST['speakerdeck-association']) && !wp_verify_nonce( $_POST['speakerdeck-association'], plugin_basename( __FILE__ ) ) )
            return;


        // Check permissions
        if ( 'page' == $this->admin_post_type() ) {
            if ( !current_user_can( 'edit_page', $post_id ) )
                return;
        } else {
            if ( !current_user_can( 'edit_post', $post_id ) )
                return;
        }

        // OK, we're authenticated: we need to find and save the data

        if (isset($_POST['speakerdeck-deck_id'])) {
            $old_value = get_post_meta($post_id, '_speakerdeck_id', true);
            if( empty($old_value) && empty($_POST['speakerdeck-deck_id']) )
                return;
            $deck_id = $_POST['speakerdeck-deck_id'];
            update_post_meta($post_id, '_speakerdeck_id', $deck_id);
        }

    }

    function tinymce_form() {
    ?>
    <div id='speakerdeck-tinymce-form' style='display:none'>
        <label><?php _e('Choose one of the Decks below:', 'speakerdeck'); ?></label>
        <select class='speakerdeck-form-items'>
        <?php 
            foreach ($this->items as $id => $deck) {
                echo "<option value='{$deck['id']}' data-deck='" . json_encode($deck) . "'>{$deck['title']}</option>";
            }
        ?>
        </select>
       
        <?php submit_button(__('Insert Shortcode'),'button-primary speakerdeck-insert', 'submit', false); ?>
    </div>
    <?php
    }

    function proxy_url() {
        $response = wp_remote_get( $_POST['url'] );
        if( is_wp_error( $response ) ) {
           echo json_encode($response);
        } else {
           echo $response['body'];
        }
        die();
    }

    function tinymce_plugin($plugin_array) {
        $plugin_array['speakerdeck'] = plugins_url( 'js/speakerdeck-tinymce.js', __FILE__ );
        return $plugin_array;
    }

    function tinymce_button($buttons) {
        array_push($buttons, "|", "speakerdeck");
        return $buttons;
    }

    function shortcode($atts, $content = null) {
        $settings = (object) shortcode_atts(
            array(
                "show" => 'slides',
                "id" => false,
                "link" => false
            ),
            $atts
        );

        $deck = (object) $this->items[$settings->id];
        if($settings->link===false)
            $link = $deck->url;
        else
            $link = $settings->link;
        if ($settings->show == 'scrub'){
            return "<a href='{$link}' class='speakerdeck-scrub' data-deck='" . json_encode($deck) . "'></a>";
        } else {
            $wp_oembed = _wp_oembed_get_object();
            return $wp_oembed->get_html($deck->url);
        }
    }

    // Prepare the settings of an item to the option
    function prepare_items($input){
        if (
            !empty($input['url']) &&
            !empty($input['title']) &&
            !empty($input['id']) &&
            !empty($input['ratio']) &&
            !empty($input['download']) &&
            !empty($input['total'])
        ) {
            $this->items[$input['id']] = $input;
        }
        return $this->items;
    }

    // Prepare the configuration settings
    function prepare_configuration($input) {
        if(!empty($input['association_post_types'])){
            $input['association_post_types'] = array_keys($input['association_post_types']);
        }
        $input['enqueue_js'] = (isset($input['enqueue_js'])?true:false);
        $input['enqueue_css'] = (isset($input['enqueue_css'])?true:false);

        return $input;
    }

    // Metabox Association Rendering
    function render_metabox_association( $post ) {
        // Use nonce for verification
        wp_nonce_field( plugin_basename( __FILE__ ), 'speakerdeck-association' );
        ?>
        <label><?php _e('Choose one of the Decks below:', 'speakerdeck'); ?></label>
        <select class='speakerdeck-form-items' name='speakerdeck-deck_id'>
            <option value=''></option>
        <?php 
            foreach ($this->items as $id => $deck) {
                echo "<option" . ( $deck['id'] == get_post_meta($post->ID, '_speakerdeck_id', true)?" selected='selected'":'') . " value='{$deck['id']}' data-deck='" . json_encode($deck) . "'>{$deck['title']}</option>";
            }
        ?>
        </select>
        <?php
    }

    // Page Rendering
    function render_page(){
        $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->keys->configuration;
        ?>
        <div class="wrap">
            <?php
            screen_icon('speakerdeck');
            echo '<h2 class="nav-tab-wrapper">';
            foreach ( $this->tabs as $key => $label ) {
                $active = $tab == $key ? 'nav-tab-active' : '';
                echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->keys->menu . '&tab=' . $key . '">' . $label . '</a>'; 
            }
            echo '</h2>';
            if ($tab != $this->keys->speaks){
            ?>
                <form method="post" action="options.php">
                    <?php wp_nonce_field( 'update-options' ); ?>
                    <?php settings_fields( $tab ); ?>
                    <?php do_settings_sections( $tab ); ?>
                    <?php submit_button(); ?>
                </form>
            <?php 
            } else {
                foreach ($this->items as $key => $speak) {
                    echo do_shortcode("[speakerdeck show='scrub' url='{$speak['url']}' id='{$speak['id']}' total='{$speak['total']}' ratio='{$speak['ratio']}' download='{$speak['download']}']");
                }
                echo "<pre>" . print_r($this->items, true) . "</pre>";
            }
            ?>
        </div>
        <?php
    }

    // Configuration tab fields
    function field_configuration_profile_slug(){
        $slug = 'profile_slug';
        ?>
        <input id='speakerdeck-field-<?php echo $slug; ?>' type="text" name="<?php echo "{$this->keys->configuration}[{$slug}]"; ?>" value="<?php echo esc_attr($this->configuration[$slug]); ?>" class="regular-text" placeholder='<?php _e("e.g. webord",'speakerdeck'); ?>' />
        <p class="description"><?php _e('The slug of your Speaker Deck profile, not your name.'); ?></p>
        <?php
    }

    function field_configuration_association_post_types(){
        $slug = 'association_post_types';
        $post_types = get_post_types(array(
            'public'   => true,
        ),'objects');
        foreach ($post_types as $key => $cpt) {
            if ($cpt->name === 'attachment')
                continue;
            echo 
                "<label for='{$this->keys->configuration}[{$slug}][{$cpt->name}]'>" .
                "<input id='{$this->keys->configuration}[{$slug}][{$cpt->name}]' type='checkbox' name='{$this->keys->configuration}[{$slug}][{$cpt->name}]'" . (in_array($cpt->name, $this->configuration[$slug])?" checked='checked'":'') . " />" .
                " {$cpt->label}</label><br />";
        }
        ?>
        <p class="description"><?php _e('Choose what custom post types should have a metabox allowing the post association with a Speaker Deck item'); ?></p>
        <?php
    }

    function field_configuration_enqueue_script_style(){
        $slug = 'enqueue_script_style';
        ?>
        <label for='speakerdeck-field-<?php echo $slug; ?>-js'>
        <input id='speakerdeck-field-<?php echo $slug; ?>-js' type="checkbox" name="<?php echo "{$this->keys->configuration}[enqueue_js]"; ?>" <?php echo ($this->configuration['enqueue_js']?"checked='checked' ":''); ?>/>
         Use JavaScript <small>(not recommended)</small></label><br />
        <label for='speakerdeck-field-<?php echo $slug; ?>-css'>
        <input id='speakerdeck-field-<?php echo $slug; ?>-css' type="checkbox" name="<?php echo "{$this->keys->configuration}[enqueue_css]"; ?>" <?php echo ($this->configuration['enqueue_css']?"checked='checked' ":''); ?>/>
         Use CSS</label><br />
        <p class="description"><?php _e("If you don't want to use the default CSS and JS, use with caution!", 'speakerdeck'); ?></p>
        <?php
    }

    // New Speaks tab fields
    function field_items_url() {
        $slug = 'url';
        ?>
        <input id='speakerdeck-field-<?php echo $slug; ?>' type="text" name="<?php echo "{$this->keys->items}[{$slug}]"; ?>" value="" class="regular-text" />
        <p class="description"><?php echo sprintf("%s <a href='#enable-fields' id='speakerdeck-enable-fields'>%s</a>", __('Paste the url above and I will try to populate the rest of the fields or', 'speakerdeck'), __('enable the fields', 'speakerdeck')); ?></p>
        <?php
    }
    
    function field_items_title() {
        $slug = 'title';
        ?>
        <input id='speakerdeck-field-<?php echo $slug; ?>' type="text" name="<?php echo "{$this->keys->items}[{$slug}]"; ?>" value="" class="" disabled='disabled' />
        <?php
    }
    
    function field_items_id() {
        $slug = 'id';
        ?>
        <input id='speakerdeck-field-<?php echo $slug; ?>' type="text" name="<?php echo "{$this->keys->items}[{$slug}]"; ?>" value="" class="" disabled='disabled' />
        <?php
    }
    
    function field_items_ratio() {
        $slug = 'ratio';
        ?>
        <input id='speakerdeck-field-<?php echo $slug; ?>' type="text" name="<?php echo "{$this->keys->items}[{$slug}]"; ?>" value="" class="small-text" disabled='disabled' />
        <?php
    }
    
    function field_items_download() {
        $slug = 'download';
        ?>
        <input id='speakerdeck-field-<?php echo $slug; ?>' type="text" name="<?php echo "{$this->keys->items}[{$slug}]"; ?>" value="" class="regular-text" disabled='disabled' />
        <?php
    }
    
    function field_items_total() {
        $slug = 'total';
        ?>
        <input id='speakerdeck-field-<?php echo $slug; ?>' type="text" name="<?php echo "{$this->keys->items}[{$slug}]"; ?>" value="" class='small-text' disabled='disabled' />
        <?php
    }
    

    // Utils
    function admin_post_type() {
        global $post, $typenow, $current_screen;

        //we have a post so we can just get the post type from that
        if ( $post && $post->post_type )
            return $post->post_type;

        //check the global $typenow - set in admin.php
        elseif( $typenow )
            return $typenow;

        //check the global $current_screen object - set in sceen.php
        elseif( $current_screen && $current_screen->post_type )
            return $current_screen->post_type;

        //lastly check the post_type querystring
        elseif( isset( $_REQUEST['post_type'] ) )
            return sanitize_key( $_REQUEST['post_type'] );

        //we do not know the post type!
        return null;
    }
};

add_action( 'plugins_loaded', function() {
    $speakerdeck = new SpeakerDeck;
});