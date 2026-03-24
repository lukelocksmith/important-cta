<?php
defined('ABSPATH') || exit;

class ICTA_Injector {

    public function __construct() {
        add_filter('the_content', [$this, 'inject'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    public function enqueue_styles(): void {
        if (!is_singular('post')) return;
        wp_enqueue_style('important-cta', ICTA_URL . 'assets/css/cta-block.css', [], ICTA_VERSION);
    }

    public function inject(string $content): string {
        if (!is_singular('post') || is_admin()) return $content;

        $post_id    = get_the_ID();
        $categories = get_the_category($post_id);
        $cat_slug   = '';
        foreach ($categories as $cat) {
            if ($cat->slug !== 'uncategorized') { $cat_slug = $cat->slug; break; }
        }

        $cta1 = ICTA_Settings::get($cat_slug, 'cta1');
        $cta2 = ICTA_Settings::get($cat_slug, 'cta2');
        $cta3 = ICTA_Settings::get($cat_slug, 'cta3');

        $cta1_trigger = max(1, (int)($cta1['h2_trigger'] ?? 2));
        $cta2_trigger = max(1, (int)($cta2['h2_trigger'] ?? 4));

        // Split content by H2 tags
        $h2_pattern = '/(<h2[^>]*>.*?<\/h2>)/is';
        $parts      = preg_split($h2_pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (!$parts) return $content;

        $h2_count  = 0;
        $new_parts = [];
        $cta1_done = false;
        $cta2_done = false;

        foreach ($parts as $part) {
            if (preg_match('/^<h2/i', $part)) {
                $h2_count++;

                // CTA 1 — before Nth H2 (default: 2nd)
                if ($h2_count === $cta1_trigger && !$cta1_done && $cta1['enabled']) {
                    $new_parts[] = $this->render_cta1($cta1);
                    $cta1_done   = true;
                }

                // CTA 2 — before Nth H2 (default: 4th)
                if ($h2_count === $cta2_trigger && !$cta2_done && $cta2['enabled']) {
                    $new_parts[] = $this->render_cta2($cta2);
                    $cta2_done   = true;
                }
            }

            $new_parts[] = $part;
        }

        $content = implode('', $new_parts);

        // CTA 3 — appended at the end
        if ($cta3['enabled']) {
            $content .= $this->render_cta3($cta3);
        }

        return $content;
    }

    private function build_style(array $d): string {
        $style = 'background:' . esc_attr($d['bg_color']) . ';';
        if (!empty($d['text_color'])) {
            $style .= 'color:' . esc_attr($d['text_color']) . ';';
        }
        return $style;
    }

    private function render_label(array $d): string {
        return !empty($d['label'])
            ? '<span class="icta-label">' . esc_html($d['label']) . '</span>'
            : '';
    }

    private function render_cta1(array $d): string {
        $style     = $this->build_style($d);
        $btn_style = 'background:' . esc_attr($d['btn_color']) . ';';
        $label     = $this->render_label($d);
        $img       = $d['image_url']
            ? '<div class="icta-1__img"><img src="' . esc_url($d['image_url']) . '" alt="' . esc_attr($d['headline']) . '" loading="lazy"></div>'
            : '';
        $desc = $d['desc']
            ? '<p class="icta-1__desc">' . esc_html($d['desc']) . '</p>'
            : '';
        $btn = $d['btn_url'] && $d['btn_text']
            ? '<a href="' . esc_url($d['btn_url']) . '" class="icta-btn" style="' . $btn_style . '" target="_blank" rel="noopener">' . esc_html($d['btn_text']) . '</a>'
            : '';

        return <<<HTML
<div class="icta-block icta-block--1" style="{$style}">
  <div class="icta-1__body">
    {$label}
    <p class="icta-1__headline">{$d['headline']}</p>
    {$desc}
    {$btn}
  </div>
  {$img}
</div>
HTML;
    }

    private function render_cta2(array $d): string {
        $style     = $this->build_style($d);
        $btn_style = 'background:' . esc_attr($d['btn_color']) . ';';
        $label     = $this->render_label($d);
        $sub = $d['subheadline']
            ? '<span class="icta-2__sub">' . esc_html($d['subheadline']) . '</span>'
            : '';
        $btn = $d['btn_url'] && $d['btn_text']
            ? '<a href="' . esc_url($d['btn_url']) . '" class="icta-btn icta-btn--sm" style="' . $btn_style . '" target="_blank" rel="noopener">' . esc_html($d['btn_text']) . '</a>'
            : '';

        return <<<HTML
<div class="icta-block icta-block--2" style="{$style}">
  <div class="icta-2__text">
    {$label}
    <span class="icta-2__headline">{$d['headline']}</span>
    {$sub}
  </div>
  {$btn}
</div>
HTML;
    }

    private function render_cta3(array $d): string {
        $style   = $this->build_style($d);
        $label   = $this->render_label($d);
        $sub     = $d['subheadline']
            ? '<p class="icta-3__sub">' . esc_html($d['subheadline']) . '</p>'
            : '';
        $divider = ($d['subheadline'] || $d['headline']) && $d['shortcode']
            ? '<hr class="icta-3__divider">'
            : '';
        $form = $d['shortcode']
            ? '<div class="icta-3__form">' . do_shortcode($d['shortcode']) . '</div>'
            : '';

        return <<<HTML
<div class="icta-block icta-block--3" style="{$style}">
  <div class="icta-3__body">
    {$label}
    <p class="icta-3__headline">{$d['headline']}</p>
    {$sub}
    {$divider}
    {$form}
  </div>
</div>
HTML;
    }
}
