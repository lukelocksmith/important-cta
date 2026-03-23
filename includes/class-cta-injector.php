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

        // Split content by H2 tags
        $h2_pattern = '/(<h2[^>]*>.*?<\/h2>)/is';
        $parts      = preg_split($h2_pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (!$parts) return $content;

        // Count H2s and inject CTA1 after 2nd H2, CTA2 after 4th H2
        $h2_count   = 0;
        $new_parts  = [];
        $cta1_done  = false;
        $cta2_done  = false;

        foreach ($parts as $part) {
            $new_parts[] = $part;

            if (preg_match('/^<h2/i', $part)) {
                $h2_count++;

                // CTA 1 — after 2nd H2
                if ($h2_count === 2 && !$cta1_done && $cta1['enabled']) {
                    $new_parts[] = $this->render_cta1($cta1);
                    $cta1_done   = true;
                }

                // CTA 2 — after 4th H2 (~60-70% of article)
                if ($h2_count === 4 && !$cta2_done && $cta2['enabled']) {
                    $new_parts[] = $this->render_cta2($cta2);
                    $cta2_done   = true;
                }
            }
        }

        $content = implode('', $new_parts);

        // CTA 3 — appended at the end
        if ($cta3['enabled']) {
            $content .= $this->render_cta3($cta3);
        }

        return $content;
    }

    private function render_cta1(array $d): string {
        $style     = 'background:' . esc_attr($d['bg_color']) . ';';
        $btn_style = 'background:' . esc_attr($d['btn_color']) . ';';
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
  {$img}
  <div class="icta-1__body">
    <p class="icta-1__headline">{$d['headline']}</p>
    {$desc}
    {$btn}
  </div>
</div>
HTML;
    }

    private function render_cta2(array $d): string {
        $style     = 'background:' . esc_attr($d['bg_color']) . ';';
        $btn_style = 'background:' . esc_attr($d['btn_color']) . ';';
        $sub = $d['subheadline']
            ? '<span class="icta-2__sub">' . esc_html($d['subheadline']) . '</span>'
            : '';
        $btn = $d['btn_url'] && $d['btn_text']
            ? '<a href="' . esc_url($d['btn_url']) . '" class="icta-btn icta-btn--sm" style="' . $btn_style . '" target="_blank" rel="noopener">' . esc_html($d['btn_text']) . '</a>'
            : '';

        return <<<HTML
<div class="icta-block icta-block--2" style="{$style}">
  <div class="icta-2__text">
    <span class="icta-2__headline">{$d['headline']}</span>
    {$sub}
  </div>
  {$btn}
</div>
HTML;
    }

    private function render_cta3(array $d): string {
        $style = 'background:' . esc_attr($d['bg_color']) . ';';
        $sub   = $d['subheadline']
            ? '<p class="icta-3__sub">' . esc_html($d['subheadline']) . '</p>'
            : '';
        $form  = $d['shortcode']
            ? '<div class="icta-3__form">' . do_shortcode($d['shortcode']) . '</div>'
            : '';

        return <<<HTML
<div class="icta-block icta-block--3" style="{$style}">
  <div class="icta-3__body">
    <p class="icta-3__headline">{$d['headline']}</p>
    {$sub}
    {$form}
  </div>
</div>
HTML;
    }
}
