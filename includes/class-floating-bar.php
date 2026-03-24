<?php
defined('ABSPATH') || exit;

class ICTA_Floating_Bar {

    const OPTION = 'icta_floating';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin']);
        add_action('wp_footer', [$this, 'render'], 50);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public static function defaults(): array {
        return [
            'enabled'       => false,
            'mode'          => 'both',       // 'both', 'cta_only', 'toc_only'
            'btn_text'      => 'Umów się',
            'btn_url'       => 'https://cal.com/%C5%82ukasz-important/30min',
            'author_name'   => 'Łukasz Ślusarski',
            'author_role'   => 'Ekspert UX',
            'author_avatar' => '',
            'bar_bg'        => '#ffffff',
            'btn_color'     => '#2563eb',
        ];
    }

    public static function get(): array {
        return array_merge(self::defaults(), get_option(self::OPTION, []));
    }

    // ── Admin ─────────────────────────────────────

    public function add_menu(): void {
        add_options_page(
            'BLM Pływający pasek',
            'BLM Pływający pasek',
            'manage_options',
            'icta-floating',
            [$this, 'render_admin']
        );
    }

    public function register_settings(): void {
        register_setting('icta_floating_group', self::OPTION, [
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
    }

    public function enqueue_admin(string $hook): void {
        if ($hook !== 'settings_page_icta-floating') return;
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();
        wp_enqueue_script('icta-admin', ICTA_URL . 'assets/js/admin.js', ['wp-color-picker', 'jquery'], ICTA_VERSION, true);
        wp_enqueue_style('icta-admin', ICTA_URL . 'assets/css/admin.css', [], ICTA_VERSION);
    }

    public function sanitize(array $input): array {
        $modes = ['both', 'cta_only', 'toc_only'];
        return [
            'enabled'       => !empty($input['enabled']),
            'mode'          => in_array($input['mode'] ?? '', $modes, true) ? $input['mode'] : 'both',
            'btn_text'      => sanitize_text_field($input['btn_text'] ?? ''),
            'btn_url'       => esc_url_raw($input['btn_url'] ?? ''),
            'author_name'   => sanitize_text_field($input['author_name'] ?? ''),
            'author_role'   => sanitize_text_field($input['author_role'] ?? ''),
            'author_avatar' => esc_url_raw($input['author_avatar'] ?? ''),
            'bar_bg'        => sanitize_hex_color($input['bar_bg'] ?? '') ?: '#ffffff',
            'btn_color'     => sanitize_hex_color($input['btn_color'] ?? '') ?: '#2563eb',
        ];
    }

    public function render_admin(): void {
        $d = self::get();
        $n = self::OPTION;
        ?>
        <div class="wrap icta-wrap">
            <h1>BLM Pływający pasek <span class="icta-version">v<?= ICTA_VERSION ?></span></h1>
            <p class="icta-desc">Pasek na dole ekranu z przyciskiem CTA i/lub spisem treści — widoczny na artykułach.</p>

            <form method="post" action="options.php" class="icta-form">
                <?php settings_fields('icta_floating_group'); ?>

                <div class="icta-block">
                    <div class="icta-block-header">
                        <div>
                            <h2>Konfiguracja</h2>
                        </div>
                        <label class="icta-toggle">
                            <input type="checkbox" name="<?= $n ?>[enabled]" value="1" <?= checked($d['enabled'], true, false) ?>>
                            <span>Włączony</span>
                        </label>
                    </div>
                    <div class="icta-block-fields">

                        <div class="icta-row">
                            <label>Tryb wyświetlania</label>
                            <select name="<?= $n ?>[mode]" style="max-width:300px">
                                <option value="both" <?= selected($d['mode'], 'both', false) ?>>Przycisk CTA + Spis treści</option>
                                <option value="cta_only" <?= selected($d['mode'], 'cta_only', false) ?>>Tylko przycisk CTA</option>
                                <option value="toc_only" <?= selected($d['mode'], 'toc_only', false) ?>>Tylko Spis treści</option>
                            </select>
                        </div>

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

                        <div class="icta-row icta-row--half">
                            <div>
                                <label>Imię autora</label>
                                <input type="text" name="<?= $n ?>[author_name]" value="<?= esc_attr($d['author_name']) ?>">
                            </div>
                            <div>
                                <label>Rola / tytuł</label>
                                <input type="text" name="<?= $n ?>[author_role]" value="<?= esc_attr($d['author_role']) ?>">
                            </div>
                        </div>

                        <div class="icta-row">
                            <label>Avatar autora</label>
                            <div class="icta-media-group">
                                <?php if ($d['author_avatar']) : ?>
                                <img src="<?= esc_url($d['author_avatar']) ?>" class="icta-img-preview" alt="">
                                <?php else : ?>
                                <img src="" class="icta-img-preview" alt="" style="display:none">
                                <?php endif; ?>
                                <div class="icta-media-inputs">
                                    <input type="url" id="icta-img-floating-avatar" name="<?= $n ?>[author_avatar]" value="<?= esc_attr($d['author_avatar']) ?>" placeholder="https://..." class="icta-img-url">
                                    <button type="button" class="button icta-media-btn" data-target="icta-img-floating-avatar">Wybierz z biblioteki</button>
                                </div>
                            </div>
                        </div>

                        <div class="icta-row icta-row--colors">
                            <div>
                                <label>Kolor tła paska</label>
                                <input type="text" name="<?= $n ?>[bar_bg]" value="<?= esc_attr($d['bar_bg']) ?>" class="icta-color-picker">
                            </div>
                            <div>
                                <label>Kolor przycisku</label>
                                <input type="text" name="<?= $n ?>[btn_color]" value="<?= esc_attr($d['btn_color']) ?>" class="icta-color-picker">
                            </div>
                        </div>

                    </div>
                </div>

                <?php submit_button('Zapisz ustawienia'); ?>
            </form>
        </div>
        <?php
    }

    // ── Frontend ──────────────────────────────────

    public function enqueue_assets(): void {
        if (!is_singular('post')) return;
        $d = self::get();
        if (!$d['enabled']) return;

        wp_enqueue_style('icta-floating', ICTA_URL . 'assets/css/floating-bar.css', [], ICTA_VERSION);
        wp_enqueue_script('icta-floating', ICTA_URL . 'assets/js/floating-bar.js', [], ICTA_VERSION, true);
    }

    public function render(): void {
        if (!is_singular('post') || is_admin()) return;
        $d = self::get();
        if (!$d['enabled']) return;

        $show_cta = in_array($d['mode'], ['both', 'cta_only'], true);
        $show_toc = in_array($d['mode'], ['both', 'toc_only'], true);

        $bar_style = 'background:' . esc_attr($d['bar_bg']) . ';';
        $btn_style = 'background:' . esc_attr($d['btn_color']) . ';';

        $initials = '';
        $name_parts = explode(' ', $d['author_name']);
        foreach ($name_parts as $part) {
            if ($part) $initials .= mb_substr($part, 0, 1);
        }
        ?>
        <!-- BLM Floating Bar -->
        <div class="blm-float" id="blm-float" aria-expanded="false" style="<?= $bar_style ?>">
          <div class="blm-float__bar">
            <div class="blm-float__inner">

              <?php if ($show_cta) : ?>
              <div class="blm-float__expert">
                <div class="blm-float__avatar" aria-hidden="true">
                  <?php if ($d['author_avatar']) : ?>
                  <img src="<?= esc_url($d['author_avatar']) ?>" alt="<?= esc_attr($d['author_name']) ?>">
                  <?php else : ?>
                  <?= esc_html($initials) ?>
                  <?php endif; ?>
                </div>
                <div class="blm-float__info">
                  <span class="blm-float__name"><?= esc_html($d['author_name']) ?></span>
                  <span class="blm-float__role"><?= esc_html($d['author_role']) ?></span>
                </div>
                <a href="<?= esc_url($d['btn_url']) ?>" class="blm-float__btn" style="<?= $btn_style ?>" target="_blank" rel="noopener" onclick="event.stopPropagation()">
                  <?= esc_html($d['btn_text']) ?>
                </a>
              </div>
              <?php endif; ?>

              <?php if ($show_cta && $show_toc) : ?>
              <div class="blm-float__sep" aria-hidden="true"></div>
              <?php endif; ?>

              <?php if ($show_toc) : ?>
              <div class="blm-float__toc-area">
                <div class="blm-float__panel">
                  <div class="blm-float__panel-inner">
                    <ol class="blm-float__toc-list" id="blm-toc-list"></ol>
                  </div>
                </div>
                <button class="blm-float__toc-toggle" onclick="toggleBlmFloat()" aria-label="Otwórz spis treści">
                  <div class="blm-float__toc-text">
                    <span class="blm-float__toc-label">Spis treści</span>
                    <span class="blm-float__toc-active" id="blm-toc-active">—</span>
                  </div>
                  <svg class="blm-float__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                    <polyline points="18 15 12 9 6 15"/>
                  </svg>
                </button>
              </div>
              <?php endif; ?>

            </div>
          </div>
        </div>
        <?php
    }
}
