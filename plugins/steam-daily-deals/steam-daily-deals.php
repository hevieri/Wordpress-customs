<?php
/**
 * Plugin Name: Steam Daily Deals (hevieri) - Visual Tweak (fixed final)
 * Description: Muestra ofertas del día de Steam como shortcode y widget. Solo muestra porcentaje de descuento; badge reducido y imágenes más visibles.
 * Version: 1.0.9-final
 * Author: hevieri
 * Text Domain: steam-daily-deals-hevieri
 * Update URI: false
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Steam_Daily_Deals {
    const TRANSIENT_KEY    = 'sdd_deals_cache';
    const OPTION_KEY       = 'sdd_settings';
    const HISTORY_OPTION   = 'sdd_deals_history';
    const CRON_HOOK        = 'sdd_refresh_cron';
    const EST_AVG_DURATION_HOURS = 24;

    private $regions = array(
        'AR' => array( 'label' => 'Argentina', 'flag' => '🇦🇷' ),
        'MX' => array( 'label' => 'México',    'flag' => '🇲🇽' ),
        'ES' => array( 'label' => 'España',    'flag' => '🇪🇸' ),
        'EU' => array( 'label' => 'Europa',    'flag' => '🇪🇺' ),
    );

    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( $this, 'init_hooks' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_shortcode( 'steam_daily_deals', array( $this, 'shortcode_render' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        add_action( self::CRON_HOOK, array( $this, 'refresh_deals_cron' ) );
        add_action( 'widgets_init', function(){ register_widget( 'SDD_Widget' ); } );
    }

    public function activate() {
        $defaults = array(
            'count' => 8,
            'hours' => 6,
            'min_discount' => 10,
            'debug' => 0,
            'est_avg_duration_hours' => self::EST_AVG_DURATION_HOURS,
        );
        if ( ! get_option( self::OPTION_KEY ) ) {
            add_option( self::OPTION_KEY, $defaults );
        }

        $settings = get_option( self::OPTION_KEY, $defaults );
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), $this->hours_to_cron_interval( $settings['hours'] ), self::CRON_HOOK );
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
        delete_transient( self::TRANSIENT_KEY );
    }

    public function init_hooks() {
        // placeholder para extensiones futuras
    }

    private function hours_to_cron_interval( $hours ) {
        if ( $hours <= 1 ) return 'hourly';
        if ( $hours <= 12 ) return 'twicedaily';
        return 'daily';
    }

    public function admin_menu() {
        add_options_page( 'Steam Daily Deals', 'Steam Daily Deals', 'manage_options', 'steam-daily-deals', array( $this, 'settings_page' ) );
    }

    public function register_settings() {
        register_setting( 'sdd_group', self::OPTION_KEY, array( $this, 'sanitize_settings' ) );
    }

    public function sanitize_settings( $input ) {
        $current = get_option( self::OPTION_KEY );
        $out = array();
        $out['count'] = max(1, intval( $input['count'] ?? 8 ));
        $out['hours'] = max(1, intval( $input['hours'] ?? 6 ));
        $out['min_discount'] = max(0, intval( $input['min_discount'] ?? 10 ));
        $out['debug'] = isset( $input['debug'] ) ? 1 : 0;
        $out['est_avg_duration_hours'] = max(1, intval( $input['est_avg_duration_hours'] ?? self::EST_AVG_DURATION_HOURS ));

        if ( $current && $current['hours'] != $out['hours'] ) {
            wp_clear_scheduled_hook( self::CRON_HOOK );
            wp_schedule_event( time(), $this->hours_to_cron_interval( $out['hours'] ), self::CRON_HOOK );
        }

        delete_transient( self::TRANSIENT_KEY );

        return $out;
    }

    public function settings_page() {
        $s = get_option( self::OPTION_KEY );
        if ( ! $s ) $s = array( 'count'=>8, 'hours'=>6, 'min_discount'=>10, 'debug'=>0, 'est_avg_duration_hours'=>self::EST_AVG_DURATION_HOURS );
        ?>
        <div class="wrap">
            <h1>Steam Daily Deals</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'sdd_group' ); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="sdd_count">Cantidad de juegos</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[count]" type="number" id="sdd_count" value="<?php echo esc_attr($s['count']); ?>" min="1" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sdd_hours">Refrescar cada (horas)</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[hours]" type="number" id="sdd_hours" value="<?php echo esc_attr($s['hours']); ?>" min="1" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sdd_min_discount">Descuento mínimo (%)</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[min_discount]" type="number" id="sdd_min_discount" value="<?php echo esc_attr($s['min_discount']); ?>" min="0" max="100" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sdd_est_avg">Estimación duración oferta (horas)</label></th>
                        <td><input name="<?php echo esc_attr(self::OPTION_KEY); ?>[est_avg_duration_hours]" type="number" id="sdd_est_avg" value="<?php echo esc_attr($s['est_avg_duration_hours'] ?? self::EST_AVG_DURATION_HOURS); ?>" min="1" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Modo Debug</th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[debug]" <?php checked(1, $s['debug']); ?> value="1" /> Habilitar logs en opción sdd_debug_log</label></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2>Logs (solo debug)</h2>
            <pre style="background:#fff;padding:10px;border:1px solid #ddd;"><?php echo esc_html( get_option( 'sdd_debug_log', 'Sin logs' ) ); ?></pre>
        </div>
        <?php
    }

    public function enqueue_assets() {
        wp_register_style( 'sdd_styles', plugins_url( 'assets/sdd.css', __FILE__ ), array(), '1.0.0' );
        wp_enqueue_style( 'sdd_styles' );

        $inline = '/* SDD — badge reducido, imágenes con contain y mayor max-height */
.sdd-grid{display:flex;flex-wrap:wrap;gap:18px;align-items:stretch}
.sdd-card{width:calc(25% - 18px);background:#0f1113;color:#fff;border-radius:10px;overflow:hidden;box-sizing:border-box;text-decoration:none;display:block;transition:transform .18s ease, box-shadow .18s ease;box-shadow:0 6px 20px rgba(2,6,23,0.45)}
.sdd-card:hover{transform:translateY(-6px);box-shadow:0 18px 40px rgba(2,6,23,0.6)}
.sdd-media{position:relative;display:block;overflow:hidden;background:#0b0c0d;display:flex;align-items:center;justify-content:center;border-bottom-left-radius:6px;border-bottom-right-radius:6px}
.sdd-media img{width:100%;height:auto;max-height:300px;object-fit:contain;background:#0b0c0d;display:block;transition:transform .28s ease;border-radius:6px;box-shadow:0 6px 18px rgba(2,6,23,0.35) inset}
.sdd-card:hover .sdd-media img{transform:scale(1.03)}
.sdd-body{padding:10px 12px 14px}
.sdd-title{font-size:13px;line-height:1.2;font-weight:600;margin:0 0 6px;color:#f6f7f8;max-height:2.4em;overflow:hidden;text-overflow:ellipsis}
.sdd-discount{font-size:13px;color:#fff;padding:5px 8px;border-radius:999px;font-weight:700;line-height:1;box-shadow:0 6px 18px rgba(0,0,0,0.35)}
.badge-wrap{position:absolute;top:10px;left:10px;z-index:3;display:inline-block;border-radius:999px;opacity:0.95}
.badge-wrap .sdd-discount{background:linear-gradient(135deg,#ff6b6b,#ffbd59)}
.sdd-region-list{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.sdd-region{font-size:12px;padding:4px 6px;background:rgba(255,255,255,0.03);border-radius:6px;display:flex;align-items:center;gap:6px}
.sdd-region .flag{font-size:14px}
.sdd-footer{margin-top:8px;font-size:11px;color:rgba(255,255,255,0.68)}
@media(max-width:1100px){.sdd-card{width:calc(33.333% - 18px)}}
@media(max-width:760px){.sdd-card{width:calc(50% - 18px)} .sdd-media img{max-height:200px}}
@media(max-width:420px){.sdd-card{width:100%} .sdd-media img{max-height:220px}}';

        wp_add_inline_style( 'sdd_styles', $inline );
    }

    public function shortcode_render( $atts ) {
        $atts = shortcode_atts( array( 'count' => false ), $atts, 'steam_daily_deals' );
        $settings = get_option( self::OPTION_KEY );
        $count = $atts['count'] ? intval($atts['count']) : ( $settings['count'] ?? 8 );

        $deals = $this->get_deals( $count );
        if ( is_wp_error( $deals ) ) {
            return '<div class="sdd-error">Error al obtener ofertas.</div>';
        }

        ob_start();
        echo '<div class="sdd-grid">';
        foreach ( $deals as $d ) {
            $title = esc_html( $d['name'] ?? 'Sin título' );
            $img = esc_url( $d['header_image'] ?? $d['small_capsule_image'] ?? '' );
            $discount_pct = isset($d['price_overview']['discount_percent']) ? intval($d['price_overview']['discount_percent']) : 0;
            $link = esc_url( $d['store_link'] ?? ( 'https://store.steampowered.com/app/' . intval($d['appid']) ) );

            echo '<a class="sdd-card" href="'. $link . '" target="_blank" rel="noopener noreferrer">';

            echo '<div class="sdd-media">';
            if ( $img ) {
                echo '<img src="'. $img .'" alt="'. $title .'" loading="lazy" decoding="async" />';
            } else {
                echo '<div style="width:100%;height:190px;display:flex;align-items:center;justify-content:center;background:#0b0c0d;color:#777">Sin imagen</div>';
            }

            if ( $discount_pct ) {
                echo '<div class="badge-wrap"><span class="sdd-discount">-'. esc_html( $discount_pct ) .'%</span></div>';
            }
            echo '</div>'; // .sdd-media

            echo '<div class="sdd-body">';
            echo '<div class="sdd-title">'. $title .'</div>';
            echo '<div class="sdd-footer">';
            if ( ! empty( $d['local_prices'] ) && is_array( $d['local_prices'] ) ) {
                echo '<div class="sdd-region-list">';
                $shown = 0;
                foreach ( $d['local_prices'] as $cc => $pdata ) {
                    if ( $shown >= 4 ) break;
                    $flag = esc_html( $pdata['flag'] ?? ( ($this->regions[strtoupper($cc)]['flag'] ?? '') ) );
                    $label = esc_html( $pdata['label'] ?? $cc );
                    echo '<div class="sdd-region"><span class="flag">'. $flag .'</span><span class="label" style="opacity:.9;margin-left:3px">'. $label .'</span></div>';
                    $shown++;
                }
                echo '</div>';
            }
            echo '</div>'; // .sdd-footer
            echo '</div>'; // .sdd-body

            echo '</a>';
        }
        echo '</div>';

        if ( $settings['debug'] ?? 0 ) {
            echo '<details><summary>RAW</summary><pre>'. esc_html( wp_json_encode( $deals, JSON_PRETTY_PRINT ) ) .'</pre></details>';
        }

        return ob_get_clean();
    }

    public function get_deals( $count = 8 ) {
        $cached = get_transient( self::TRANSIENT_KEY );
        if ( $cached ) return $cached;

        $url = 'https://store.steampowered.com/api/featuredcategories';
        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
        if ( is_wp_error( $response ) ) {
            $this->log_debug( 'wp_remote_get error: ' . $response->get_error_message() );
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            $this->log_debug( 'Empty body from featuredcategories' );
            return new WP_Error( 'empty', 'Empty response' );
        }

        $data = json_decode( $body, true );
        if ( ! $data || ! isset( $data['specials'] ) ) {
            $this->log_debug( 'Unexpected structure from featuredcategories' );
            return new WP_Error( 'structure', 'Unexpected response structure' );
        }

        $items = $data['specials']['items'] ?? array();
        $settings = get_option( self::OPTION_KEY );
        $min_discount = $settings['min_discount'] ?? 0;
        $filtered = array();

        $history = get_option( self::HISTORY_OPTION, array() );
        $now = time();

        foreach ( $items as $it ) {
            $discount = intval( $it['discount_percent'] ?? 0 );
            if ( $discount < $min_discount ) continue;

            $appid = $it['id'] ?? ($it['appid'] ?? 0);
            if ( ! $appid ) continue;

            $deal = array(
                'appid' => $appid,
                'name' => $it['name'] ?? '',
                'header_image' => $it['large_capsule_image'] ?? $it['header_image'] ?? '',
                'small_capsule_image' => $it['small_capsule_image'] ?? '',
                'price_overview' => array(
                    'discount_percent' => $discount,
                ),
                'store_link' => $it['url'] ?? ( 'https://store.steampowered.com/app/'.$appid ),
                'raw' => $it,
            );

            $h = $history[$appid] ?? null;
            if ( ! $h ) {
                $history[$appid] = array(
                    'first_seen_at'   => $now,
                    'last_seen_at'    => $now,
                    'removed_at'      => null,
                    'last_discount'   => $discount,
                );
            } else {
                $history[$appid]['last_seen_at'] = $now;
                $last_discount = intval( $history[$appid]['last_discount'] ?? 0 );
                if ( $last_discount > 0 && $discount == 0 ) {
                    $history[$appid]['removed_at'] = $now;
                }
                $history[$appid]['last_discount'] = $discount;
            }

            $deal['first_seen_at'] = $history[$appid]['first_seen_at'] ?? $now;
            $deal['removed_at'] = $history[$appid]['removed_at'] ?? null;

            $deal['local_prices'] = $it['local_prices'] ?? array();

            $filtered[] = $deal;
            if ( count( $filtered ) >= $count ) break;
        }

        update_option( self::HISTORY_OPTION, $history );

        $ttl_hours = $settings['hours'] ?? 6;
        set_transient( self::TRANSIENT_KEY, $filtered, $ttl_hours * HOUR_IN_SECONDS );

        return $filtered;
    }

    private function format_price_from_cents( $cents ) {
        if ( is_null( $cents ) ) return '';
        if ( $cents === 0 ) return 'Gratis';
        $v = $cents / 100;
        return '$' . number_format( $v, 2 );
    }

    public function refresh_deals_cron() {
        delete_transient( self::TRANSIENT_KEY );
        $this->get_deals();
    }

    private function log_debug( $msg ) {
        $s = get_option( self::OPTION_KEY );
        if ( ! $s || empty( $s['debug'] ) ) return;
        $prev = get_option( 'sdd_debug_log', '' );
        $time = date( 'Y-m-d H:i:s' );
        $entry = "[$time] $msg\n";
        update_option( 'sdd_debug_log', $entry . $prev );
    }
}

new Steam_Daily_Deals();

class SDD_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'sdd_widget',
            'Steam Daily Deals',
            array( 'description' => 'Muestra ofertas del día de Steam (descuentos)' )
        );
    }

    public function widget( $args, $instance ) {
        echo $args['before_widget'];
        $title = apply_filters( 'widget_title', $instance['title'] ?? 'Ofertas Steam' );
        if ( $title ) echo $args['before_title'] . $title . $args['after_title'];
        echo do_shortcode('[steam_daily_deals]');
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? 'Ofertas Steam';
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Título:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                name="<?php echo $this->get_field_name('title'); ?>" type="text"
                value="<?php echo esc_attr( $title ); ?>">
        </p>
        <?php
    }

    public function update( $new, $old ) {
        $out = array();
        $out['title'] = sanitize_text_field( $new['title'] ?? 'Ofertas Steam' );
        return $out;
    }
}

