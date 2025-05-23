<?php


/**
* Enqueue thumbnail modal assets.
 * Usage:
 * echo ttp_thumbnail(
 * 'https://example.com/assets/images/screenshot-admin.png',
 * 'Admin Dashboard',
 * 200
 * );
*/
function ttp_enqueue_thumbnail_assets() {
// Only load on front-end
if ( is_admin() ) {
return;
}

// Simple CSS for the overlay + image
$css = "
.ttp-modal-overlay {
position: fixed;
top: 0; left: 0; right: 0; bottom: 0;
background: rgba(0,0,0,0.8);
display: flex;
align-items: center;
justify-content: center;
z-index: 9999;
}
.ttp-modal-overlay img {
max-width: 90%;
max-height: 90%;
border: 4px solid #fff;
border-radius: 4px;
box-shadow: 0 0 10px rgba(0,0,0,0.5);
}
.ttp-modal-overlay .ttp-modal-close {
position: absolute;
top: 1rem; right: 1rem;
color: #fff;
background: none;
border: none;
font-size: 2rem;
cursor: pointer;
}
";
wp_add_inline_style( 'wp-block-library', $css );

// JS to open/close the modal
$js = "
document.addEventListener('click', function(e) {
var t = e.target;
// Open
if ( t.matches('.ttp-thumb-link, .ttp-thumb-link *') ) {
e.preventDefault();
var url = t.closest('.ttp-thumb-link').dataset.fullUrl;
var overlay = document.createElement('div');
overlay.className = 'ttp-modal-overlay';
overlay.innerHTML =
'<button class=\"ttp-modal-close\" aria-label=\"Close\">×</button>' +
'<img src=\"' + url + '\" alt=\"\" />';
document.body.appendChild(overlay);
}
// Close on close-button or click outside image
if ( t.matches('.ttp-modal-close') || t.matches('.ttp-modal-overlay') ) {
t.closest('.ttp-modal-overlay').remove();
}
});
";
wp_add_inline_script( 'jquery', $js );
}
add_action( 'wp_enqueue_scripts', 'ttp_enqueue_thumbnail_assets' );

/**
* Output a thumbnail that opens the full image in a modal.
*
* @param string $url  Full-size image URL.
* @param string $alt  Alt text for <img>.
* @param int    $size Max dimension (px) of the thumbnail.
*/
function ttp_thumbnail( $url, $alt = '', $size = 150 ) {
// Inline style to constrain thumbnail
$style = sprintf( 'max-width:%1$spx;max-height:%1$spx;border-radius:4px;cursor:pointer;', intval( $size ) );

return sprintf(
'<a href="#" class="ttp-thumb-link" data-full-url="%1$s">' .
    '<img src="%1$s" style="%2$s" alt="%3$s" loading="lazy" />' .
    '</a>',
esc_url( $url ),
esc_attr( $style ),
esc_attr( $alt )
);
}

?>
