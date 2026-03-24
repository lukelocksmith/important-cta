<?php
defined('ABSPATH') || exit;

class ICTA_Content_Gate {

    public function __construct() {
        add_filter('the_content', [$this, 'apply_gate'], 15); // Before CTA injection (priority 20)
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('save_post', [$this, 'save_meta_box']);
    }

    public function enqueue_assets(): void {
        if (!is_singular('post')) return;
        if (!$this->is_gated(get_the_ID())) return;

        wp_enqueue_style('icta-gate', ICTA_URL . 'assets/css/content-gate.css', [], ICTA_VERSION);
        wp_enqueue_script('icta-gate', ICTA_URL . 'assets/js/content-gate.js', [], ICTA_VERSION, true);
    }

    /**
     * Check if a post should be gated.
     * Gate is enabled if:
     * 1. Post has _icta_gate_enabled meta = '1', OR
     * 2. Post's category has gate enabled in plugin settings
     */
    public function is_gated(int $post_id): bool {
        // Already unlocked via cookie check is done in JS, not PHP
        // Here we just check if gating SHOULD apply

        // Per-post override
        if (get_post_meta($post_id, '_icta_gate_enabled', true) === '1') {
            return true;
        }

        // Per-category setting
        $categories = get_the_category($post_id);
        $cat_slug   = '';
        foreach ($categories as $cat) {
            if ($cat->slug !== 'uncategorized') { $cat_slug = $cat->slug; break; }
        }

        $gate = ICTA_Settings::get($cat_slug, 'gate');
        return !empty($gate['enabled']);
    }

    public function apply_gate(string $content): string {
        if (!is_singular('post') || is_admin()) return $content;

        $post_id = get_the_ID();
        if (!$this->is_gated($post_id)) return $content;

        // Get gate settings
        $categories = get_the_category($post_id);
        $cat_slug   = '';
        foreach ($categories as $cat) {
            if ($cat->slug !== 'uncategorized') { $cat_slug = $cat->slug; break; }
        }
        $gate = ICTA_Settings::get($cat_slug, 'gate');

        $trigger = max(1, (int)($gate['h2_trigger'] ?? 3));

        // Split content by H2 tags
        $parts = preg_split('/(<h2[^>]*>.*?<\/h2>)/is', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!$parts) return $content;

        $h2_count = 0;
        $visible  = [];
        $hidden   = [];
        $past_gate = false;

        foreach ($parts as $part) {
            if (preg_match('/^<h2/i', $part)) {
                $h2_count++;
                if ($h2_count === $trigger && !$past_gate) {
                    $past_gate = true;
                }
            }

            if ($past_gate) {
                $hidden[] = $part;
            } else {
                $visible[] = $part;
            }
        }

        // If not enough H2s, don't gate
        if (empty($hidden)) return $content;

        $visible_html = implode('', $visible);
        $hidden_html  = implode('', $hidden);
        $gate_html    = $this->render_gate($gate, $post_id);

        return $visible_html
            . $gate_html
            . '<div class="icta-gated-content" data-post-id="' . esc_attr($post_id) . '" style="display:none">'
            . $hidden_html
            . '</div>';
    }

    private function render_gate(array $d, int $post_id): string {
        $style = 'background:' . esc_attr($d['bg_color']) . ';';
        if (!empty($d['text_color'])) {
            $style .= 'color:' . esc_attr($d['text_color']) . ';';
        }

        $label = !empty($d['label'])
            ? '<span class="icta-label">' . esc_html($d['label']) . '</span>'
            : '';

        $headline = !empty($d['headline'])
            ? '<h3 class="icta-gate__headline">' . esc_html($d['headline']) . '</h3>'
            : '';

        $desc = !empty($d['subheadline'])
            ? '<p class="icta-gate__desc">' . esc_html($d['subheadline']) . '</p>'
            : '';

        $form = !empty($d['shortcode'])
            ? '<div class="icta-gate__form">' . do_shortcode($d['shortcode']) . '</div>'
            : '';

        return <<<HTML
<div class="icta-gate" data-post-id="{$post_id}" style="{$style}">
  <div class="icta-gate__fade"></div>
  <div class="icta-gate__body">
    {$label}
    {$headline}
    {$desc}
    {$form}
  </div>
</div>
HTML;
    }

    // ── Meta Box ────────────────────────────────────

    public function add_meta_box(): void {
        add_meta_box(
            'icta_gate_meta',
            'Content Gate',
            [$this, 'render_meta_box'],
            'post',
            'side',
            'default'
        );
    }

    public function render_meta_box(\WP_Post $post): void {
        $enabled = get_post_meta($post->ID, '_icta_gate_enabled', true) === '1';
        wp_nonce_field('icta_gate_meta', 'icta_gate_nonce');
        ?>
        <label style="display:flex;align-items:center;gap:6px;cursor:pointer">
            <input type="checkbox" name="icta_gate_enabled" value="1" <?= checked($enabled, true, false) ?>>
            <span>Włącz Content Gate na tym wpisie</span>
        </label>
        <p class="description" style="margin-top:8px">
            Ukrywa treść od wybranego H2 — czytelnik musi podać email, żeby odblokować resztę artykułu.
        </p>
        <?php
    }

    public function save_meta_box(int $post_id): void {
        if (!isset($_POST['icta_gate_nonce'])) return;
        if (!wp_verify_nonce($_POST['icta_gate_nonce'], 'icta_gate_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $enabled = !empty($_POST['icta_gate_enabled']) ? '1' : '0';
        update_post_meta($post_id, '_icta_gate_enabled', $enabled);
    }
}
