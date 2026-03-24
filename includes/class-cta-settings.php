<?php
defined('ABSPATH') || exit;

class ICTA_Settings {

    const OPTION = 'icta_settings';

    public function __construct() {
        add_action('admin_menu',            [$this, 'add_menu']);
        add_action('admin_init',            [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu(): void {
        add_options_page(
            'Blog Lead Magnet',
            'Blog Lead Magnet',
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
        wp_enqueue_media();
        wp_enqueue_script('icta-admin', ICTA_URL . 'assets/js/admin.js', ['wp-color-picker', 'jquery'], ICTA_VERSION, true);
        wp_enqueue_style('icta-admin', ICTA_URL . 'assets/css/admin.css', [], ICTA_VERSION);
    }

    public function sanitize(array $input): array {
        $existing = get_option(self::OPTION, []);

        // Only sanitize keys present in the submitted form (active tab only)
        foreach ($input as $key => $raw) {
            if (!is_array($raw)) continue;

            // Determine position from key suffix
            $pos = null;
            foreach (['cta1', 'cta2', 'cta3', 'gate'] as $p) {
                if (str_ends_with($key, "_{$p}")) { $pos = $p; break; }
            }
            if (!$pos) continue;

            $existing[$key] = [
                'enabled'     => !empty($raw['enabled']),
                'label'       => sanitize_text_field($raw['label']       ?? ''),
                'headline'    => sanitize_text_field($raw['headline']    ?? ''),
                'subheadline' => sanitize_text_field($raw['subheadline'] ?? ''),
                'desc'        => sanitize_textarea_field($raw['desc']    ?? ''),
                'btn_text'    => sanitize_text_field($raw['btn_text']    ?? ''),
                'btn_url'     => esc_url_raw($raw['btn_url']             ?? ''),
                'bg_color'    => sanitize_hex_color($raw['bg_color']     ?? '') ?: '#18181b',
                'btn_color'   => sanitize_hex_color($raw['btn_color']    ?? '') ?: '#e22007',
                'text_color'  => sanitize_hex_color($raw['text_color']   ?? ''),
                'image_url'   => esc_url_raw($raw['image_url']           ?? ''),
                'shortcode'   => sanitize_text_field($raw['shortcode']   ?? ''),
                'h2_trigger'  => max(1, (int)($raw['h2_trigger']         ?? 0)),
            ];
        }

        return $existing;
    }

    public static function defaults(string $pos): array {
        $defaults = [
            'cta1' => [
                'enabled'     => false,
                'label'       => '',
                'headline'    => 'Potrzebujesz pomocy z tym tematem?',
                'subheadline' => '',
                'desc'        => 'Pomagam firmom wdrażać nowoczesne rozwiązania. Umów bezpłatną rozmowę.',
                'btn_text'    => 'Umów bezpłatną rozmowę →',
                'btn_url'     => 'https://cal.com/%C5%82ukasz-important/30min',
                'bg_color'    => '#18181b',
                'btn_color'   => '#e22007',
                'text_color'  => '#ffffff',
                'image_url'   => '',
                'shortcode'   => '',
                'h2_trigger'  => 2,
            ],
            'cta2' => [
                'enabled'     => false,
                'label'       => '',
                'headline'    => 'Zrób to z ekspertem',
                'subheadline' => 'Bezpłatna 30-minutowa konsultacja.',
                'desc'        => '',
                'btn_text'    => 'Zarezerwuj termin →',
                'btn_url'     => 'https://cal.com/%C5%82ukasz-important/30min',
                'bg_color'    => '#18181b',
                'btn_color'   => '#e22007',
                'text_color'  => '#ffffff',
                'image_url'   => '',
                'shortcode'   => '',
                'h2_trigger'  => 4,
            ],
            'cta3' => [
                'enabled'     => false,
                'label'       => '',
                'headline'    => 'Pobierz bezpłatny materiał',
                'subheadline' => 'Zostaw email i dostaniesz go od razu.',
                'desc'        => '',
                'btn_text'    => '',
                'btn_url'     => '',
                'bg_color'    => '#18181b',
                'btn_color'   => '#e22007',
                'text_color'  => '#ffffff',
                'image_url'   => '',
                'shortcode'   => '[fluentform id="4"]',
                'h2_trigger'  => 0,
            ],
            'gate' => [
                'enabled'     => false,
                'label'       => 'Treść premium',
                'headline'    => 'Czytaj dalej — za darmo',
                'subheadline' => 'Zostaw email, a reszta artykułu odblokuje się natychmiast.',
                'desc'        => '',
                'btn_text'    => '',
                'btn_url'     => '',
                'bg_color'    => '#18181b',
                'btn_color'   => '#e22007',
                'text_color'  => '#ffffff',
                'image_url'   => '',
                'shortcode'   => '[fluentform id="4"]',
                'h2_trigger'  => 3,
            ],
        ];
        return $defaults[$pos] ?? [];
    }

    public static function get(string $category_slug, string $pos): array {
        $all  = get_option(self::OPTION, []);
        $key  = "{$category_slug}_{$pos}";
        $gkey = "_global_{$pos}";

        // Category override only if it exists AND is enabled
        if (isset($all[$key]) && !empty($all[$key]['enabled'])) {
            return array_merge(self::defaults($pos), $all[$key]);
        }

        // Global fallback
        $data = $all[$gkey] ?? self::defaults($pos);
        return array_merge(self::defaults($pos), $data);
    }

    public function render_page(): void {
        $categories = get_categories(['hide_empty' => false]);
        $settings   = get_option(self::OPTION, []);
        $active_tab = sanitize_key($_GET['tab'] ?? '_global');
        ?>
        <div class="wrap icta-wrap">
            <h1>Blog Lead Magnet <span class="icta-version">v<?= ICTA_VERSION ?></span></h1>
            <p class="icta-desc">Skonfiguruj 3 bloki CTA / lead magnet wstrzykiwane automatycznie w artykuły — osobno dla każdej kategorii.</p>

            <nav class="nav-tab-wrapper icta-tabs">
                <a href="?page=important-cta&tab=_global"
                   class="nav-tab <?= $active_tab === '_global' ? 'nav-tab-active' : '' ?>">
                    Globalne (fallback)
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

                <?php // Only render the active tab — prevents overwriting other tabs on save ?>
                <div class="icta-tab-panel is-active">
                    <?php $this->render_cta_fields($active_tab, $settings); ?>
                </div>

                <?php submit_button('Zapisz ustawienia'); ?>
            </form>
        </div>
        <?php
    }

    private function render_cta_fields(string $slug, array $settings): void {
        $positions = [
            'cta1' => [
                'label'         => 'CTA 1 — Expert Block',
                'desc'          => 'Wstrzykiwany przed wybranym H2 (domyślnie 2.)',
                'has_image'     => true,
                'has_desc'      => true,
                'has_shortcode' => false,
                'has_trigger'   => true,
            ],
            'cta2' => [
                'label'         => 'CTA 2 — Mini Nudge',
                'desc'          => 'Kompaktowy baner przed wybranym H2 (domyślnie 4.)',
                'has_image'     => false,
                'has_desc'      => false,
                'has_shortcode' => false,
                'has_trigger'   => true,
            ],
            'cta3' => [
                'label'         => 'CTA 3 — Lead Magnet',
                'desc'          => 'Formularz / newsletter na końcu artykułu',
                'has_image'     => false,
                'has_desc'      => false,
                'has_shortcode' => true,
                'has_trigger'   => false,
            ],
            'gate' => [
                'label'         => 'Content Gate',
                'desc'          => 'Ukrywa treść od wybranego H2 — czytelnik podaje email, żeby odblokować. Można też włączyć per-artykuł checkboxem.',
                'has_image'     => false,
                'has_desc'      => false,
                'has_shortcode' => true,
                'has_trigger'   => true,
            ],
        ];

        foreach ($positions as $pos => $config) :
            $key = "{$slug}_{$pos}";
            $d   = array_merge(self::defaults($pos), $settings[$key] ?? []);
            $n   = self::OPTION . "[{$key}]";
            ?>
            <div class="icta-block">
                <div class="icta-block-header">
                    <div>
                        <h2><?= esc_html($config['label']) ?></h2>
                        <span class="icta-block-desc"><?= esc_html($config['desc']) ?></span>
                    </div>
                    <label class="icta-toggle">
                        <input type="checkbox" name="<?= $n ?>[enabled]" value="1" <?= checked($d['enabled'], true, false) ?>>
                        <span>Włączony</span>
                    </label>
                </div>
                <div class="icta-block-fields">

                    <?php if ($config['has_trigger']) : ?>
                    <div class="icta-row icta-row--trigger">
                        <label>Przed którym H2? <small>(np. 2 = wstrzyknij przed 2. nagłówkiem H2)</small></label>
                        <input type="number" name="<?= $n ?>[h2_trigger]" value="<?= (int)$d['h2_trigger'] ?>" min="1" max="20" step="1">
                    </div>
                    <?php endif; ?>

                    <div class="icta-row">
                        <label>Etykieta <small>(mały badge nad nagłówkiem, opcjonalnie)</small></label>
                        <input type="text" name="<?= $n ?>[label]" value="<?= esc_attr($d['label']) ?>" placeholder="np. Ekspert radzi">
                    </div>

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
                        <label>Avatar / obrazek</label>
                        <div class="icta-media-group">
                            <?php if ($d['image_url']) : ?>
                            <img src="<?= esc_url($d['image_url']) ?>" class="icta-img-preview" alt="">
                            <?php else : ?>
                            <img src="" class="icta-img-preview" alt="" style="display:none">
                            <?php endif; ?>
                            <div class="icta-media-inputs">
                                <input type="url" id="icta-img-<?= esc_attr($key) ?>" name="<?= $n ?>[image_url]" value="<?= esc_attr($d['image_url']) ?>" placeholder="https://..." class="icta-img-url">
                                <button type="button" class="button icta-media-btn" data-target="icta-img-<?= esc_attr($key) ?>">Wybierz z biblioteki</button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($config['has_shortcode']) : ?>
                    <div class="icta-row">
                        <label>Shortcode formularza <small>(np. [fluentform id="4"])</small></label>
                        <input type="text" name="<?= $n ?>[shortcode]" value="<?= esc_attr($d['shortcode']) ?>" placeholder='[fluentform id="4"]'>
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
                        <div>
                            <label>Kolor tekstu <small>(puste = automatyczny)</small></label>
                            <input type="text" name="<?= $n ?>[text_color]" value="<?= esc_attr($d['text_color']) ?>" class="icta-color-picker" data-alpha-enabled="true">
                        </div>
                    </div>

                </div>
            </div>
            <?php
        endforeach;
    }
}
