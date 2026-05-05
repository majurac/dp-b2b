<?php 

function display_breadcrumb() {
    // Do not show breadcrumb on front page or single post pages
    if (is_front_page() || is_home() || is_singular('post')) {
        return;
    }

    // Call the breadcrumb function
    custom_breadcrumb();
}

function custom_breadcrumb() {
    // Get the home URL and label
    $home_url = home_url('/');
    $home_label = __('Početna', 'dreampoint-b2b'); 
    
    // Start the breadcrumb output
    $breadcrumb = '<div class="breadcrumb">';
    $breadcrumb .= '<a href="' . esc_url($home_url) . '">' . esc_html($home_label) . '</a>';

    // Add the separator
    $separator = ' / ';

    if (is_page() && !is_front_page()) {
        // For pages
        global $post;
        if ($post->post_parent) {
            // Get parent pages
            $ancestors = array_reverse(get_post_ancestors($post->ID));
            foreach ($ancestors as $ancestor) {
                $breadcrumb .= $separator . '<a href="' . esc_url(get_permalink($ancestor)) . '">' . esc_html(get_the_title($ancestor)) . '</a>';
            }
        }
        $breadcrumb .= $separator . '<span>' . get_the_title() . '</span>';
    } elseif (is_archive()) {
        // For category archives
       if (is_category()) {
            // Check if the current page is using the 'blog.php' template
            $blog_page = get_page_by_path('blog'); // Assuming your page slug is 'blog'
            
            if ($blog_page) {
                $linked_page_title = get_the_title($blog_page);
                $linked_page_url = get_permalink($blog_page);
                
                // Add the linked title of the blog page before the category title
                $breadcrumb .= $separator . '<a href="' . esc_url($linked_page_url) . '">' . esc_html($linked_page_title) . '</a>';
            }
            
            // Add the category title after the linked blog page
            $breadcrumb .= $separator . '<span>' . single_cat_title('', false) . '</span>';
        }
        // For other archives (like custom post type, date, author archives)
        else {
            $breadcrumb .= $separator . '<span>' . post_type_archive_title('', false) . '</span>';
        }
    } elseif (is_page_template('blog.php')) {
        // For other archives
       $breadcrumb .= $separator . '<span>' . get_the_title() . '</span>';
    } elseif (is_search()) {
        // For search results
        $breadcrumb .= $separator . '<span>' . sprintf(__('Rezultati pretrage za "%s"', 'dreampoint-b2b'), get_search_query()) . '</span>';
    } elseif (is_404()) {
        // For 404 pages
        $breadcrumb .= $separator . '<span>' . __('404 Nije pronađeno', 'dreampoint-b2b') . '</span>';
    }

    $breadcrumb .= '</div>';

    echo $breadcrumb;
}



?>