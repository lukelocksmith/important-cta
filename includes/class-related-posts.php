<?php
defined('ABSPATH') || exit;

class ICTA_Related_Posts {

    public function __construct() {
        add_filter('the_content', [$this, 'append_related'], 30);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    public function enqueue_styles(): void {
        if (!is_singular('post')) return;
        wp_enqueue_style('icta-related', ICTA_URL . 'assets/css/related-posts.css', [], ICTA_VERSION);
    }

    public function append_related(string $content): string {
        if (!is_singular('post') || is_admin()) return $content;

        $post_id = get_the_ID();
        $posts   = $this->get_related($post_id, 3);

        if (empty($posts)) return $content;

        $html = $this->render($posts);
        return $content . $html;
    }

    /**
     * Get related posts: same category first, fill with recent if needed.
     */
    private function get_related(int $post_id, int $count = 3): array {
        $categories = get_the_category($post_id);
        $cat_ids    = [];
        foreach ($categories as $cat) {
            if ($cat->slug !== 'uncategorized') {
                $cat_ids[] = $cat->term_id;
            }
        }

        $posts = [];

        // First: same category
        if (!empty($cat_ids)) {
            $cat_posts = get_posts([
                'category__in'   => $cat_ids,
                'post__not_in'   => [$post_id],
                'posts_per_page' => $count,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'post_status'    => 'publish',
            ]);
            $posts = $cat_posts;
        }

        // Fill with recent posts if not enough
        if (count($posts) < $count) {
            $exclude = array_merge([$post_id], wp_list_pluck($posts, 'ID'));
            $recent  = get_posts([
                'post__not_in'   => $exclude,
                'posts_per_page' => $count - count($posts),
                'orderby'        => 'date',
                'order'          => 'DESC',
                'post_status'    => 'publish',
            ]);
            $posts = array_merge($posts, $recent);
        }

        return $posts;
    }

    private function render(array $posts): string {
        ob_start();
        ?>
        <section class="icta-related" aria-label="Powiązane artykuły">
          <h2 class="icta-related__heading">Czytaj również</h2>
          <div class="icta-related__grid">
            <?php foreach ($posts as $post) :
                $thumb   = get_the_post_thumbnail_url($post->ID, 'medium_large');
                $cats    = get_the_category($post->ID);
                $cat     = null;
                foreach ($cats as $c) {
                    if ($c->slug !== 'uncategorized') { $cat = $c; break; }
                }
                $date = get_the_date('d.m.Y', $post->ID);
            ?>
            <article class="icta-related__card<?= !$thumb ? ' icta-related__card--no-img' : '' ?>">
              <a href="<?= esc_url(get_permalink($post->ID)) ?>" class="icta-related__link">
                <?php if ($thumb) : ?>
                <div class="icta-related__img">
                  <img src="<?= esc_url($thumb) ?>" alt="<?= esc_attr(get_the_title($post->ID)) ?>" loading="lazy">
                </div>
                <?php endif; ?>
                <div class="icta-related__body">
                  <?php if ($cat) : ?>
                  <span class="icta-related__cat"><?= esc_html($cat->name) ?></span>
                  <?php endif; ?>
                  <h3 class="icta-related__title"><?= esc_html(get_the_title($post->ID)) ?></h3>
                  <span class="icta-related__date"><?= esc_html($date) ?></span>
                </div>
              </a>
            </article>
            <?php endforeach; ?>
          </div>
        </section>
        <?php
        return ob_get_clean();
    }
}
