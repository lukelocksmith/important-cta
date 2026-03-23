<?php
defined('ABSPATH') || exit;

class ICTA_Settings {

    const OPTION = 'icta_settings';

    public function __construct() {
        add_action('admin_menu',       [$this, 'add_menu']);
        add_action('admin_init',       [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu(): void {
        add_options_page(
            'Important CTA',
            'Important CTA',
            'manage_options',
            'important-cta',
            [$this, 'render_page']
        );
    }

    public function register_settings(): void {
        register_setting('icta_group', self::OPTION, [
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'settings_page_important-cta') return;
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('icta-admin', ICTA_URL . 'assets/js/admin.js', ['wp-color-picker'], ICTA_VERSION, true);
        wp_enqueue_style('icta-admin', ICTA_URL . 'assets/css/admin.css', [], ICTA_VERSION);
    }

    public function sanitize(array $input): array {
        $clean = [];
        $categories = get_categories(['hide_empty' => false]);
        $slugs = array_merge(['_global'], array_column($categories, 'slug'));

        foreach ($slugs as $slug) {
            foreach (['cta1', 'cta2', 'cta3'] as $pos) {
                $key = "{$slug}_{$pos}";
                $raw = $input[$key] ?? [];

                $clean[$key]['enabled']     = !empty($raw['enabled']);
                $clean[$key]['headline']    = sanitize_text_field($raw['headline']    ?? '');
                $clean[$key]['subheadline'] = sanitize_text_field($raw['subheadline'] ?? '');
                $clean[$key]['desc']        = sanitize_textarea_field($raw['desc']    ?? '');
                $clean[$key]['btn_text']    = sanitize_text_field($raw['btn_text']    ?? '');
                $clean[$key]['btn_url']     = esc_url_raw($raw['btn_url']             ?? '');
                $clean[$key]['bg_color']    = sanitize_hex_color($raw['bg_color']     ?? '') ?: '#f8f8f8';
                $clean[$key]['btn_color']   = sanitize_hex_color($raw['btn_color']    ?? '') ?: '#e22007';
                $clean[$key]['image_url']   = esc_url_raw($raw['image_url']           ?? '');
                $clean[$key]['shortcode']   = sanitize_text_field($raw['shortcode']   ?? '');
            }
        }
        return $clean;
    }

    // Default config for a blank CTA
    public static function defaults(string $pos): array {
        $defaults = [
            'cta1' => [
                'enabled'     => false,
                'headline'    => 'Potrzebujesz pomocy z tym tematem?',
                'subheadline' => '',
                'desc'        => 'Pomagam firmom wdrażać nowoczesne rozwiązania. Umów bezpłatną rozmowę.',
                'btn_text'    => 'Umów bezpłatną rozmowę →',
                'btn_url'     => 'https://cal.com/%C5%82ukasz-important/30min',
                'bg_color'    => '#f9f6f5',
                'btn_color'   => '#e22007',
                'image_url'   => '',
                'shortcode'   => '',
            ],
            'cta2' => [
                'enabled'     => false,
                'headline'    => 'Zrób to z ekspertem',
                'subheadline' => 'Bezpłatna 30-minutowa konsultacja.',
                'desc'        => '',
                'btn_text'    => 'Zarezerwuj termin →',
                'btn_url'     => 'https://cal.com/%C5%82ukasz-important/30min',
                'bg_color'    => '#f9f6f5',
                'btn_color'   => '#e22007',
                'image_url'   => '',
                'shortcode'   => '',
            ],
            'cta3' => [
                'enabled'     => false,
                'headline'    => 'Pobierz bezpłatny materiał',
                'subheadline' => 'Zostaw email i dostaniesz go od razu.',
                'desc'        => '',
                'btn_text'    => '',
                'btn_url'     => '',
                'bg_color'    => '#f9f6f5',
                'btn_color'   => '#e22007',
                'image_url'   => '',
                'shortcode'   => '[fluentform id="1"]',
            ],
        ];
        return $defaults[$pos] ?? [];
    }

    public static function get(string $category_slug, string $pos): array {
        $all  = get_option(self::OPTION, []);
        $key  = "{$category_slug}_{$pos}";
        $gkey = "_global_{$pos}";

        // Category-specific → fallback to global → fallback to code defaults
        $data = $all[$key] ?? $all[$gkey] ?? self::defaults($pos);
        return array_merge(self::defaults($pos), $data);
    }

    public function render_page(): void {
        $categories = get_categories(['hide_empty' => false]);
        $settings   = get_option(self::OPTION, []);
        $active_tab = sanitize_key($_GET['tab'] ?? '_global');
        ?>
        <div class="wrap icta-wrap">
            <h1>Important CTA <span class="icta-version">v<?= ICTA_VERSION ?></span></h1>
            <p class="icta-desc">Skonfiguruj 3 bloki CTA wstrzykiwane automatycznie w artykuły — osobno dla każdej kategorii.</p>

            <nav class="nav-tab-wrapper icta-tabs">
                <a href="?page=important-cta&tab=_global"
                   class="nav-tab <?= $active_tab === '_global' ? 'nav-tab-active' : '' ?>">
                    🌐 Globalne (fallback)
                </a>
                <?php foreach ($categories as $cat) : ?>
                <a href="?page=important-cta&tab=<?= esc_attr($cat->slug) ?>"
                   class="nav-tab <?= $active_tab === $cat->slug ? 'nav-tab-active' : '' ?>">
                    <?= esc_html($cat->name) ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <form method="post" action="options.php" class="icta-form">
                <?php settings_fields('icta_group'); ?>

                <?php foreach (['_global', ...array_column($categories, 'slug')] as $slug) : ?>
                <div class="icta-tab-panel <?= $active_tab === $slug ? 'is-active' : '' ?>">
                    <?php $this->render_cta_fields($slug, $settings); ?>
                </div>
                <?php endforeach; ?>

                <?php submit_button('Zapisz ustawienia'); ?>
            </form>
        </div>
        <?php
    }

    private function render_cta_fields(string $slug, array $settings): void {
        $positions = [
            'cta1' => ['label' => 'CTA 1 — Expert Block (po 2. H2)', 'has_image' => true, 'has_desc' => true, 'has_shortcode' => false],
            'cta2' => ['label' => 'CTA 2 — Mini Nudge (~60-70% artykułu)', 'has_image' => false, 'has_desc' => false, 'has_shortcode' => false],
            'cta3' => ['label' => 'CTA 3 — Lead Magnet (koniec artykułu)', 'has_image' => false, 'has_desc' => false, 'has_shortcode' => true],
        ];

        foreach ($positions as $pos => $config) :
            $key = "{$slug}_{$pos}";
            $d   = array_merge(self::defaults($pos), $settings[$key] ?? []);
            $n   = self::OPTION . "[{$key}]";
            ?>
            <div class="icta-block">
                <div class="icta-block-header">
                    <h2><?= esc_html($config['label']) ?></h2>
                    <label class="icta-toggle">
                        <input type="checkbox" name="<?= $n ?>[enabled]" value="1" <?= checked($d['enabled'], true, false) ?>>
                        <span>Włączony</span>
                    </label>
                </div>
                <div class="icta-block-fields">
                    <div class="icta-row">
                        <label>Nagłówek</label>
                        <input type="text" name="<?= $n ?>[headline]" value="<?= esc_attr($d['headline']) ?>">
                    </div>
                    <div class="icta-row">
                        <label>Podtytuł</label>
                        <input type="text" name="<?= $n ?>[subheadline]" value="<?= esc_attr($d['subheadline']) ?>">
                    </div>
                    <?php if ($config['has_desc']) : ?>
                    <div class="icta-row">
                        <label>Opis</label>
                        <textarea name="<?= $n ?>[desc]" rows="2"><?= esc_textarea($d['desc']) ?></textarea>
                    </div>
                    <?php endif; ?>
                    <?php if ($config['has_image']) : ?>
                    <div class="icta-row">
                        <label>URL obrazka / avatara</label>
                        <input type="url" name="<?= $n ?>[image_url]" value="<?= esc_attr($d['image_url']) ?>" placeholder="https://...">
                    </div>
                    <?php endif; ?>
                    <?php if ($config['has_shortcode']) : ?>
                    <div class="icta-row">
                        <label>Shortcode formularza <small>(np. [fluentform id="1"])</small></label>
                        <input type="text" name="<?= $n ?>[shortcode]" value="<?= esc_attr($d['shortcode']) ?>" placeholder='[fluentform id="1"]'>
                    </div>
                    <?php else : ?>
                    <div class="icta-row icta-row--half">
                        <div>
                            <label>Tekst przycisku</label>
                            <input type="text" name="<?= $n ?>[btn_text]" value="<?= esc_attr($d['btn_text']) ?>">
                        </div>
                        <div>
                            <label>URL przycisku</label>
                            <input type="url" name="<?= $n ?>[btn_url]" value="<?= esc_attr($d['btn_url']) ?>">
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="icta-row icta-row--colors">
                        <div>
                            <label>Kolor tła</label>
                            <input type="text" name="<?= $n ?>[bg_color]" value="<?= esc_attr($d['bg_color']) ?>" class="icta-color-picker">
                        </div>
                        <div>
                            <label>Kolor przycisku</label>
                            <input type="text" name="<?= $n ?>[btn_color]" value="<?= esc_attr($d['btn_color']) ?>" class="icta-color-picker">
                        </div>
                    </div>
                </div>
            </div>
            <?php
        endforeach;
    }
}
